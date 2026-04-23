# Commission Logic

Reference document for the commission rate lookup, calculation, tax application, and payout flow in SettleOS. Written from analysis of the legacy MIS system (PHP/MySQL) and the Plan.md blueprint.

---

## 1. Domain Concepts

| Term | Meaning |
|---|---|
| **Commission Rate** | A rule row that says "for insurer X + business type Y + vehicle attrs Z, pay od_percent / tp_percent / net_percent / flat_amount" |
| **Global Rate** | A commission rate with `partner_id = NULL` — applies to all partners unless overridden |
| **Partner Rate** | A commission rate with `partner_id` set — overrides the global rate for that partner |
| **Payout** | The computed financial record for one policy: resolved rate × policy premiums − taxes |
| **net_po** | Net payout to partner = total_commission − TDS |

---

## 2. Commission Rate Combinations

### 2.1 Business Type Determines Required Dimensions

#### Non-Motor / Health

`vehicle_type_id = NULL`, `vehicle_attrs = NULL`.  
Only `insurer_id + business_type_id` (+ optional `policy_type_id`) are needed.

#### Motor — dimension matrix per vehicle type

Each motor rate row stores `vehicle_type_id` as a proper FK and all remaining vehicle-dimension IDs inside `vehicle_attrs jsonb`. Only the keys relevant to that vehicle type are stored — absent keys are simply absent (not null-padded).

| Vehicle Type | Keys in `vehicle_attrs` |
|---|---|
| **2W** | `coverage`, `age`, `fuel`, `subtype`, `engine`* |
| **Car** | `coverage`, `age`, `fuel`, `engine` |
| **Taxi** | `coverage`, `age`, `fuel`, `engine` |
| **PCV3W** | `coverage`, `age`, `fuel` |
| **Bus / PCV** | `coverage`, `age`, `seat` |
| **School Bus** | `coverage`, `age`, `seat` |
| **GCV** | `coverage`, `age`, `weight` |
| **GCV3W** | `coverage`, `age`, `weight` |
| **MISD** | `coverage`, `age`, `subtype`, `make` |
| **Tractor** | `coverage`, `age`, `subtype` |

\* `engine` is omitted for 2W when `subtype` = scooter.

All values are taxonomy term IDs (integers). Example row for a Car rate:
```json
{ "coverage": 3, "age": 2, "fuel": 1, "engine": 4 }
```

### 2.2 JSONB Storage Design

```sql
-- Proper FK columns (always scalar, indexed individually)
insurer_id        bigint  NOT NULL  REFERENCES insurers
business_type_id  bigint  NOT NULL  REFERENCES taxonomy_terms
partner_id        bigint  NULL      REFERENCES partner_profiles   -- NULL = global rate
vehicle_type_id   bigint  NULL      REFERENCES taxonomy_terms     -- NULL for non-motor

-- Variable-dimension vehicle attributes
vehicle_attrs     jsonb   NULL      -- only relevant keys present; NULL for non-motor

-- Deterministic key for overlap exclusion
dims_key          text    GENERATED ALWAYS AS (
                      COALESCE(vehicle_type_id::text, '_')
                      || ':'
                      || COALESCE(vehicle_attrs::text, '{}')
                  ) STORED

-- Effective date window
effective_range   daterange  NOT NULL  DEFAULT '[today,)'

-- Overlap exclusion — requires btree_gist extension
EXCLUDE USING GIST (
    insurer_id        WITH =,
    business_type_id  WITH =,
    COALESCE(partner_id, 0)  WITH =,
    dims_key          WITH =,
    effective_range   WITH &&
)

-- Indexes
CREATE INDEX ON commission_rates USING GIN (vehicle_attrs);
CREATE INDEX ON commission_rates (insurer_id, business_type_id, partner_id);
```

**Why `dims_key` works:** PostgreSQL serialises jsonb with keys in sorted order. A Car rate `{"age":2,"coverage":3,"engine":4,"fuel":1}` always produces the same text regardless of insertion order. Two admins entering the same rate get the same `dims_key` → the EXCLUDE constraint rejects the duplicate before it hits the application.

**Lookup — exact JSONB match:**
```php
CommissionRate::where('vehicle_attrs', $vehicleAttrsJson)
              ->orWhereNull('vehicle_attrs')   // non-motor path
```
No jsonb operator scanning needed; the GIN index handles containment, the exact-equals path is a btree lookup on the generated column.

### 2.3 Rate Columns (all business types)

| Column | Type | Notes |
|---|---|---|
| `od_percent` | DECIMAL(6,3) | Own Damage premium × rate. Motor only (0 for others) |
| `tp_percent` | DECIMAL(6,3) | Third Party premium × rate. Motor only (0 for others) |
| `net_percent` | DECIMAL(6,3) | Net/total premium × rate. Used for non-motor/health; overrides OD+TP sum for motor when > 0 |
| `flat_amount` | DECIMAL(14,4) | Fixed addition on top of percentage commission |
| `currency_code` | CHAR(3) | ISO 4217, default INR |

### 2.4 Effective Date Window

Every rate row has an `effective_range daterange` (PostgreSQL native type).

- Stored as `[from, to)` — inclusive start, exclusive end
- Open-ended: `[2025-04-01, infinity)`
- A `EXCLUDE USING GIST` constraint prevents two rows for the same combination from having overlapping ranges — enforced at the DB level, not in application code

---

## 3. Rate Lookup Algorithm

### Step 1 — Build match conditions from policy

```
conditions = {
    insurer_id:       policy.insurer_id,
    business_type_id: policy.motor_details.business_type_id   (or non_motor / health),
    effective_range:  @> policy.policy_date                   (PG daterange containment),
}

if business_type == motor:
    add: vehicle_type_id, coverage_type_id, vehicle_age_id, fuel_type_id
    conditionally add (based on vehicle_type — see §2.1):
        vehicle_subtype_id, engine_capacity_id, seat_capacity_id,
        weight_type_id, vehicle_make_id
```

### Step 2 — Partner-specific first, global fallback

```
1. Query commission_rates WHERE (conditions above) AND partner_id = policy.partner_id
   → if found: return it (partner override wins)

2. Query commission_rates WHERE (conditions above) AND partner_id IS NULL
   → if found: return it (global baseline)

3. If neither found: throw NoApplicableRate exception
   (old system silently used zero — SettleOS treats missing rate as a hard error)
```

### Step 3 — At-most-one guarantee

The `EXCLUDE USING GIST` constraint on `commission_rates` ensures that for any given combination of (insurer + business_type + vehicle attrs + partner + effective_range), only one row can exist. No `latest()` tiebreaker needed. If the DB accepted the insert, it's the unique match.

---

## 4. Commission Calculation

### 4.1 Motor

```
od_commission  = (od_percent  / 100) × motor_details.own_damage
tp_commission  = (tp_percent  / 100) × motor_details.third_party

if net_percent > 0:
    net_commission = (net_percent / 100) × policy.premium
else:
    net_commission = od_commission + tp_commission

total_commission = net_commission + flat_amount
```

**Coverage type modifies inputs:**
- OD only: `own_damage > 0`, `third_party = 0`
- TP only: `own_damage = 0`, `third_party > 0`
- OD+TP: both > 0

### 4.2 Non-Motor / Health

```
net_commission   = (net_percent / 100) × policy.premium
total_commission = net_commission + flat_amount
```

No OD/TP split. `od_commission = 0`, `tp_commission = 0`.

### 4.3 Output — CommissionComponents

```
od_commission      DECIMAL(14,4)
tp_commission      DECIMAL(14,4)
net_commission     DECIMAL(14,4)
flat_amount        DECIMAL(14,4)
total_commission   DECIMAL(14,4)   = net_commission + flat_amount
currency_code      CHAR(3)
```

All values computed using `brick/money` — no PHP floats anywhere in the calculation path.

---

## 5. Tax Application

### 5.1 TDS (Tax Deducted at Source) — Section 194D

Applied on `net_commission` (not total_commission).

```
tds_rate   = tax_rules lookup (jurisdiction=IN, type=tds, as_of=policy_date)
             → individual/HUF: 5%  |  company/LLP/partnership: 10%
             → annual threshold: ₹15,000 (no deduction if YTD commission < threshold)

tds_amount = net_commission × tds_rate
```

Partner's `business_type` (from `partner_profiles.business_type`) determines the rate:
- `individual`, `proprietor`, `huf` → 5%
- `partnership`, `llp`, `private_ltd`, `public_ltd` → 10%

### 5.2 GST on Commission — SAC 997161

Applied on `total_commission` (the service fee to the partner).

```
if partner.is_gst_registered = true:
    if partner.state_code == invoice_issuer.state_code (intra-state):
        cgst = total_commission × 9%
        sgst = total_commission × 9%
        igst = 0
    else (inter-state):
        igst = total_commission × 18%
        cgst = sgst = 0
else:
    cgst = sgst = igst = 0     (unregistered — no GST)
```

### 5.3 Net Payout (net_po)

```
net_po = total_commission − tds_amount
```

GST is charged **on top** of the commission invoice (it is collected from the brokerage, not deducted from the partner's payout). Net_po reflects what the partner receives; the invoice shows the GST as a separate line item.

### 5.4 Tax Lines (stored as jsonb on payout)

Each tax applied is recorded as a structured line:

```json
[
  { "code": "TDS_194D", "jurisdiction": "IN", "basis": "net_commission",
    "rate": 0.05, "amount": "1250.00", "currency": "INR" },
  { "code": "CGST_997161", "jurisdiction": "IN-OD", "basis": "total_commission",
    "rate": 0.09, "amount": "2250.00", "currency": "INR" },
  { "code": "SGST_997161", "jurisdiction": "IN-OD", "basis": "total_commission",
    "rate": 0.09, "amount": "2250.00", "currency": "INR" }
]
```

---

## 6. End-to-End Payout Flow

```
Policy created
    │
    ▼
CalculatePayout action triggered (manually by admin OR on policy save)
    │
    ├─ Lock policy row (SELECT FOR UPDATE) — prevents concurrent recalculation
    │
    ├─ Check existing payout status
    │   └─ If status = processed or voided → throw PayoutImmutable
    │
    ├─ ResolveCommissionRate (§3)
    │   └─ No rate found → throw NoApplicableRate
    │
    ├─ Currency check: rate.currency_code must match policy.currency_code
    │
    ├─ Calculate commission components (§4)
    │
    ├─ ApplyTaxRules (§5)
    │   ├─ Evaluate TDS rule → tds_amount
    │   └─ Evaluate GST rules → cgst/sgst/igst amounts
    │
    ├─ Compose payout row:
    │   od/tp/net/flat/total_commission, net_po, currency_code,
    │   tax_lines (jsonb), breakdown (jsonb trace), status = calculated
    │
    └─ Upsert Payout (INSERT or UPDATE if status = pending/calculated)
           └─ DB trigger blocks if status = processed

Admin reviews calculated payouts
    │
    ▼
ProcessPayouts action (one or batch)
    │
    ├─ Flip status: calculated → processed
    ├─ Set processed_at = now()
    └─ DB trigger now blocks any financial-column updates

Correction needed after processing
    │
    ▼
VoidPayout action
    ├─ Create new payout row: status = voided, reversing_payout_id = original.id
    └─ Create corrected payout row (new calculation)

GenerateInvoice action
    ├─ AllocateInvoiceNumber (SELECT FOR UPDATE on invoice_sequences → gapless)
    ├─ Build line_items + tax_lines from payout
    └─ Invoice is immutable after issued_at
```

---

## 7. Known Issues Fixed vs Legacy

| Legacy Problem | SettleOS Fix |
|---|---|
| GST calculated only at PDF time, not stored | Stored in `payout.tax_lines` jsonb at calculation time |
| TDS rate hardcoded as 5% default | Driven by `tax_rules` table per jurisdiction + business type |
| `commissions` uses string columns; `global_commissions` uses FKs | Single `commission_rates` table with FK columns only |
| `effective_date` column exists but never filtered on | `effective_range daterange` with `@>` containment query |
| No overlap prevention — two rates for same combo accepted | `EXCLUDE USING GIST` constraint rejects overlap at DB level |
| Silent failure when no rate found (commission = 0) | `NoApplicableRate` exception — surfaced as validation error |
| `flat_amount` sourced from `policy` for non-motor (bug) | Always sourced from `commission_rates.flat_amount` |
| No concurrency protection on recalculation | `SELECT FOR UPDATE` lock on policy row |
| Payout mutable after processing | DB trigger blocks financial-column updates after `status = processed` |
| Company details hardcoded in controller | `invoice_issuers` table, configurable per tenant |
| TDS: individual vs others vs HUF — three separate columns on `tds` table | Single `tax_rules` row with `conditions` jsonb encoding `business_type` |

---

## 8. Rate Admin UI — Entry Rules

The commission form must enforce these field visibility rules client-side (same as legacy):

```
business_type = motor?
  → show: vehicle_type, coverage_type, vehicle_age, fuel_type
  → vehicle_type = 2W?
      show: vehicle_subtype
      if vehicle_subtype ≠ scooter: show engine_capacity
  → vehicle_type = Car / Taxi?
      show: engine_capacity
  → vehicle_type = PCV3W?
      (fuel_type already shown)
  → vehicle_type = Bus / School Bus?
      show: seat_capacity, hide: fuel_type, engine_capacity
  → vehicle_type = GCV / GCV3W?
      show: weight_type, hide: fuel_type, engine_capacity, vehicle_model
  → vehicle_type = MISD / Tractor?
      show: vehicle_subtype, vehicle_make (jcb / others)
      hide: fuel_type, engine_capacity

  → show OD % + TP % inputs
  → net_percent_mode checkbox:
      checked → show net_percent input, hide OD%/TP% inputs
      unchecked → show OD%/TP%, hide net_percent
  → always show flat_amount

business_type = non_motor / health?
  → hide all vehicle fields
  → hide OD%, TP%
  → show net_percent, flat_amount only
```

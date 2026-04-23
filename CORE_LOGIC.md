# MIS — Core Logic Map

**Stack:** Laravel 8.83 · PHP 8.0 · Vue 2.6 + BootstrapVue (Blade-embedded, not SPA) · MySQL 8 · Kapella admin template
**Branch:** `partner/vue` (latest)
**Domain:** Insurance MIS — policy management + partner commission & payout engine for an insurance brokerage (primarily motor; also non-motor and health).

---

## 1. Domain

| Layer | Entities |
|---|---|
| Actors | `User` (admin), `Partner` (broker/agent) |
| Policy core | `Policy` (morph parent) → `MotorPolicy` / `NonMotorPolicy` / `HealthPolicy` |
| Commercials | `Commission` (partner-specific), `GlobalCommission` (org-wide fallback), `TDS`, `Payout` |
| Taxonomy | `Insurer`, `BusinessType` (commission type: motor/non-motor/health), `PolicyType`, `CoverageType`, `PaymentType` |
| Vehicle taxonomy | `VehicleType`, `TwoWheelerType`, `MiscDType`, `FuelType`, `VehicleEngineCapacity`, `VehicleSeatCapacity`, `VehicleMake`, `VehicleModel`, `VehicleAge`, `VehicleWeightType` |
| Secondary | `Customer`, `PolicyDocument`, `PartnerInfo`, `PartnerDocument`, `PartnerOtp`, `PartnerPassword`, `PartnerTempPassword` |

**Central transaction:** Policy is sold → commission resolved (partner rate first, else global) → TDS applied → payout generated → admin processes → PDF invoice.

---

## 2. Auth & Scoping

Two separate guards:

| Guard | Provider table | User model | Route prefix | Middleware |
|---|---|---|---|---|
| `web` | `users` | `App\Models\User` | `/admin/*` | `auth` |
| `partner` | `partners` | `App\Models\Partner` | `/partner/*` | `auth:partner` |

- No Spatie roles/permissions wired in — guard separation is the entire access model despite `spatie/laravel-permission` in composer.
- Partner data-scoping done at controller level: `->where('partner_id', Auth::user()->id)` in `Web\Partner\*` list queries.
- `auth:api` guard defined in `config/auth.php` but `routes/api.php` is empty.

---

## 3. Routes — [routes/web.php](routes/web.php)

Public: `GET /knowledge_base`, `GET|POST /login`, `/logout`, `/forgot-password`, `/confirm_password` (throttle 6,1).

### 3.1 Admin (`/admin`, auth, namespace `App\Http\Controllers\Web\Admin`)

| Group | Routes (verb path → ctrl@method) |
|---|---|
| Dashboard / profile | `GET /dashboard → DashboardController@index`; `GET /profile`, `POST /profile/change_password` |
| Dropdown util | `POST /get-options → DropdownController@index` |
| Settings index | `GET /settings → SettingController@index`; `POST /settings/list` (counts) |
| Global commission | REST `/settings/mis/global-commissions` + `POST .../list`, `POST .../options` |
| Policies | REST `/policies`; `POST /policies/list`, `POST /policies/options`, `GET /policies/export` (Excel) |
| Partners | REST `/partners`; `POST /partners/list`, `POST /partners/options`, `POST /partners/reset-password`, `POST /partners/send-password-link` |
| Partner commissions (nested) | REST `/partners/{partner}/commissions` + `GET .../options` |
| Partner TDS (nested) | REST `/partners/{partner}/tds` |
| Payouts | REST `/payouts`; `POST /payouts/calculate`, `POST /payouts/list`, `POST /payouts/process`, `POST /payouts/process-selected`, `GET /payouts/export`, `GET /payouts/invoice/{partner_id}/{policy_id}` (PDF) |
| Reports | REST `/reports` |
| Taxonomy CRUD (each REST + list) | `/insurers`, `/commission-types` (→ BusinessTypeController), `/payment-types`, `/vehicle-types`, `/fuel-types`, `/engines` (→ VehicleEngineCapacity), `/seats` (→ VehicleSeatCapacity), `/makes`, `/models`, `/weights`, `/vehicle-ages` (+ `GET /status`, `POST /status/{id}` toggle) |
| Admin utility (danger) | `GET /settings/partner/reset/all`, `GET /settings/cache/clear`, `GET /settings/config/cache`, `GET /settings/config/clear` |

### 3.2 Partner (`/partner`, auth:partner, namespace `App\Http\Controllers\Web\Partner`)

- `GET / → DashboardController@index`, `GET /dashboard`, `GET /profile`, `POST /profile/change_password`
- REST `/policies` + `POST /policies/list`, `POST /policies/options`
- REST `/payouts` + `POST /payouts/list`
- REST `/reports` + `POST /reports/list`

### 3.3 Stale

- [routes/web copy.php](routes/web%20copy.php) — 194 lines, not loaded, legacy.
- ~220 lines of commented-out route blocks still inside `routes/web.php`.

---

## 4. Controllers (app/Http/Controllers)

**Admin (43)** — all under `Web\Admin\`. Mostly thin REST around a model; the interesting ones:

| Controller | Non-trivial methods |
|---|---|
| `PayoutController` | `calculate()`, `calculate_payout()`, `get_commission()`, `process()`, `processSelected()`, `export()`, `download_report()` (PDF invoice) |
| `CommissionController` | `store()` uses `updateOrCreate` over the (partner, insurer, type, vehicle attrs) composite |
| `GlobalCommissionController` | Same as above without `partner_id` |
| `PartnerController` | `resetPassword`, `sendPasswordResetLink`, custom list/options |
| `TDSController` | `store()` = `updateOrCreate` by `partner_id` |
| `PolicyController` | `list()`, `options()`, `exportToExcel()` |
| `VehicleAgeController` | `status()` toggles active flag |
| `SettingController` | Returns aggregated counts for the settings dashboard |
| `DropdownController` | Single endpoint returning joined option sets |

**Partner (5)** — `PolicyController`, `PayoutController`, `ReportController`, `ProfileController`, `DashboardController`. All scope to `Auth::user()->id`.

**Auth** — `Auth\LoginController` (handles both admin & partner via guard switch).

---

## 5. Models & Relationships

```
Insurer ─┐
         ├─ Policy (partner_id, customer_id, insurer_id, policyable_id/policyable_type,
         │         policy_type, internal_policy_id, insurer_policy_number,
         │         net_amount, payment_type, policy_date, imd_code; SoftDeletes)
         │     │
         │     ├─ morphTo policyable → MotorPolicy | NonMotorPolicy | HealthPolicy
         │     ├─ belongsTo Partner, Customer, Insurer
         │     ├─ hasOne   Payout
         │     └─ hasMany  PolicyDocument
         │
         ├─ Commission       (partner_id + insurer_id + commission_type_id + vehicle attrs;
         │                    od_percent, tp_percent, net_percent, flat_amount, effective_date; SoftDeletes)
         │
         └─ GlobalCommission (insurer_id + commission_type_id + full vehicle taxonomy;
                              same rate columns + effective_date; SoftDeletes)

Partner ─┬─ hasMany  Policy, Commission, Payout, PartnerDocument
         ├─ hasOne   PartnerInfo (GSTIN, PAN, Aadhaar, bank, address; hidden sensitive fields)
         └─ hasOne   TDS (individual / others / huf rates; default individual = 5%)

Payout ──  belongsTo Partner, Policy
           cols: tp_/od_/net_percent, flat_amount,
                 od_commission_amount, tp_commission_amount, net_commission_amount,
                 total_commission, tds_percent, tds_amount, net_po,
                 status, payment_mode, payment_amount, payment_date; SoftDeletes

MotorPolicy — vehicle_type_id, fuel_type_id, vehicle_age_id,
              engine/seat/make/model/weight/coverage FKs,
              registration_number, own_damage, third_party, passenger_count; SoftDeletes
```

Traits in play: `SoftDeletes` on commercial tables. `HasRoles` / `InteractsWithMedia` / `LogsActivity` are available via installed packages but **not applied** on any model.

---

## 6. Commission & Payout — the core engine

Located in [app/Http/Controllers/Web/Admin/PayoutController.php](app/Http/Controllers/Web/Admin/PayoutController.php).

### 6.1 `get_commission($policy)` — two-tier lookup

```
1. Query commissions WHERE
     partner_id = policy.partner_id
   AND insurer_id = policy.insurer_id
   AND commission_type_id = policy.policyable.policy_type.business_type_id
   AND <vehicle-attr filters, per vehicle_type>
   → first match wins (partner override)

2. Else query global_commissions with same filters (no partner_id)
   → first match wins (global baseline)

3. Else null  → commission treated as zero
```

Vehicle-attr filter sets (motor only):

| vehicle_type_id | extra filters |
|---|---|
| 1 (2W) | vehicle_subtype_id, vehicle_engine_capacity_id |
| 2, 6, 7, 8 | vehicle_seat_capacity_id |
| 4 (CV) | vehicle_weight_type_id (no model) |
| 5, 6 (+1) | vehicle_engine_capacity_id |
| Non-motor | commission_type_id + insurer_id only |

`effective_date` column exists on both `commissions` and `global_commissions` (added in commit `a25d6ed`) but **is not filtered on** in `get_commission()` yet.

### 6.2 `calculate_payout($policy)` — formula

**Motor:**
```
OD_Commission  = (od_percent  / 100) × motor_policy.own_damage
TP_Commission  = (tp_percent  / 100) × motor_policy.third_party
if net_percent > 0:
    Net_Commission = (net_percent / 100) × policy.net_amount
else:
    Net_Commission = OD_Commission + TP_Commission
Total_Commission = Net_Commission + flat_amount
```

**Non-motor / health:**
```
Net_Commission   = (net_percent / 100) × policy.net_amount     (0 if no commission)
Total_Commission = Net_Commission + (policy.flat_amount || commission.flat_amount)
```

**TDS:**
```
tds_rate   = partner.tds.individual  ||  5.0
tds_amount = Net_Commission × tds_rate / 100
net_po     = Total_Commission − tds_amount
```

Writes/updates the `payouts` row for `(partner_id, policy_id)`.

### 6.3 Processing

- `POST /admin/payouts/process` and `/process-selected` flip `payout.status = 'processed'`, set `payment_date = now()`.
- `POST /admin/payouts/calculate` re-runs `calculate_payout()` for the filtered set (used for the "Recalculate" CTA after commission changes).

### 6.4 Invoice (PDF) — `download_report($partner_id, $policy_id)`

Uses `barryvdh/laravel-dompdf`. Template: [resources/views/admin/payout/invoice.blade.php](resources/views/admin/payout/invoice.blade.php).

- GST logic: if partner has GSTIN → CGST 9% + SGST 9% (intra-state assumed); else IGST 0%.
- Amount-in-words via `money_to_words()` helper.
- Filename: `{date}_{partner_name}.pdf`.
- **Company details hardcoded** in controller (BHESAJ BUSINESS SERVICES PVT. LTD., GSTN `21AAICB9948F1ZK`, PAN `AAICB9948F`, SAC 9983, Bhubaneswar address). Not config-driven.

---

## 7. Database — [database/migrations/](database/migrations/)

Chronological entity groups:

| Date cluster | Tables |
|---|---|
| 2014 Laravel defaults | `users`, `password_resets`, `failed_jobs` |
| 2021-07-03…08 | `partners`, `partner_infos`, `partner_passwords`, `partner_otps`, `partner_temp_passwords` |
| 2021-07-09…14 | `policies`, `motor_policies`, `non_motor_policies`, `health_policies`, `policy_documents`, `customers` |
| 2021-07-15…18 | `insurers`, `commissions`, `payouts`, `tds` |
| 2023-01 | Vehicle taxonomy tables (`vehicle_types`, `fuel_types`, `vehicle_engine_capacities`, `vehicle_seat_capacities`, `vehicle_makes`, `vehicle_models`, `vehicle_ages`, `vehicle_weight_types`, `two_wheeler_types`, `misc_d_types`, `coverage_types`), `business_types`, `policy_types`, `payment_types` |
| 2023-02-25 | `global_commissions` |
| 2023-03 | `countries`, `states`, `cities`, `districts` (unused?) |

Notable columns:

- `policies.policyable_id/policyable_type` — polymorphic to subtype policies.
- `commissions.*` stores some vehicle dimensions as **strings** (`commission_type`, `vehicle_type`); `global_commissions` uses proper FKs (`*_id`). Schema drift.
- `policies.net_amount`, `motor_policies.own_damage`, `motor_policies.third_party` — the three monetary inputs to commission.
- `payouts.net_po` — final payable amount.
- `tds.individual` — decimal percent, default 5.

---

## 8. Settings modules (all REST, all have `POST /list`)

| UI label | Route prefix | Controller | Model | Table |
|---|---|---|---|---|
| Insurers | `/admin/insurers` | `InsurerController` | `Insurer` | `insurers` |
| Commission Types | `/admin/commission-types` | `BusinessTypeController` | `BusinessType` | `business_types` |
| Vehicle Types | `/admin/vehicle-types` | `VehicleTypeController` | `VehicleType` | `vehicle_types` |
| Fuel Types | `/admin/fuel-types` | `FuelTypeController` | `FuelType` | `fuel_types` |
| Engines | `/admin/engines` | `VehicleEngineCapacityController` | `VehicleEngineCapacity` | `vehicle_engine_capacities` |
| Seats | `/admin/seats` | `VehicleSeatCapacityController` | `VehicleSeatCapacity` | `vehicle_seat_capacities` |
| Makes | `/admin/makes` | `VehicleMakeController` | `VehicleMake` | `vehicle_makes` |
| Models | `/admin/models` | `VehicleModelController` | `VehicleModel` | `vehicle_models` |
| Vehicle Ages | `/admin/vehicle-ages` | `VehicleAgeController` | `VehicleAge` | `vehicle_ages` |
| Weights | `/admin/weights` | `VehicleWeightTypeController` | `VehicleWeightType` | `vehicle_weight_types` |
| Payment Types | `/admin/payment-types` | `PaymentTypeController` | `PaymentType` | `payment_types` |
| Global Commission | `/admin/settings/mis/global-commissions` | `GlobalCommissionController` | `GlobalCommission` | `global_commissions` |

Two-wheeler / Misc-D subtypes and Coverage Type exist as models but are managed via other screens (not standalone settings entries).

---

## 9. Views — [resources/views/](resources/views/)

Blade + Vue 2 (BootstrapVue). No Vue Router; each page is its own Blade. Vue is used inline for the interactive tables, forms, filters, modals.

Admin-side notable files:

- `admin/partner/index.blade.php`, `admin/partner/commission.blade.php`, `admin/partner/tds.blade.php`
- `admin/policy/{index,add,edit,table,export}.blade.php`
- `admin/payout/{index,table,export,invoice}.blade.php` — `invoice.blade.php` is the dompdf template
- `admin/global_commission/{index,add,edit,table}.blade.php`
- `admin/setting/index.blade.php` + per-entity setting views

Partner-side: `partner/policy/*`, `partner/payout/*`, `partner/profile/*`, `partner/report/*`.

Frontend entry: [resources/js/app.js](resources/js/app.js) + [resources/js/bootstrap.js](resources/js/bootstrap.js) — just Axios + global Vue setup; webpack.mix compiles to `public/js/app.js`.

---

## 10. Exports

- Excel: Blade templates rendered as HTML and returned with Excel MIME — no `maatwebsite/excel` or PhpSpreadsheet. See [resources/views/admin/policy/export.blade.php](resources/views/admin/policy/export.blade.php) and [resources/views/admin/payout/export.blade.php](resources/views/admin/payout/export.blade.php).
- PDF: `barryvdh/laravel-dompdf` for payout invoices (see §6.4).
- No import functionality.

---

## 11. Seeders ([database/seeders/](database/seeders/))

Taxonomy + sample data:

- `AdminUserSeeder` — default admin login
- `InsurerSeeder`, `PaymentTypeSeeder`, `PartnerStatusSeeder`, `ReportTypeSeeder`
- `VehicleEngineCapacitySeeder`, `VehicleModelSeeder` (and siblings for other vehicle taxonomy tables)
- `GlobalCommissionSeeder` — sample org-wide rates
- `PolicySeeder` — sample policies for local testing

No factories.

---

## 12. Smells / gotchas

- [routes/web copy.php](routes/web%20copy.php) — leftover file, delete candidate.
- ~220 lines of commented-out routes still in `routes/web.php`.
- `commissions` table uses **string** vehicle-type columns while `global_commissions` uses proper FKs — schema drift that complicates `get_commission()` (motor branch uses `_id` lookups either way, confirm in the code before any refactor).
- `effective_date` on commissions is stored but not filtered — "commission effective date" (commit `a25d6ed`) is **incomplete**.
- Invoice company details (GSTN, PAN, address, SAC, tax rates) are hardcoded in `PayoutController::download_report()`.
- `/admin/settings/partner/reset/all` and cache-clear routes are `GET` with only `auth` guard → anyone logged in as admin can CSRF-hit them via a link.
- Payout export uses `->get()` then `->items()` in one code path — `items()` exists on `LengthAwarePaginator`, not on `Collection`. Likely a latent bug.
- `calculate_payout()` continues with zeroed fields when `get_commission()` returns null — silent failure instead of flagging "no rate configured".
- Admin dashboard is stubbed ("TODO: get all the admin dashboard data").
- Partner `PolicyController` has a `// TODO: move calculations to sql` — payout loop runs in PHP per-row.
- `auth:api` guard + empty `routes/api.php` — unused.

---

## 13. End-to-end flow (worked example)

1. Admin creates an **Insurer** + **GlobalCommission** rows for a vehicle segment.
2. Admin creates a **Partner**, optionally adds partner-specific **Commission** overrides and a **TDS** rate.
3. A policy is entered — `Policy` + `MotorPolicy` (polymorphic) with `own_damage`, `third_party`, `net_amount`, vehicle attributes.
4. On Payout screen, admin hits **Calculate** → `PayoutController::calculate_payout()`:
   - `get_commission()` tries partner-specific match → falls back to global → else null.
   - Applies OD%/TP%/Net%/flat formula from §6.2.
   - Pulls `partner.tds.individual` (default 5%), computes `tds_amount`, `net_po`.
   - Upserts `Payout` row.
5. Admin selects payouts, hits **Process** → status flips to `processed`, `payment_date = now()`.
6. Admin clicks **Invoice** → dompdf renders `invoice.blade.php` with GST based on partner GSTIN presence.
7. Partner logs in at `/partner`, sees only their own policies and payouts (filtered by `Auth::user()->id`).

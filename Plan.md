# SettleOS — Design Blueprint

SettleOS is a modern, enterprise-grade Laravel application for managing insurance policies, partner commission structures, and payout processing. Designed for global deployment: multi-tenant, multi-currency, jurisdiction-agnostic tax engine, append-only financial ledgers, data-residency-aware hosting.

---

## 1. Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.3 |
| Framework | Laravel 13 |
| Frontend | Inertia.js + Vue 3 (Composition API, `<script setup>`) + Vuetify 3 + Vite |
| Auth | Laravel Breeze (Inertia + Vue 3 starter), Fortify under the hood |
| Permissions | spatie/laravel-permission — roles: `admin`, `partner`, `auditor`, `super_admin` (cross-tenant) |
| Multi-tenancy | **stancl/tenancy** — database-per-tenant with regional hosting for data residency |
| Actions | lorisleiva/laravel-actions — one class per domain operation |
| DTOs / Validation | spatie/laravel-data + Form Requests |
| State machines | spatie/laravel-model-states (payout lifecycle, including terminal `Voided`) |
| PDF | barryvdh/laravel-dompdf (or Spatie Browsershot for higher fidelity) |
| Excel | maatwebsite/laravel-excel (queued XLSX) |
| Media | spatie/laravel-medialibrary on **private S3** (per-tenant bucket) + temporary signed URLs |
| Audit log | spatie/laravel-activitylog |
| Queue | database driver in dev, Redis in prod (tenant-aware job payloads) |
| **Database** | **PostgreSQL 16** — `jsonb` GIN indexes for `breakdown` traces, `EXCLUDE` constraints for rate-window overlaps, `daterange` types, advisory locks |
| Money | brick/money + `app/Support/Money.php` — decimal-only, no floats |
| Currency / FX | moneyphp/money catalogue; FX rates via `exchange_rates` table (nightly sync job) |
| Encryption | Laravel `encrypted:hashed` cast for KYC; envelope encryption via AWS KMS in prod |
| Tests | Pest 3 + Larastan (PHPStan level 8) + Laravel Pint |
| CI | GitHub Actions: Pint → PHPStan → Pest (with Postgres matrix) → Vite build |
| Dev env | Docker Compose (app + postgres + redis + mailpit + minio) |

Single SPA shell: one Inertia + Vue 3 + Vuetify app serves admin, partner, and auditor surfaces. The tenant is resolved from the subdomain / domain and injected into every request; per-tenant branding, currency, locale, and legal defaults flow into the shell.

---

## 2. Domain model

```
Tenant   (cross-region; governs data residency, currency, locale, tax regime)
  ├─ hasMany User, Insurer, Policy, CommissionRate, Payout, InvoiceIssuer, TaxRule
  └─ columns  region, default_currency, default_locale, tax_regime_slug, timezone

User  ──(belongsToMany)── Role (admin | partner | auditor | super_admin)
  └─ hasOne PartnerProfile   [when role=partner]
     ├─ tax_id_type    (enum: PAN | GSTIN | SSN | EIN | VAT | ABN | CPF | ...)
     ├─ tax_id_number  (encrypted:hashed)
     ├─ tax_id_hash    (deterministic hash for uniqueness / lookup)
     ├─ legal_name, address, country_code, state_code
     └─ bank_account_encrypted

Insurer ─┬─ hasMany Policy
         └─ hasMany CommissionRate

Policy
  ├─ belongsTo Tenant, Partner (User), Customer, Insurer
  ├─ morphTo   details  (MotorDetails | NonMotorDetails | HealthDetails)
  ├─ columns   policy_date, premium_amount, currency_code  (ISO 4217)
  ├─ hasOne    Payout
  ├─ hasMany   Documents (spatie/media-library on private disk)
  └─ logsActivity

CommissionRate
  ├─ belongsTo Tenant, Insurer, BusinessType, PolicyType, CoverageType
  ├─ belongsTo (taxonomy) VehicleType, FuelType, ...
  ├─ nullable  Partner   (null = global; set = partner override)
  ├─ columns   od_percent, tp_percent, net_percent, flat_amount, currency_code
  ├─ range     effective_range   (Postgres daterange, [from, to))
  └─ logsActivity (NO softDeletes — expired rates stay; unused rows are voided via state)

Payout      (immutable financial ledger — append-only)
  ├─ belongsTo Tenant, Policy, Partner, CommissionRate
  ├─ state     status   (pending | calculated | processed | voided)      ← no softDeletes
  ├─ columns   od_commission, tp_commission, net_commission, flat_amount
  ├─ columns   total_commission, net_po, currency_code
  ├─ json      tax_lines (breakdown of every tax applied, see §5.5)
  ├─ json      breakdown (full calculation trace, jsonb + GIN index)
  ├─ columns   calculated_at, processed_at, voided_at, voided_by_id, void_reason
  └─ immutable: no UPDATE allowed on financial columns after status=processed;
                corrections happen via Void + new Payout referencing the voided one
                (reversing_payout_id nullable FK)

TaxRule     (replaces hardcoded tds/gst columns)
  ├─ belongsTo Tenant
  ├─ columns   code, jurisdiction_code, applies_to (enum: commission | premium | invoice_total),
                calculator (strategy key: india_tds | india_gst | us_backup_withholding | eu_vat | ...)
  ├─ json      conditions   (country, tax_id_type, threshold, partner tags, date window)
  ├─ decimal   rate_percent (nullable; some rules use flat or tiered)
  ├─ json      tiers        (optional threshold-tiered rates)
  └─ range     effective_range

InvoiceIssuer       (the company issuing invoices, per-tenant)
  ├─ belongsTo Tenant
  └─ legal_name, tax_id_number (encrypted), tax_id_type, address, state_code, logo_path, is_active

InvoiceSequence     (gapless, auditable invoice numbering)
  ├─ belongsTo Tenant, InvoiceIssuer
  ├─ columns   fiscal_year, prefix, next_value, updated_at
  └─ unique    (tenant_id, issuer_id, fiscal_year)
    Allocation pattern: SELECT ... FOR UPDATE → increment → return.
    Number is reserved inside the same DB transaction that creates the invoice row;
    rollback releases it atomically — no gaps under normal operation.

Invoice
  ├─ belongsTo Tenant, Payout, InvoiceIssuer
  ├─ columns   number (tenant-unique, gapless per issuer/FY), issued_at, currency_code
  ├─ json      line_items, tax_lines
  └─ immutable after issued_at (corrections → credit note, never edit)

ExchangeRate        (nightly sync; used only for reporting, never for ledger recording)
  └─ from_currency, to_currency, rate, fetched_at, source

Taxonomy            (tenant-scoped where meaningful)
  BusinessType (motor | non-motor | health) · PolicyType · CoverageType · PaymentType
  VehicleType · FuelType · VehicleEngineCapacity · VehicleSeatCapacity
  VehicleMake · VehicleModel · VehicleAge · VehicleWeightType · TwoWheelerType · MiscDType
```

Key properties:

- **Tenant is the root aggregate.** Every tenant-scoped table carries `tenant_id`; `stancl/tenancy` swaps the connection per request so queries never cross tenants. See §13.
- **Unified `commission_rates` table** — partner-specific and global rates share the same shape, distinguished by `partner_id` nullability.
- **Date windows as `daterange`**, with Postgres `EXCLUDE` constraint preventing overlaps for identical taxonomy combinations (see §3.1).
- **Payouts are immutable.** No `UPDATE` of financial columns after `status=processed`. No `softDeletes`. Corrections issue a new payout with `status=voided` plus a reversing row — a full append-only ledger.
- **Tax is data, not code.** The `tax_rules` table + strategy-pattern calculators handle India TDS/GST, US backup withholding, EU VAT, etc. Adding a jurisdiction is a migration + a calculator class.
- **Currency is explicit** on every monetary record (`currency_code` ISO 4217); no conversions happen silently.
- **KYC is encrypted.** `tax_id_number` uses `encrypted:hashed` cast; uniqueness via `tax_id_hash`. Regional columns (`pan`, `gstin`) replaced by `(tax_id_type, tax_id_number)` tuple.

---

## 3. Schema highlights

### 3.1 `commission_rates` — overlap-proof

```php
Schema::create('commission_rates', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tenant_id')->constrained();
    $t->foreignId('insurer_id')->constrained();
    $t->foreignId('business_type_id')->constrained();
    $t->foreignId('policy_type_id')->nullable()->constrained();
    $t->foreignId('coverage_type_id')->nullable()->constrained();
    $t->foreignId('vehicle_type_id')->nullable()->constrained();
    $t->foreignId('fuel_type_id')->nullable()->constrained();
    $t->foreignId('vehicle_age_id')->nullable()->constrained();
    $t->foreignId('vehicle_make_id')->nullable()->constrained();
    $t->foreignId('vehicle_model_id')->nullable()->constrained();
    $t->foreignId('vehicle_engine_capacity_id')->nullable()->constrained();
    $t->foreignId('vehicle_seat_capacity_id')->nullable()->constrained();
    $t->foreignId('vehicle_weight_type_id')->nullable()->constrained();
    $t->foreignId('partner_id')->nullable()->constrained('users');
    $t->decimal('od_percent',  5, 2)->default(0);
    $t->decimal('tp_percent',  5, 2)->default(0);
    $t->decimal('net_percent', 5, 2)->default(0);
    $t->decimal('flat_amount', 14, 4)->default(0);
    $t->char('currency_code', 3);                // ISO 4217
    $t->timestamps();
    $t->foreignId('created_by_id')->constrained('users');
    $t->foreignId('updated_by_id')->nullable()->constrained('users');
});

// Postgres-native window + exclusion constraint
DB::statement("ALTER TABLE commission_rates ADD COLUMN effective_range daterange NOT NULL");

DB::statement(<<<SQL
    ALTER TABLE commission_rates ADD CONSTRAINT commission_rates_no_overlap EXCLUDE USING GIST (
        tenant_id                       WITH =,
        insurer_id                      WITH =,
        business_type_id                WITH =,
        COALESCE(policy_type_id, 0)     WITH =,
        COALESCE(coverage_type_id, 0)   WITH =,
        COALESCE(vehicle_type_id, 0)    WITH =,
        COALESCE(fuel_type_id, 0)       WITH =,
        COALESCE(vehicle_age_id, 0)     WITH =,
        COALESCE(vehicle_make_id, 0)    WITH =,
        COALESCE(vehicle_model_id, 0)   WITH =,
        COALESCE(vehicle_engine_capacity_id, 0) WITH =,
        COALESCE(vehicle_seat_capacity_id, 0)   WITH =,
        COALESCE(vehicle_weight_type_id, 0)     WITH =,
        COALESCE(partner_id, 0)         WITH =,
        effective_range                 WITH &&
    )
SQL);
```

The DB refuses inserts that would overlap another row with the same taxonomy key. Form Requests surface the DB error as a friendly field-level message; no application-level `latest()` hack masks bad input.

### 3.2 `payouts` — immutable

```php
Schema::create('payouts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tenant_id')->constrained();
    $t->foreignId('policy_id')->constrained();
    $t->foreignId('partner_id')->constrained('users');
    $t->foreignId('commission_rate_id')->nullable()->constrained();
    $t->foreignId('reversing_payout_id')->nullable()->constrained('payouts'); // for voids

    $t->decimal('od_commission',    14, 4)->default(0);
    $t->decimal('tp_commission',    14, 4)->default(0);
    $t->decimal('net_commission',   14, 4)->default(0);
    $t->decimal('flat_amount',      14, 4)->default(0);
    $t->decimal('total_commission', 14, 4)->default(0);
    $t->decimal('net_po',           14, 4)->default(0);
    $t->char('currency_code', 3);

    $t->string('status')->default('pending');           // spatie/model-states
    $t->jsonb('tax_lines');                             // [{code, jurisdiction, rate, amount}, ...]
    $t->jsonb('breakdown');                             // full calc trace
    $t->timestamp('calculated_at')->nullable();
    $t->timestamp('processed_at')->nullable();
    $t->timestamp('voided_at')->nullable();
    $t->foreignId('voided_by_id')->nullable()->constrained('users');
    $t->string('void_reason')->nullable();
    $t->timestamps();

    $t->unique(['tenant_id', 'policy_id', 'reversing_payout_id']);
    // NO softDeletes — append-only ledger
});

DB::statement('CREATE INDEX payouts_breakdown_gin ON payouts USING GIN (breakdown)');
DB::statement('CREATE INDEX payouts_tax_lines_gin ON payouts USING GIN (tax_lines)');

// A DB trigger blocks financial-column updates once status='processed'.
DB::unprepared(<<<SQL
    CREATE OR REPLACE FUNCTION payouts_immutable_after_processed() RETURNS trigger AS $$
    BEGIN
      IF OLD.status = 'processed' AND (
         NEW.total_commission <> OLD.total_commission OR
         NEW.net_po           <> OLD.net_po           OR
         NEW.tax_lines        <> OLD.tax_lines
      ) THEN
        RAISE EXCEPTION 'payout % is immutable after processing; use void + new payout', OLD.id;
      END IF;
      RETURN NEW;
    END;
    $$ LANGUAGE plpgsql;

    CREATE TRIGGER trg_payouts_immutable
        BEFORE UPDATE ON payouts
        FOR EACH ROW EXECUTE FUNCTION payouts_immutable_after_processed();
SQL);
```

### 3.3 `invoice_sequences` — gapless allocation

```php
Schema::create('invoice_sequences', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tenant_id')->constrained();
    $t->foreignId('invoice_issuer_id')->constrained();
    $t->smallInteger('fiscal_year');
    $t->string('prefix')->default('INV');
    $t->unsignedBigInteger('next_value')->default(1);
    $t->timestamps();
    $t->unique(['tenant_id', 'invoice_issuer_id', 'fiscal_year']);
});
```

Allocation (inside the invoice-creation transaction):

```php
DB::transaction(function () use ($payout) {
    $seq = InvoiceSequence::where([...])->lockForUpdate()->firstOrCreate([...]);
    $number = sprintf('%s/%d/%06d', $seq->prefix, $seq->fiscal_year, $seq->next_value);
    $seq->increment('next_value');

    Invoice::create([
        'number'   => $number,
        'payout_id'=> $payout->id,
        // ...
    ]);
});
```

Transaction rollback releases the reservation; the same `next_value` is handed to the next successful attempt. No gaps under normal operation; the rare gap case (hard crash between `increment` and `Invoice::create`) is explicit and recoverable via an audit tool.

### 3.4 `tax_rules` — jurisdiction-agnostic

```php
Schema::create('tax_rules', function (Blueprint $t) {
    $t->id();
    $t->foreignId('tenant_id')->constrained();
    $t->string('code');                  // 'india_tds_194D' etc.
    $t->string('jurisdiction_code');     // ISO country + state
    $t->string('applies_to');            // commission | premium | invoice_total
    $t->string('calculator');            // key into strategy registry
    $t->jsonb('conditions');             // country, tax_id_type, thresholds, tags
    $t->decimal('rate_percent', 6, 3)->nullable();
    $t->jsonb('tiers')->nullable();      // [{above: 0, rate: 0.05}, {above: 100000, rate: 0.10}]
    $t->char('currency_code', 3)->nullable();
    $t->timestamps();
});

DB::statement("ALTER TABLE tax_rules ADD COLUMN effective_range daterange NOT NULL");
DB::statement("CREATE INDEX tax_rules_conditions_gin ON tax_rules USING GIN (conditions)");
```

### 3.5 KYC — encrypted

```php
// partner_profiles
$t->string('tax_id_type', 16);                  // enum-backed in app
$t->text('tax_id_number');                      // cast 'encrypted:hashed'
$t->string('tax_id_hash', 64)->unique();        // for uniqueness / lookup
$t->string('legal_name');
$t->char('country_code', 2);
$t->string('state_code', 10)->nullable();
$t->text('bank_account_encrypted');             // cast 'encrypted'
```

Model:

```php
protected $casts = [
    'tax_id_type'             => TaxIdType::class,
    'tax_id_number'           => 'encrypted',
    'bank_account_encrypted'  => 'encrypted',
];
```

All monetary columns are `DECIMAL(14,4)` (four decimals support micro-currencies and pre-rounding intermediate calcs). Rates are `DECIMAL(5,2)` or `DECIMAL(6,3)` for tax rules.

---

## 4. Directory layout

```
app/
  Actions/
    Commission/ResolveCommissionRate.php
    Payout/{CalculatePayout,ProcessPayouts,VoidPayout,RecalculatePayouts}.php
    Invoicing/{GenerateInvoice,AllocateInvoiceNumber,GenerateInvoicePdf}.php
    Tax/{ApplyTaxRules,PreviewTax}.php
  Data/
    CommissionComponents.php · PayoutBreakdown.php · InvoiceData.php
    TaxLine.php · MoneyData.php
  Enums/
    UserRole.php · PayoutStatus.php · BusinessTypeSlug.php
    TaxIdType.php · TaxAppliesTo.php
  Exceptions/
    NoApplicableRate.php · OverlappingRate.php · PayoutImmutable.php
    InvoiceSequenceUnavailable.php
  Models/
    Tenant.php · User.php · PartnerProfile.php
    Policy.php · MotorDetails.php · NonMotorDetails.php · HealthDetails.php
    CommissionRate.php · Payout.php · Invoice.php · InvoiceIssuer.php · InvoiceSequence.php
    TaxRule.php · ExchangeRate.php
    Insurer.php · Customer.php
    (taxonomy models)
  Policies/
    PolicyPolicy.php · PayoutPolicy.php · CommissionRatePolicy.php
    DocumentPolicy.php (guards signed-URL generation)
  Services/
    Invoicing/InvoiceRenderer.php
    Tax/
      TaxCalculator.php                 # interface
      Calculators/
        IndiaTdsCalculator.php
        IndiaGstCalculator.php
        UsBackupWithholdingCalculator.php
        EuVatCalculator.php
      TaxCalculatorRegistry.php
    Media/SignedUrlIssuer.php
    Fx/ExchangeRateProvider.php
  States/
    Payout/{Pending,Calculated,Processed,Voided}.php
  Support/
    Money.php
  Tenancy/
    CurrentTenant.php
    TenantSwitcher.php
    TenancyBootstrappers/*           # custom queue / cache / filesystem bootstrappers
  Http/
    Controllers/Admin/* · Controllers/Partner/* · Controllers/Auth/*
    Middleware/InitializeTenancy.php · EnsureTenantRegion.php
    Requests/...
    Resources/...

resources/js/              # Inertia + Vue 3 + Vuetify  (see UX_DESIGN.md)

database/
  migrations/tenant/...              # run per-tenant (stancl/tenancy)
  migrations/landlord/...            # central tenant registry, users, roles
  seeders/...

tests/
  Feature/Commission/*
  Feature/Payout/{CalculatePayoutTest,ProcessPayoutsTest,VoidPayoutTest,ConcurrentPayoutTest}.php
  Feature/Invoice/{GenerateInvoiceTest,GaplessSequenceTest}.php
  Feature/Tax/{IndiaTdsTest,IndiaGstTest,UsBackupWithholdingTest,EuVatTest}.php
  Feature/Tenancy/{IsolationTest,QueueScopeTest}.php
  Feature/Media/SignedUrlTest.php
  Unit/Support/MoneyTest.php

config/
  mis.php · tenancy.php · permission.php · data.php
```

---

## 5. Core engine — Actions

### 5.1 `ResolveCommissionRate`

```php
final class ResolveCommissionRate
{
    use AsAction;

    public function handle(Policy $policy, ?CarbonInterface $asOf = null): ?CommissionRate
    {
        $asOf ??= $policy->policy_date;
        $attrFilters = $this->vehicleAttributeFiltersFor($policy);

        $base = CommissionRate::query()
            ->where('insurer_id',        $policy->insurer_id)
            ->where('business_type_id',  $policy->business_type_id)
            ->whereRaw('effective_range @> ?::date', [$asOf])
            ->where($attrFilters);

        // Partner override wins; else global fallback.
        return (clone $base)->where('partner_id', $policy->partner_id)->first()
            ?? (clone $base)->whereNull('partner_id')->first();
    }
}
```

- Uses Postgres `daterange @> date` containment operator.
- No `latest()` tiebreaker — overlap constraint (§3.1) guarantees at most one match per taxonomy key.

### 5.2 `CalculatePayout` — concurrency-safe, currency-explicit

```php
final class CalculatePayout
{
    use AsAction;

    public function __construct(
        private ResolveCommissionRate $resolver,
        private ApplyTaxRules         $taxes,
    ) {}

    public function handle(Policy $policy): Payout
    {
        return DB::transaction(function () use ($policy) {

            // Lock the policy row to serialize concurrent calculations for the same policy.
            $policy = Policy::whereKey($policy->id)->lockForUpdate()->firstOrFail();

            // Block recalc of a processed or voided payout — immutability.
            $existing = Payout::where('policy_id', $policy->id)
                ->whereIn('status', [PayoutStatus::Processed, PayoutStatus::Voided])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw PayoutImmutable::for($existing);
            }

            $rate = $this->resolver->handle($policy)
                ?? throw NoApplicableRate::for($policy);

            if ($rate->currency_code !== $policy->currency_code) {
                throw CurrencyMismatch::between($rate, $policy);
            }

            $components = match ($policy->business_type->slug) {
                BusinessTypeSlug::Motor => $this->motor($policy, $rate),
                default                 => $this->nonMotor($policy, $rate),
            };

            $taxResult = $this->taxes->handle(
                partner:    $policy->partner,
                components: $components,
                jurisdiction: $policy->partner->partnerProfile->country_code,
                asOf:       $policy->policy_date,
            );

            return Payout::updateOrCreate(
                ['policy_id' => $policy->id],
                [
                    'tenant_id'           => $policy->tenant_id,
                    'partner_id'          => $policy->partner_id,
                    'commission_rate_id'  => $rate->id,
                    'od_commission'       => $components->od,
                    'tp_commission'       => $components->tp,
                    'net_commission'      => $components->netCommission,
                    'flat_amount'         => $components->flat,
                    'total_commission'    => $components->total,
                    'net_po'              => $components->total->minus($taxResult->totalDeductions()),
                    'currency_code'       => $policy->currency_code,
                    'tax_lines'           => $taxResult->toArray(),
                    'status'              => PayoutStatus::Calculated,
                    'calculated_at'       => now(),
                    'breakdown'           => [
                        'rate_id'         => $rate->id,
                        'components'      => $components->toArray(),
                        'tax_result'      => $taxResult->toArray(),
                        'asOf'            => $policy->policy_date->toIso8601String(),
                    ],
                ],
            );
        });
    }
}
```

- `DB::transaction` + `lockForUpdate()` on the policy row prevents two concurrent workers producing divergent payouts for the same policy.
- Throws `PayoutImmutable` if attempting to recalculate a processed or voided payout — corrections go through `VoidPayout` + a new `CalculatePayout`.
- Throws `NoApplicableRate` (not silent zeros) when no rate applies.
- Asserts currency match between policy and rate — never silently converts.

### 5.3 Void and reverse — the correction flow

```php
final class VoidPayout
{
    use AsAction;

    public function handle(Payout $payout, User $actor, string $reason): Payout
    {
        return DB::transaction(function () use ($payout, $actor, $reason) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $payout->status->transitionTo(Voided::class, reason: $reason, actor: $actor);

            // Reversing entry preserves the running ledger sum: zero net effect, linked to original.
            return Payout::create([
                'tenant_id'           => $payout->tenant_id,
                'policy_id'           => $payout->policy_id,
                'partner_id'          => $payout->partner_id,
                'reversing_payout_id' => $payout->id,
                'total_commission'    => $payout->total_commission->negate(),
                'net_po'              => $payout->net_po->negate(),
                'currency_code'       => $payout->currency_code,
                'tax_lines'           => array_map(fn ($l) => [...$l, 'amount' => -$l['amount']], $payout->tax_lines),
                'breakdown'           => ['reversal_of' => $payout->id, 'reason' => $reason],
                'status'              => PayoutStatus::Processed,
                'calculated_at'       => now(),
                'processed_at'        => now(),
            ]);
        });
    }
}
```

### 5.4 Invoice generation — gapless numbering

```php
final class GenerateInvoice
{
    public function __construct(
        private AllocateInvoiceNumber $allocator,
        private GenerateInvoicePdf    $renderer,
        private TaxCalculatorRegistry $taxes,
    ) {}

    public function handle(Payout $payout): Invoice
    {
        return DB::transaction(function () use ($payout) {
            $issuer  = InvoiceIssuer::active();

            $invoiceTaxes = $this->taxes
                ->forJurisdiction($payout->partner->partnerProfile->jurisdictionKey())
                ->applicableTo(TaxAppliesTo::InvoiceTotal)
                ->reduce($payout->total_commission);

            $number = $this->allocator->handle(
                issuer:      $issuer,
                fiscalYear:  fiscal_year_for($payout->calculated_at),
            );   // SELECT ... FOR UPDATE inside the same transaction

            $invoice = Invoice::create([
                'tenant_id'  => $payout->tenant_id,
                'payout_id'  => $payout->id,
                'issuer_id'  => $issuer->id,
                'number'     => $number,
                'issued_at'  => now(),
                'currency_code' => $payout->currency_code,
                'line_items' => [/* derived */],
                'tax_lines'  => $invoiceTaxes->toArray(),
            ]);

            $this->renderer->renderToStorage($invoice);

            return $invoice;
        });
    }
}
```

Number format is per-issuer configurable (e.g. `INV/2025-26/000001`). Fiscal year boundary (April–March for India, Jan–Dec for US) is driven by `Tenant::fiscal_year_start_month`.

### 5.5 Tax engine — Strategy pattern

Contract:

```php
interface TaxCalculator
{
    public function supports(TaxRule $rule): bool;

    /**
     * Returns the tax lines that $rule produces on $base (a Money amount),
     * given the partner and the as-of date.
     */
    public function calculate(
        TaxRule  $rule,
        Money    $base,
        User     $partner,
        CarbonInterface $asOf,
    ): Collection;   // of TaxLine
}
```

Built-in calculators:

| Calculator | Handles |
|---|---|
| `IndiaTdsCalculator` | Sec 194D TDS on commission (individual / HUF / other partner types, threshold aware) |
| `IndiaGstCalculator` | CGST+SGST for intra-state, IGST for inter-state on invoice totals |
| `UsBackupWithholdingCalculator` | 24 % backup withholding when partner has no valid W-9 |
| `EuVatCalculator` | Reverse charge vs forward VAT based on partner VAT id validity |
| `AustraliaGstCalculator` | 10 % GST on invoice totals for registered partners |

Registry resolves calculator by `TaxRule::calculator` slug; new jurisdictions = new calculator class + one seeder row, no core changes.

`ApplyTaxRules` queries `tax_rules` filtered by `(tenant, jurisdiction, applies_to, effective_range @> asOf)` and runs each matching rule's calculator, collecting `TaxLine`s. Each tax line is durably attached to the `payouts.tax_lines` jsonb column, so the exact rule id, rate, base, and amount are auditable forever.

---

## 6. Admin surface — Inertia + Vue 3 + Vuetify 3

See [UX_DESIGN.md](UX_DESIGN.md) for the full UI specification. Enterprise additions:

- **Tenant chip** in the app bar beside the env chip — never leave doubt about which tenant is being acted on.
- **Currency in every monetary display** (`₹12,345.00`, `$456.78`). Formatting honors the tenant's `default_locale`; explicit override per record when `currency_code` differs.
- **Rate overlap errors** bubble from the DB `EXCLUDE` constraint into a field-level error on `effective_range` with a link to the conflicting rate.
- **Void flow** UI: Payout detail has "Void" action (admin/auditor) requiring reason; the reversing payout appears in the partner's ledger with status `Processed` and type "Reversal".
- **Tax rule admin** (`Admin/TaxRules/*.vue`) — jurisdiction-filtered list, JSON-aware editor for `conditions` and `tiers`, calculator selector, effective-range picker.
- **Invoice preview** shows the allocated number *before* commit via a dry-run endpoint (without incrementing the sequence).

## 7. Partner portal

- Partner sees their own payouts including voids + reversals; totals reconcile at the bottom.
- Documents (policy attachments, KYC) are never served directly — `DocumentPolicy` authorizes, `SignedUrlIssuer` returns a 5-minute signed S3 URL.
- Encrypted KYC fields surface only masked (`****` + last 4) in the UI; decrypt+reveal requires a policy check and is activity-logged.

---

## 8. Frontend architecture

Unchanged from §1: Inertia + Vue 3 + Vuetify 3 + Vite + TypeScript. Two additions:

- **Tenant config** flows via Inertia shared props (`currency`, `locale`, `tax_regime`, `fiscal_year_start_month`); composables consume it (`useMoney`, `useDate`).
- **Ziggy routes** are tenant-aware (domain stub filled by the runtime tenant), so `route('admin.payouts.index')` works across tenants.

---

## 9. Authentication & authorization

- Breeze + Fortify for auth flows; 2FA mandatory for `admin`/`auditor` in production, optional for `partner`.
- spatie/laravel-permission roles, policies enforced via `authorizeResource()` and Filament-style `can()` checks in Vue.
- **Queue-safe global scopes.** Data isolation for partners uses a global scope that explicitly guards against background contexts:

```php
protected static function booted(): void
{
    static::addGlobalScope('partner', function (Builder $q) {
        if (! auth()->hasUser()) return;          // queue / scheduler / console
        if (auth()->user()->hasRole(UserRole::Partner)) {
            $q->where('partner_id', auth()->id());
        }
    });
}
```

`auth()->hasUser()` is the correct guard — `auth()->check()` can return false for a guest *and* for a worker, but `hasUser()` returns false only when no user has been resolved at all, which is exactly when the scope must no-op. Admin controllers use `withoutGlobalScope('partner')` behind policy authorization.

- **Tenancy middleware** (`InitializeTenancy`) resolves tenant from domain → connects per-tenant DB. `EnsureTenantRegion` blocks cross-region access for residency-locked tenants.

---

## 10. Config

`config/mis.php`:

```php
return [
    'fiscal_year_start_month' => 4,      // per-tenant override in DB
    'default_pagination'      => 25,
    'invoice' => [
        'driver'              => 'dompdf',
        'storage_disk'        => 's3-private-invoices',
    ],
    'documents' => [
        'storage_disk'        => 's3-private-documents',
        'signed_url_ttl'      => 300,    // 5 min
    ],
    'fx' => [
        'provider'            => 'ecb',  // nightly sync
        'reporting_base'      => env('FX_REPORTING_BASE', 'USD'),
    ],
    'tax' => [
        'strategies' => [
            'india_tds_194D'           => \App\Services\Tax\Calculators\IndiaTdsCalculator::class,
            'india_gst'                => \App\Services\Tax\Calculators\IndiaGstCalculator::class,
            'us_backup_withholding'    => \App\Services\Tax\Calculators\UsBackupWithholdingCalculator::class,
            'eu_vat'                   => \App\Services\Tax\Calculators\EuVatCalculator::class,
            'australia_gst'            => \App\Services\Tax\Calculators\AustraliaGstCalculator::class,
        ],
    ],
];
```

Runtime values (`InvoiceIssuer`, `TaxRule`, `CommissionRate`, partner `TaxConfig`) live in DB. Nothing tax- or currency-material in code constants.

---

## 11. Testing

Pest 3 with a Postgres test database (matches production semantics for `daterange`, `EXCLUDE`, jsonb, triggers).

```
tests/Feature/Commission/
  ResolveCommissionRateTest.php      — partner override, global fallback, date window containment, no-match → null
  OverlapConstraintTest.php           — creating an overlapping range throws a constraint violation

tests/Feature/Payout/
  CalculatePayoutTest.php             — motor OD/TP/flat, non-motor net%, currency mismatch throws
  ConcurrentPayoutTest.php            — two parallel CalculatePayout calls for the same policy: one wins, other sees serialized outcome
  VoidPayoutTest.php                  — void creates reversing row; sum of ledger = 0 for voided
  ImmutableProcessedTest.php          — update to processed row raises DB trigger

tests/Feature/Invoice/
  GaplessSequenceTest.php             — 1000 concurrent invoice creations yield 1..1000 with no gap/duplicate
  SequenceRollbackTest.php            — failed Invoice::create inside transaction releases the number

tests/Feature/Tax/
  IndiaTdsTest.php                    — 5% / 10% / threshold exemption
  IndiaGstTest.php                    — intra-state CGST+SGST, inter-state IGST
  UsBackupWithholdingTest.php         — W-9 missing → 24% withholding
  EuVatTest.php                       — valid VAT id → reverse charge; missing id → forward VAT

tests/Feature/Tenancy/
  IsolationTest.php                   — queries on tenant A cannot read tenant B's rows (by design of stancl)
  QueueScopeTest.php                  — queued job acting on a partner's payout is not over-filtered by the partner scope
  ResidencyTest.php                   — request hitting a tenant from a disallowed region is blocked

tests/Feature/Media/
  SignedUrlTest.php                   — policy gates signed-URL issuance; unauthorized user → 403

tests/Unit/Support/
  MoneyTest.php                       — rounding, percentage, currency safety
```

Coverage target: ≥ 95 % on `app/Actions`, `app/Services/Tax`, `app/Support`; ≥ 80 % overall.

---

## 12. Dev infra & CI

```
docker-compose.yml    # app, postgres:16, redis, mailpit, minio
Dockerfile            # php:8.3 + ext-pgsql, ext-redis, ext-gd, composer, node 20
.env.example
Makefile              # make up / test / fresh / lint / build / tenant:migrate
```

GitHub Actions:

1. `composer install --prefer-dist`
2. `npm ci && npm run build`
3. `vendor/bin/pint --test`
4. `vendor/bin/phpstan analyse --memory-limit=2G`
5. `vendor/bin/pest --parallel --ci`  (service container: postgres:16)

---

## 13. Enterprise readiness — tenancy, residency, compliance

### 13.1 Multi-tenancy (data residency)

- **stancl/tenancy** in database-per-tenant mode. A central "landlord" DB holds `tenants`, `domains`, super_admin users, global configuration. Each tenant has its own Postgres database (or schema, configurable).
- **Regional hosting.** A tenant's `region` column (e.g. `in-mumbai`, `eu-frankfurt`, `us-east-1`) pins its database to a regional cluster. Jobs for that tenant run on workers in the same region.
- **Residency enforcement.** `EnsureTenantRegion` middleware blocks API access from IPs outside a tenant's approved regions (optional per-tenant flag for strict residency: GDPR / DPDP / LGPD).
- **Cross-tenant analytics** (for the operating company, not tenants) happen via a separate "warehouse" Postgres replica with anonymized/aggregated views — no direct raw-data cross-tenant joins at app runtime.

### 13.2 Secure PII & document storage

- `spatie/laravel-medialibrary` configured on the `s3-private-documents` disk per tenant. Buckets are regional, private, SSE-KMS encrypted with per-tenant KMS keys.
- Document serving: controller → `DocumentPolicy::view()` → `SignedUrlIssuer::for($media, ttl: 300)` → 302 redirect to signed URL. The app never proxies the bytes.
- KYC fields (`tax_id_number`, `bank_account_encrypted`) use Laravel `encrypted:hashed`; the encryption key is a per-tenant KMS-wrapped data key.

### 13.3 Append-only financial ledgers

- `payouts` and `invoices` have no `softDeletes`.
- Once `status=processed`, monetary columns are immutable (enforced by DB trigger in §3.2).
- Corrections issue a new payout with `reversing_payout_id` pointing at the original; ledger arithmetic is preserved.
- `activity_log` captures every mutation (including voids, invoice issuance, rate changes) with actor, IP, timestamp, diff.

### 13.4 Compliance surface

| Regime | What we handle |
|---|---|
| India — GST / TDS | Tax rules + gapless invoice numbering + state-code-driven CGST/SGST vs IGST |
| EU — GDPR | Data residency per-tenant, right-to-erasure as a tenant-scoped workflow, DPA logs |
| India — DPDP | Residency + breach-response workflow + consent records (partner sign-up flow) |
| US — 1099 | Backup withholding, W-9 capture, year-end 1099 export |
| Brazil — LGPD | Residency + data subject requests via activity log export |
| Global — PCI | We do not store card data. Payment integrations tokenize via gateway. |
| Global — SOC 2-ready | Immutable ledger + activity log + per-role policies + MFA + encryption at rest |

### 13.5 Multi-currency

- Every monetary record carries `currency_code` (ISO 4217).
- The ledger never converts. Reporting views convert at dashboard read time using `exchange_rates` (nightly sync from ECB / central bank feeds) into the tenant's reporting base currency.
- A policy and its matching rate must share `currency_code` or `CalculatePayout` throws `CurrencyMismatch` — no silent cross-currency math.

---

## 14. Build order (suggested)

**Milestone 1 — foundation**
Laravel 13, Breeze (Inertia + Vue 3), Vuetify 3, Pint, PHPStan, Pest, CI with Postgres 16.
stancl/tenancy wired (landlord + single example tenant).
Core migrations: users, roles, insurers, taxonomy, policies, commission_rates (with EXCLUDE constraint), payouts (with immutability trigger), tax_rules, invoice_sequences, invoice_issuers.
`Money`, `TaxLine` DTOs + full Pest coverage for `Money`.

**Milestone 2 — commission + payout + tax engine**
`ResolveCommissionRate`, `CalculatePayout` (with `lockForUpdate`), `VoidPayout`, `ProcessPayouts`.
`TaxCalculator` interface + India TDS/GST calculators + `ApplyTaxRules`.
`GenerateInvoice` + `AllocateInvoiceNumber` (gapless sequence).
Full test suite including concurrency and gapless sequence stress tests.

**Milestone 3 — admin panel**
Inertia + Vuetify shells (`AdminLayout`, `DataTable`, `Filters`, `BulkActionBar`).
Admin pages: Partners, Policies, Payouts (with void action), CommissionRates, Insurers, Taxonomy, InvoiceIssuer, TaxRules, Settings, Dashboard.

**Milestone 4 — partner portal**
Partner pages (dashboard, policies, payouts showing voids+reversals, reports, profile). Queue-safe global scopes. Document signed-URL flow.

**Milestone 5 — additional tax regimes + reporting**
US backup withholding, EU VAT, Australia GST calculators. XLSX exports. Year-end reports (1099, India Form 26AS support). Activity log viewer. FX rate sync job.

**Milestone 6 — hardening**
2FA mandatory for admin/auditor, per-tenant KMS keys, residency enforcement middleware, penetration test, SOC 2 readiness review.

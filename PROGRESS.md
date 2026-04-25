# SettleOS — Build Progress

Tracks the implementation against [Plan.md](Plan.md). Update as milestones land.

## Status snapshot

| Milestone | Status | Notes |
|---|---|---|
| M1 — Foundation | ✅ Done | Laravel 13 + Breeze + tenancy + central/tenant migrations + enums + DTOs |
| M2 — Commission/Payout/Tax/Invoicing engine | ✅ Done | India TDS + GST calculators; gapless invoice numbering; void/reverse flow |
| M3 — Admin panel | ⏳ Next | Spatie permission + controllers + Inertia pages backed by real data |
| M4 — Partner portal | Not started | |
| M5 — Additional tax regimes + reporting | Not started | US 1099, EU VAT, AU GST; XLSX exports; FX sync; YTD-aggregated TDS |
| M6 — Hardening | Not started | 2FA, KMS, residency middleware, pen test |

---

## M2 — what shipped

### Models (`app/Models/`)
PartnerProfile · Insurer · Customer · TaxonomyTerm · CommissionRate · Policy · MotorDetails · NonMotorDetails · HealthDetails · TaxRule · InvoiceIssuer · InvoiceSequence · Invoice · Payout · ExchangeRate.

KYC fields use the `encrypted` cast; soft-deletes on the entities the plan calls for; `Payout` and `CommissionRate` deliberately have no `softDeletes` (append-only ledger).

### Engine (`app/Actions/`, `app/Services/Tax/`)
- `Commission/ResolveCommissionRate` — partner override beats global; daterange-aware via Postgres `effective_range @> ?::date`. Relies on the EXCLUDE constraint to guarantee at most one match per dims_key.
- `Payout/CalculatePayout` — `lockForUpdate()` on the policy row, refuses to recalc Processed/Voided payouts, asserts currency match between rate and policy, computes OD/TP for motor and net% for non-motor/health, persists `tax_lines` + full `breakdown` jsonb. Fetches the active issuer to drive intra/inter-state GST classification.
- `Payout/VoidPayout` — Calculated → `voided` in place; **Processed → INSERT a reversing payout** with `reversing_payout_id` + negated amounts (the DB trigger blocks any UPDATE on Processed rows).
- `Payout/ProcessPayouts` — bulk Calculated → Processed with `processed_at` stamp.
- `Tax/ApplyTaxRules` — queries active rules by jurisdiction + applies_to + effective_on, dispatches each to its calculator via the registry. Binds the `IndiaGstCalculator` with the active issuer when one is provided.
- `Invoicing/AllocateInvoiceNumber` — `SELECT ... FOR UPDATE` on `invoice_sequences`, increments `next_value` inside the same transaction. Rollback releases the reservation → gapless under normal operation.
- `Invoicing/GenerateInvoice` — picks the active issuer, computes GST against the payout subtotal, allocates a number, persists the invoice row with `line_items` + `tax_lines`. PDF rendering deferred to M3+.

### Tax engine (`app/Services/Tax/`)
- `TaxCalculator` interface + `TaxCalculatorRegistry` (config-driven, in `mis.tax.strategies`).
- `Calculators/IndiaTdsCalculator` — Section 194D, business-type-conditional; threshold treated as a per-payout floor pending YTD aggregation in M5.
- `Calculators/IndiaGstCalculator` — issuer-injected; intra-state rule fires CGST/SGST when partner state == issuer state, inter-state fires IGST. Skips non-GST-registered partners.

### Exceptions (`app/Exceptions/`)
`NoApplicableRate`, `PayoutImmutable`, `CurrencyMismatch`, `InvalidPayoutTransition`.

### Demo command
`php artisan app:demo-data [--fresh]` — seeds central admin + 2 partner users, a single tenant `acme` (domain `acme.localhost`), and runs the engine end-to-end:
- 8 policies (4 motor + 2 non-motor + 2 health), 2 partners, 5 customers
- Per policy: `CalculatePayout` → `tax_lines` + `breakdown` populated
- `ProcessPayouts` flips the first 2 payouts to Processed
- `GenerateInvoice` issues `INV/2026-27/000001` for one Processed payout
- Anita (KA, individual, GST-registered) → 3 tax lines (TDS 5% + CGST 9% + SGST 9%)
- Ravi (MH, private_ltd, not GST-registered) → 1 tax line (TDS 10%)

### Tests
`php artisan test --compact tests/Unit` — **19 passing** across:
- `tests/Unit/Support/MoneyTest.php` — Money arithmetic, percentage rounding, currency safety.
- `tests/Unit/Tax/IndiaTdsCalculatorTest.php` — supports(), business-type filtering, threshold, half-up rounding.
- `tests/Unit/Tax/IndiaGstCalculatorTest.php` — GST-registered guard, intra/inter-state classification, missing issuer fallback.

### Pre-existing test failures (NOT introduced by M2)
`tests/Feature/ExampleTest.php` and `tests/Feature/ProfileTest.php` — 6 stock Breeze tests broken by route customization in commit 318e19c. Out of scope; revisit when M3 wires real auth flows.

### Caveats / known divergences from Plan.md
- **Void/reverse**: the migration's payout trigger blocks **any** UPDATE on Processed rows (not just financial columns), so the plan's "transitionTo(Voided::class) on the original" can't run as written. `VoidPayout` instead inserts a reversing entry referencing the original. Sum of original + reversal = 0.
- **TDS threshold**: demo seeds the 194D threshold as `0` so deductions are visible. Real ₹15,000 YTD aggregation lives in M5 reporting.
- **Effective ranges**: commission_rates and tax_rules default `effective_range` to `[CURRENT_DATE, infinity)`. Demo overrides to `[today - 1 year, infinity)` so backfilled policies match.
- **Commission rate FK**: `commission_rates.partner_id` references `partner_profiles.id`, not `users.id` as Plan §3.1 sketches. The schema is authoritative.
- **`TaxRule.applies_to`**: enum values are `gross_commission | net_commission | premium`. Demo + engine use `net_commission`; the migration comment mentioning `total_commission` is a doc drift.

---

## M3 — what's next

### Recommendation when picking back up
Start with **spatie/permission wiring** as the foundation (smaller, standalone), then build the first vertical slice (Partners list/show + Payouts with Process/Void wired) before the rest of the admin pages.

### Concrete checklist
- [ ] `composer require spatie/laravel-permission` already in composer.json — publish migrations + config (`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`).
- [ ] Run permission migrations on the **central** DB (super_admin/admin span tenants).
- [ ] Roles seeder: `super_admin`, `admin`, `partner`, `auditor`. Assign in `PopulateDemoData`.
- [ ] `User` model: `use HasRoles;`. Decide central-vs-tenant connection for the trait's queries.
- [ ] Role-aware route middleware: `admin.*` → admin/super_admin; `partner.*` → partner. Replace the Inertia closures in `routes/web.php`.
- [ ] Inertia shared props: expose `auth.user.role` so the AdminLayout role chip stops hardcoding "Admin".
- [ ] Vue page files do **not** exist yet for `Admin/Partners/Index`, `Admin/Policies/Index`, `Admin/Payouts/Index`, etc. — only `Admin/Dashboard.vue` and `Partner/Dashboard.vue` are written. Each needs a real Vue file alongside its controller.

### First M3 slice (suggested order)
1. **Admin Dashboard** — replace closure with `Admin\DashboardController`; pull real KPIs (policies this month, sum of `total_commission`, count of Calculated payouts, count of active partners).
2. **Admin Partners** — `PartnersController@index/show` + `Admin/Partners/Index.vue` data table.
3. **Admin Payouts** — `PayoutsController@index/show/process/void` + `Admin/Payouts/Index.vue` with status filter and bulk Process / per-row Void buttons.
4. **Admin Policies** — `PoliciesController@index/show` + `Admin/Policies/Index.vue`.
5. (Defer) CommissionRates editor (`EXCLUDE` constraint as field error), Insurers/Taxonomy CRUD, full Partners create/update form.

### Things to watch out for
- The `partner_profiles.user_id` is a cross-DB pointer (central users ↔ tenant partner_profiles, no FK). Any list/show that needs partner display name should use `partner_profiles.display_name` directly rather than joining users.
- `CalculatePayout` currently fetches the active issuer with `InvoiceIssuer::query()->active()->first()`. If a tenant later supports multiple issuers, this lookup needs partner segmentation logic.
- Concurrency tests (per Plan §11 `ConcurrentPayoutTest`, `GaplessSequenceTest`) should land before the partner portal — easier to run against the engine alone than once UI is layered on.

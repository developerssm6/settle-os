# SettleOS — Project Plan & Estimates

Execution plan for the SettleOS blueprint in [MODERN_DESIGN.md](MODERN_DESIGN.md). Milestone-level estimates, work breakdown, dependency graph, critical path, risks, and rough calendar.

---

## 1. Assumptions

| Dimension | Value |
|---|---|
| Team | 2 backend (Laravel/PHP), 2 frontend (Vue 3 / Vuetify / TS), 0.5 DevOps, 0.5 QA |
| Seniority | Mid-to-senior; comfortable with the stack; new to this codebase |
| Working week | 5 days × ~4.5 productive engineering hours |
| Estimation unit | **Engineer-weeks** (EW). A 1-EW task = 1 engineer × 1 working week |
| Overhead included | Code review, tests, docs, refinement, ~15 % buffer rolled into estimates |
| Overhead **not** included | Hiring, onboarding, vendor procurement (KMS, S3 regions), product discovery sessions |
| Environments | Dev → Staging → Prod. Staging spun up end of M1, Prod cutover after M4 |
| Out of scope | Legacy data migration, mobile apps, customer portal (non-partner), real-time notifications |

**Calendar math.** With 2 BE + 2 FE working in parallel, 4 EW of same-type work ≈ 1 calendar week if fully parallelizable. Most milestones are *not* fully parallelizable — dependencies and shared components serialize chunks. Each milestone calendar estimate below reflects realistic concurrency.

---

## 2. Estimate summary

| # | Milestone | Backend (EW) | Frontend (EW) | DevOps (EW) | QA (EW) | Total (EW) | Calendar |
|---|---|---:|---:|---:|---:|---:|---|
| M1 | Foundation | 8 | 4 | 3 | 1 | **16** | 6 weeks |
| M2 | Commission + payout + tax engine | 10 | 1 | 0.5 | 2 | **13.5** | 6 weeks |
| M3 | Admin panel | 6 | 14 | 0.5 | 2 | **22.5** | 9 weeks |
| M4 | Partner portal | 2 | 6 | 0.5 | 1 | **9.5** | 4 weeks |
| M5 | Additional tax regimes + reporting | 7 | 4 | 1 | 2 | **14** | 6 weeks |
| M6 | Hardening | 3 | 1 | 3 | 2 | **9** | 4 weeks |
| | **Total engineering** | **36** | **30** | **8.5** | **10** | **84.5 EW** | **~35 weeks** |
| | Pre-project (discovery, design alignment, env procurement) | — | — | — | — | — | +3 weeks |
| | Contingency buffer (20 %) | — | — | — | — | — | +7 weeks |
| | **Calendar total** | | | | | | **~45 weeks (~10.5 months)** |

Cross-check: 84.5 EW ÷ 5 engineers ≈ 17 engineer-weeks per person over 35 working weeks = fits a team of 5 comfortably with ~50 % utilization headroom for review, context-switching, meetings.

---

## 3. M1 — Foundation (6 calendar weeks · 16 EW)

**Goal.** Greenfield Laravel 13 + Postgres 16 + Inertia/Vue 3/Vuetify skeleton with tenancy, CI, test harness, and the schema + primitives that later milestones depend on.

### 3.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | Repo scaffold: Laravel 13 + Breeze (Inertia + Vue 3) + Pint + PHPStan + Pest | 0.5 | 0.5 | — | — | — | Baseline `composer create-project`, npm install, Breeze install |
| 2 | Vuetify 3 + theme + layout shells (`AdminLayout`, `PartnerLayout`, `AuthLayout`) | — | 1.5 | — | — | 1 | Tokens from [UX_DESIGN.md](UX_DESIGN.md) §4 |
| 3 | Docker Compose: app, postgres, redis, mailpit, minio; Makefile | — | — | 1 | — | — | Stack from §12 |
| 4 | GitHub Actions pipeline (Pint → PHPStan → Pest w/ Postgres service → Vite build) | 0.5 | — | 0.5 | — | 1, 3 | Matrix on PHP 8.3 |
| 5 | stancl/tenancy wiring: landlord + tenant connections, bootstrappers, domain resolution | 2 | — | 0.5 | — | 1 | Includes custom queue/cache bootstrapper |
| 6 | Central migrations: tenants, domains, users, roles, super_admin; RoleSeeder | 0.5 | — | — | — | 5 | Landlord DB |
| 7 | Tenant migrations (part 1): insurers, taxonomy tables, partner_profiles (encrypted KYC) | 1 | — | — | — | 5 | Encrypted casts + `tax_id_hash` lookup |
| 8 | Tenant migrations (part 2): policies, motor/non-motor/health details, customers | 1 | — | — | — | 7 | Polymorphic morphs |
| 9 | Tenant migrations (part 3): commission_rates with `daterange` + `EXCLUDE` constraint | 1 | — | — | — | 7 | Raw SQL for EXCLUDE |
| 10 | Tenant migrations (part 4): payouts + immutability trigger + reversing_payout_id | 1 | — | — | — | 7 | Postgres `plpgsql` trigger |
| 11 | Tenant migrations (part 5): tax_rules, invoice_issuers, invoice_sequences, invoices, exchange_rates | 0.5 | — | — | — | 7 | |
| 12 | `Money` support class + brick/money integration + extensive Pest coverage | 1 | — | — | — | 1 | Currency-aware, no floats, rounding modes |
| 13 | Core DTOs (spatie/laravel-data): `MoneyData`, `TaxLine`, `CommissionComponents`, `PayoutBreakdown` | 0.5 | — | — | — | 12 | |
| 14 | Eloquent models + relationships + enums (`UserRole`, `PayoutStatus`, `TaxIdType`, `BusinessTypeSlug`) | 0.5 | — | — | — | 6–11 | Casts, traits, scopes |
| 15 | Queue-safe `partner` global scope + tests | 0.5 | — | — | — | 14 | `auth()->hasUser()` guard (§9) |
| 16 | Demo tenant seeder + one `InvoiceIssuer` + baseline taxonomy + sample admin user | 0.5 | — | — | 0.5 | 14 | Local boot-able in < 1 min |
| 17 | Smoke tests: tenancy isolation, encrypted casts, overlap constraint violation | 0.5 | — | — | 0.5 | 5, 9 | Negative tests that the constraint actually refuses |
| 18 | Frontend: Ziggy + TypeScript + spatie/typescript-transformer pipeline + dark/light theme | — | 1 | — | — | 2 | |
| 19 | Frontend: `DataTable.vue`, `Filters.vue`, `PageHeader.vue`, `MoneyField.vue`, `PercentField.vue` — primitives | — | 1 | — | — | 2 | Full stories in [UX_DESIGN.md](UX_DESIGN.md) §6 |
| 20 | Staging env provisioned (Postgres 16, Redis, minio, OIDC for team access) | — | — | 1 | — | 3 | |
| **Totals** | | **8** | **4** | **3** | **1** | | |

### 3.2 Definition of Done

- `make up` → green CI → login page, admin login works on a seeded demo tenant.
- Overlap constraint violation produces a readable exception surfaced in tests.
- Immutability trigger blocks UPDATEs on a processed payout row.
- `Money` class has > 95 % test coverage.
- Staging deployed; rolling deploys work; CI gates required for merge to `main`.

### 3.3 Risks & mitigations

- **Tenancy bootstrapper edge cases** (queue workers, scheduled jobs) → budget half a BE week to harden + targeted tests (Task 15, 17).
- **DB trigger / EXCLUDE portability** — these are Postgres-specific; team must accept they cannot fall back to MySQL. Verify CI Postgres version matches prod from day one.

---

## 4. M2 — Commission + payout + tax engine (6 calendar weeks · 13.5 EW)

**Goal.** The financial core: rate resolution, concurrent payout calculation, void/reverse flow, invoice generation with gapless numbering, and a pluggable tax engine with India TDS + GST calculators.

### 4.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | `ResolveCommissionRate` action + vehicle-attribute dispatch table | 1 | — | — | — | M1 | Postgres `daterange @>` queries |
| 2 | Pest suite: partner override, global fallback, date window, no-match | 0.5 | — | — | 0.5 | 1 | |
| 3 | `CalculatePayout` action: transaction, `lockForUpdate`, currency guard, component math | 2 | — | — | — | 1 | Motor + non-motor branches |
| 4 | `PayoutImmutable`, `NoApplicableRate`, `CurrencyMismatch` exceptions + error handler wiring | 0.25 | — | — | — | 3 | |
| 5 | Concurrency Pest test (parallel process harness) | 0.5 | — | — | 0.5 | 3 | Uses Pest's `pcntl`-based parallel helper |
| 6 | `VoidPayout` action with reversing entry; state machine transitions | 1 | — | — | 0.25 | 3 | `spatie/laravel-model-states` setup |
| 7 | Immutability-trigger tests (attempt update → constraint error) | 0.25 | — | — | 0.25 | 3 | |
| 8 | Tax engine: `TaxCalculator` interface + `TaxCalculatorRegistry` + `ApplyTaxRules` action | 1 | — | — | — | M1 | Strategy pattern, config-driven |
| 9 | `IndiaTdsCalculator` (individual/HUF/other, thresholds) + tests | 1 | — | — | 0.25 | 8 | |
| 10 | `IndiaGstCalculator` (intra/inter-state CGST/SGST/IGST) + tests | 1 | — | — | 0.25 | 8 | |
| 11 | `AllocateInvoiceNumber` with `SELECT ... FOR UPDATE` + gapless sequence + rollback tests | 1 | — | — | 0.25 | M1 | 1000-concurrent stress test |
| 12 | `GenerateInvoice` action (wires taxes + number + persistence) + `GenerateInvoicePdf` | 1 | — | — | — | 10, 11 | Blade template for invoice |
| 13 | Invoice blade template + dompdf config (logo, issuer from DB, line items, taxes) | 0.5 | — | — | — | 12 | |
| 14 | `ProcessPayouts` + `RecalculatePayouts` queued job with chunking | 0.5 | — | — | 0.25 | 6 | Tenant-aware job payloads |
| 15 | Integration E2E test: seed policy → calculate → process → invoice → void → reverse | — | — | — | 1 | 1–14 | The "happy path" + "correction path" |
| 16 | Developer UI: a temporary console command to dry-run calculations on seeded data | 0.5 | — | — | — | 3 | Useful for M3 UI work kickoff |
| 17 | Vue `PayoutBreakdown.vue` component consuming the JSON schema | — | 1 | — | — | 3 | Stubbed data until M3 wires endpoints |
| 18 | CI: add `--parallel` for Pest; ensure Postgres service reuses between tests | — | — | 0.5 | — | 5 | Keeps CI time under 10 min |
| **Totals** | | **10** | **1** | **0.5** | **2** | | |

### 4.2 Definition of Done

- End-to-end demo via `php artisan mis:demo-calc <policy>`: resolves rate, calculates payout, applies India TDS + GST, generates invoice with a real sequence number, voids and reverses.
- `ConcurrentPayoutTest` green: 20 parallel `CalculatePayout` calls on one policy yield exactly one payout row.
- `GaplessSequenceTest` green: 1000 interleaved `AllocateInvoiceNumber` calls produce 1..1000 with no gaps or duplicates.
- India TDS + GST pass their scenario matrices (see §11 of design doc).
- `PayoutBreakdown.vue` renders a seeded breakdown visually (standalone preview page).

### 4.3 Risks & mitigations

- **Concurrency test flakiness** — Postgres serializable retry behavior can produce spurious failures. Mitigation: use `REPEATABLE READ` + explicit retry-on-deadlock pattern inside `CalculatePayout`, or `FOR UPDATE` on the policy row first (current plan).
- **Gapless vs performance** — `SELECT FOR UPDATE` on the sequence row serializes invoice creation. Fine for MIS volumes (<10k invoices/day per tenant); flag if projected scale rises.
- **Tax rule expressiveness** — `conditions` jsonb may not cover all scenarios. Mitigation: build India calculators first; revisit interface before M5 with learnings.

---

## 5. M3 — Admin panel (9 calendar weeks · 22.5 EW)

**Goal.** Full admin SPA surface: dashboard, CRUD for every resource, payouts list with bulk actions, commission rate editor with conflict detection, policy wizard with live payout preview, tax rule admin.

### 5.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | Shared: `DataTable`, `Filters`, `BulkActionBar`, `StatusChip`, `ConfirmDialog`, `ExportButton` (polish M1 primitives) | — | 2 | — | — | M1 | Backed by real endpoints now |
| 2 | Admin controllers + policies + Form Requests + API Resources for each domain object | 3 | — | — | — | M2 | Thin wrappers over Actions |
| 3 | `spatie/laravel-query-builder` wiring for server-side sort/filter/paginate | 1 | — | — | — | 2 | |
| 4 | Dashboard page: KPI cards, sparklines, recent activity feed | 0.25 | 1 | — | — | 2 | Queries for tenant scope |
| 5 | Partners: list, create, edit, show (with tabs: profile, commission overrides, tax config, documents, activity) | 0.5 | 2 | — | 0.25 | 2 | Encrypted KYC reveal flow (policy-gated) |
| 6 | Policies: list with filters | 0.25 | 1 | — | 0.25 | 2 | |
| 7 | Policies: 4-step create wizard with live `CalculatePayout` preview | 0.5 | 2 | — | 0.25 | 2, M2 | Preview action added to M2 API |
| 8 | Policies: edit + show + archive | 0.25 | 1 | — | — | 7 | |
| 9 | Payouts: list with breakdown expansion + bulk actions (calculate / recalculate / process / void / export) | 0.5 | 1.5 | — | 0.5 | 2 | `BulkActionBar` with total-₹ preview |
| 10 | Payouts: detail page with breakdown timeline + invoice download | 0.25 | 0.5 | — | — | 9 | |
| 11 | Commission rates: unified list (Global / Partner overrides / All toggle) | 0.25 | 1 | — | — | 2 | `effective_range` pretty display |
| 12 | Commission rate form: conditional fields, effective range picker, surfaces `EXCLUDE` conflict with link to conflicting row | 0.5 | 1 | — | 0.25 | 2 | |
| 13 | Rate-change side-effect: "N payouts affected — recalculate?" banner → queued recalc | 0.25 | 0.25 | — | — | 12 | |
| 14 | Insurers + Taxonomy (12 CRUD resources) — generate via a shared resource template | 0.25 | 1.5 | — | — | 1 | Each ~1/2 day FE once template done |
| 15 | Invoice issuer: single-record edit, logo upload, active flag | 0.25 | 0.5 | — | — | 2 | |
| 16 | Tax rules admin: list, create/edit form with jurisdiction picker, JSON-aware conditions editor, calculator selector | 0.5 | 1 | — | 0.25 | 2, M2 | Schema-aware JSON editor (monaco or vuetify native) |
| 17 | Settings: counts dashboard, users & roles admin, activity log viewer (paged) | 0.25 | 0.5 | — | — | 2 | |
| 18 | Invoice preview dry-run endpoint + UI (no sequence consumption) | 0.25 | 0.25 | — | — | 10 | |
| 19 | Keyboard shortcuts ( `/` focus search, `n` new, `Esc` close dialog ) + command palette (`⌘K`) | — | 0.75 | — | — | 1 | UX §1 principle 4 |
| 20 | Admin-role policy gating review + audit | 0.25 | — | — | 0.25 | all | Spot check every route |
| 21 | Staging deploy with multi-tenant demo dataset | — | — | 0.5 | 0.25 | 20 | |
| 22 | Accessibility pass: axe-core CI check + remediation | — | 0.5 | — | 0.25 | all FE | WCAG AA |
| **Totals** | | **6** | **14** | **0.5** | **2** | | |

### 5.2 Definition of Done

- Admin can: add a partner with KYC, create a commission rate, create a policy, see payout preview, process payouts, download an invoice — all via UI.
- Commission rate overlap constraint surfaces as field-level error with link to the conflicting row.
- Payout list sums to the same totals as backend XLSX export.
- axe-core zero critical violations on all admin pages.
- All admin actions are activity-logged.

### 5.3 Risks & mitigations

- **Largest milestone.** Split into two sub-release sprints: M3a (partners + policies + payouts + rates) and M3b (taxonomy + tax rules + settings + polish). M3a unlocks external demos.
- **Design drift** — lock UX component library mid-M3; any new pattern must be added to `resources/js/components/` first, not ad-hoc in pages.
- **Wizard complexity** — policy create wizard (Task 7) is the highest-risk FE work. Timebox to 2 FE weeks; de-scope live preview to "preview on step 4" only if slipping.

---

## 6. M4 — Partner portal (4 calendar weeks · 9.5 EW)

**Goal.** Partner-facing SPA surface reusing M3 components, with strict data isolation and secure document access.

### 6.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | Partner controllers + policies (role-gated); reuse M3 Actions | 0.5 | — | — | — | M3 | No new business logic |
| 2 | `DocumentPolicy` + `SignedUrlIssuer` service + 5-min TTL + audit log entry on access | 1 | — | — | 0.25 | M1 | Private S3 + KMS |
| 3 | Partner dashboard (own KPIs) | 0.25 | 1 | — | — | 1 | |
| 4 | Partner policies list + create/edit (wizard reused) | 0.25 | 1.5 | — | 0.25 | 1, M3 | Column config hides admin-only fields |
| 5 | Partner payouts list (shows voids + reversals) + detail | — | 1 | — | 0.25 | 1 | Running balance row |
| 6 | Partner reports + XLSX export (scoped) | — | 0.75 | — | — | 1 | |
| 7 | Partner profile + password + 2FA opt-in | — | 0.5 | — | 0.25 | M1 | Fortify flows |
| 8 | Masked KYC display + reveal-on-policy-check flow | — | 0.5 | — | — | 2 | |
| 9 | Partner-scope isolation tests (partner A cannot fetch partner B) | — | — | — | 0.25 | 1 | Exhaustive per controller |
| 10 | Document upload (policy attachments) via media-library on private disk | — | 0.5 | 0.25 | — | 2 | |
| 11 | Staging cutover readiness: load test (100 concurrent partners) | — | — | 0.25 | — | all | Before prod cutover after M4 |
| 12 | Production cutover plan (blue-green, DB backup, rollback) | — | — | — | — | 11 | Document in runbook |
| **Totals** | | **2** | **6** | **0.5** | **1** | | |

### 6.2 Definition of Done

- Partner logs in, sees only their own policies and payouts.
- Document download uses signed URL (verified via Chrome devtools).
- Partner A → B leak test in Pest: every controller has an assertion.
- Production cutover runbook reviewed; staging load test passes at 100 concurrent partner sessions, p95 < 400 ms.

### 6.3 Risks & mitigations

- **Cutover timing** — production cutover to "MIS v2" happens after M4 for real users. Allow 1 calendar week of bake-time on staging with real traffic mirroring before flipping DNS.
- **Global scope regressions** — any new model needs the same `partner` scope or an explicit opt-out. Add a PHPStan rule or test helper that enumerates tenant-scoped models and fails if one lacks the scope.

---

## 7. M5 — Additional tax regimes + reporting (6 calendar weeks · 14 EW)

**Goal.** US, EU, AU jurisdictions supported. Year-end reports (1099, Form 26AS). FX sync job. Activity log viewer. Dashboard widgets mature.

### 7.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | `UsBackupWithholdingCalculator` (24 % on missing W-9) + tests | 1 | — | — | 0.25 | M2 | |
| 2 | `EuVatCalculator` (reverse charge vs forward, VIES check interface) + tests | 1.5 | — | — | 0.25 | M2 | VIES integration stubbed; real call in M6 |
| 3 | `AustraliaGstCalculator` (10 % on registered partners) + tests | 0.5 | — | — | 0.25 | M2 | |
| 4 | Tax rule seeder templates for each new jurisdiction | 0.5 | — | — | 0.25 | 1–3 | Admin can clone templates |
| 5 | Year-end US 1099 export (XLSX + PDF summary per partner) | 1 | 0.5 | — | 0.5 | 1 | |
| 6 | India Form 26AS quarterly TDS summary export | 1 | 0.25 | — | 0.25 | M2 | |
| 7 | General XLSX exports (policies, payouts, commission audit) via maatwebsite/laravel-excel, queued | 0.5 | 0.5 | — | 0.25 | M3 | |
| 8 | Activity log viewer: filter by user, action, date; export CSV | 0.5 | 0.75 | — | 0.25 | M3 | |
| 9 | `ExchangeRateProvider` + nightly sync job (ECB source) + fallback chain | 0.5 | — | 0.25 | — | M1 | |
| 10 | Dashboard v2: commission-by-jurisdiction, unpaid aging, top insurers | 0.25 | 1 | — | — | M3 | Sparkline + data tables |
| 11 | Saved filter presets ("This month", "FY 2025-26", custom) persisted per user | 0.25 | 0.5 | — | — | M3 | |
| 12 | Regression suite run on staging with multi-jurisdiction dataset | — | — | 0.5 | 0.5 | 1–9 | |
| 13 | Documentation: per-jurisdiction tax setup guide, year-end runbooks | 0.25 | 0.25 | 0.25 | — | all | |
| **Totals** | | **7** | **4** | **1** | **2** | | |

### 7.2 Definition of Done

- Each new tax regime has seeded `tax_rules` + documentation showing how to configure for a tenant.
- End-to-end: an "EU tenant" calculates a payout with reverse-charge VAT; a "US tenant" calculates with backup withholding.
- 1099 and 26AS exports validated against known-correct fixtures.
- FX sync job runs nightly on staging for 5 consecutive days without manual intervention.

### 7.3 Risks & mitigations

- **Jurisdiction correctness** — budget for one tax / finance consultant review at end of M5 (½ week external spend). The engineering team should not be the final word on tax semantics.
- **Scope creep** — "one more jurisdiction" temptation. Hold the line at US+EU+AU for M5; additional jurisdictions become post-GA tickets.

---

## 8. M6 — Hardening (4 calendar weeks · 9 EW)

**Goal.** Enterprise security posture: 2FA mandatory for admin/auditor, per-tenant KMS keys, residency enforcement, external penetration test, SOC 2 readiness review.

### 8.1 Work breakdown

| # | Task | BE | FE | Ops | QA | Depends on | Notes |
|---|---|---:|---:|---:|---:|---|---|
| 1 | Mandatory 2FA for admin/auditor roles (Fortify TOTP) + enrollment flow | 0.5 | 0.5 | — | — | M1 | Enforcement via middleware |
| 2 | Per-tenant KMS data keys + envelope encryption for encrypted casts | 1 | — | 1 | — | M1 | AWS KMS; key rotation runbook |
| 3 | `EnsureTenantRegion` middleware (IP geolocation + residency flag enforcement) | 0.5 | — | 0.5 | 0.25 | M1 | GeoIP service configured |
| 4 | Private S3 bucket policies audit; least-privilege IAM per tenant prefix | 0.25 | — | 0.75 | — | M4 | |
| 5 | Rate limiting: login throttle, API throttle, export throttle | 0.25 | — | — | — | M1 | |
| 6 | Security headers, CSP, HSTS, SRI for Vite bundles | — | 0.25 | 0.25 | — | — | |
| 7 | Dependency audit + lockfile hash pinning in CI (`composer audit`, `npm audit`) | — | — | 0.25 | — | — | Must-fix threshold = high |
| 8 | External pen test (2-week engagement window, internal liaison + remediation) | 0.25 | 0.25 | 0.25 | 0.5 | 1–6 | Budget vendor time separately |
| 9 | SOC 2 readiness review: control mapping, evidence collection plan, gap list | — | — | — | 1 | 1–6 | External advisor recommended |
| 10 | Observability: structured logs, Sentry (or similar), per-tenant log tagging, alerting runbook | 0.25 | — | 0.75 | — | — | |
| 11 | Backup + restore drill: per-tenant DB restore, KMS key recovery | — | — | 0.5 | 0.25 | 2 | |
| 12 | Runbook: incident response, key rotation, tenant offboarding, data deletion (GDPR / DPDP) | — | — | — | — | 1–11 | |
| 13 | Final staging → prod cutover | — | — | — | — | all | Follow runbook from M4 |
| **Totals** | | **3** | **1** | **3** | **2** | | |

### 8.2 Definition of Done

- Pen test report: zero critical or high findings open.
- SOC 2 readiness gap list: all "blockers" closed; "improvements" documented with owners.
- 2FA enrollment: 100 % of admin/auditor accounts before GA.
- Restore drill: full tenant recovered from backup within the SLA (set in M4 runbook).

### 8.3 Risks & mitigations

- **Pen-test findings** — plan for 1 EW of unplanned remediation. If fewer findings, reinvest in SOC 2 evidence collection.
- **KMS integration complexity** — AWS KMS envelope encryption has non-obvious failure modes (key revocation, cross-region replication). Mitigation: task 2 deliberately over-budgeted; test key-rotation before prod enablement.

---

## 9. Dependency graph (critical path)

```
M1 Foundation ─────────────┐
  │                         │
  ▼                         ▼
M2 Commission + Tax      FE primitives (M1 task 19)
  │                         │
  ▼                         ▼
  └────►  M3 Admin panel ◄──┘
              │
              ▼
          M4 Partner portal
              │
              ▼
     ┌────────┴────────┐
     ▼                 ▼
M5 More regimes   M6 Hardening         (M5 & M6 partially in parallel)
     │                 │
     └──────►  GA  ◄───┘
```

**Critical path** runs M1 → M2 → M3 → M4 → M6 → GA. M3 is the longest single milestone and the most likely to slip. **Invest early in M3 prep during M2**: the FE pair can start on `PayoutBreakdown.vue` and Vuetify primitives in M2 (already planned as Tasks 17 and M1 Tasks 18–19).

M5 can run **partially concurrent with M6**: tax engine extension is BE-heavy and hardening is DevOps-heavy. With the right staff split, M5 and M6 together = ~7 calendar weeks instead of 10 sequential.

---

## 10. Timeline (Gantt-ish)

```
Week   1   2   3   4   5   6   7   8   9  10  11  12  13  14  15  16  17  18  19  20  21  22  23  24  25  26  27  28  29  30  31  32  33  34  35
Pre    ██  ██  ██                                                                                                                                    (discovery, env)
M1         ██  ██  ██  ██  ██  ██                                                                                                                    (foundation)
M2                     ██  ██  ██  ██  ██  ██                                                                                                        (engine)
M3                              ██  ██  ██  ██  ██  ██  ██  ██  ██  ██                                                                               (admin panel)
M4                                                              ██  ██  ██  ██                                                                       (partner portal)
M5                                                                          ██  ██  ██  ██  ██  ██                                                   (regimes + reporting)
M6                                                                                      ██  ██  ██  ██                                               (hardening, overlaps M5)
Buffer                                                                                                  ██  ██  ██  ██  ██  ██  ██                   (contingency)
GA                                                                                                                                              ▼    (week 35, generous)
```

Pre-project discovery can start while hiring/onboarding wraps up. M1 kickoff week 3. First internal demo (M1 DoD) end of week 8; external demo (M3a partial) around week 14; partner UAT (post-M4) around week 22; GA target **week 28–30** without buffer, **week 35** with 20 % contingency.

---

## 11. Staffing & ramp

| Phase | Calendar weeks | Role utilization |
|---|---|---|
| Pre-project + M1 start | 1–4 | 1 BE + 1 FE + 1 DevOps onboard; hire/ramp second BE + FE |
| M1 end / M2 start | 5–10 | Full team of 5 engaged |
| M2 → M3 | 11–16 | FE pair transitions M2 stub work → M3 admin panel |
| M3 → M4 | 17–22 | One FE starts M4 while other wraps M3 polish |
| M5 + M6 | 23–30 | BE on tax regimes; DevOps + 1 BE on hardening; QA leads pen-test liaison |
| Cutover + GA | 31–35 | All-hands on regression + incident watch |

Day-to-day: 2-week sprints, demos every Friday, retro every other Friday.

---

## 12. Exit criteria for GA

| Area | Must be true |
|---|---|
| Functional | M1–M4 DoD met; at least India + US + EU tax regimes live |
| Quality | Pest suite green, ≥ 90 % coverage on `app/Actions` + `app/Services/Tax`; zero high-severity bugs open; axe-core clean |
| Performance | p95 admin list view < 400 ms, p95 payout calc < 500 ms at 2× projected peak |
| Security | Pen test clean; 2FA enforced; encrypted casts using KMS; bucket audit clean |
| Operational | Runbooks reviewed; on-call rota; paging alerts; backup restore drilled |
| Compliance | SOC 2 readiness gap list closed; GDPR / DPDP workflows documented |
| Documentation | Admin guide, partner guide, tax setup guide, runbook, ADRs for key decisions |

---

## 13. Key risks register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Tenancy + Postgres constraint complexity slows M1 | Medium | High | Over-budget M1; pick a single staff engineer as "tenancy lead"; reference implementation review in week 2 |
| M3 admin panel slips | High | High | Sub-release M3a by week 14 (partners + policies + payouts); protect shared component build-out early |
| Policy wizard with live preview too complex | Medium | Medium | Timebox at 2 FE weeks; de-scope live preview if slipping |
| Pen test finds high-severity issues | Medium | High | Budget 1 EW remediation; book vendor in week 27 not 31 so remediation fits before GA |
| Tax semantics incorrect for a jurisdiction | Medium | High | Book ½ week external consultant review at M5 close |
| FX provider outage breaks reporting | Low | Low | Provider fallback chain; cached last-known rate; never used in ledger |
| Key personnel turnover | Medium | High | Pair on every area; ADRs maintained; no single point of failure for tenancy or tax engine |
| Scope creep from stakeholders ("one more report") | High | Medium | PM discipline; everything post-M5 goes to post-GA backlog unless tied to GA exit criteria |

---

## 14. Next steps

1. Review this plan with stakeholders; negotiate scope vs. calendar tradeoffs if the timeline is too long.
2. Confirm team composition and start dates.
3. Decide: AWS region layout for multi-tenant hosting (affects M1 DevOps work).
4. Book pen-test vendor for week 27.
5. Kick off pre-project: discovery sessions, environment provisioning, tenancy-lead deep dive on stancl/tenancy in multi-DB mode.

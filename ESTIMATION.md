# SettleOS — Estimation

Numbers-only companion to [PROJECT_PLAN.md](PROJECT_PLAN.md). Effort, cost, and scenario analysis for the SettleOS scope defined in [MODERN_DESIGN.md](MODERN_DESIGN.md).

---

## 1. Assumptions

| Dimension | Value |
|---|---|
| Estimation unit | **Engineer-week (EW)** = 1 engineer × 5 working days × ~4.5 focused hrs/day = ~22.5 hrs |
| Buffer inside each task | ~15 % (review, refinement, docs, rework) |
| Top-level contingency | 20 % on calendar, not folded into task numbers |
| Team composition (baseline) | 2 backend, 2 frontend, 0.5 DevOps, 0.5 QA = **5.0 FTE** |
| Seniority | Mid-to-senior, familiar with Laravel + Vue 3 + Vuetify |
| Blended rate (parametric, override for your context) | **₹1.2 Lakh / EW** (~$1,400 / EW)  **OR**  fill in your own below |
| Excluded | Hiring/onboarding, external vendor fees (pen test, tax advisor, SOC 2 advisor), AWS / S3 / KMS infra costs, product / UX discovery work beyond pre-project |
| Currency | INR (₹) shown first; USD equivalents at 1 USD = ₹85 |

---

## 2. Summary (baseline scenario: full scope, 5-FTE team)

| # | Milestone | BE | FE | Ops | QA | Total (EW) | Calendar |
|---|---|---:|---:|---:|---:|---:|---|
| M1 | Foundation | 8.0 | 4.0 | 3.0 | 1.0 | **16.0** | 6 wk |
| M2 | Commission + payout + tax engine | 10.0 | 1.0 | 0.5 | 2.0 | **13.5** | 6 wk |
| M3 | Admin panel | 6.0 | 14.0 | 0.5 | 2.0 | **22.5** | 9 wk |
| M4 | Partner portal | 2.0 | 6.0 | 0.5 | 1.0 | **9.5** | 4 wk |
| M5 | Additional tax regimes + reporting | 7.0 | 4.0 | 1.0 | 2.0 | **14.0** | 6 wk |
| M6 | Hardening + pen-test | 3.0 | 1.0 | 3.0 | 2.0 | **9.0** | 4 wk (overlaps M5) |
|   | **Engineering total** | **36.0** | **30.0** | **8.5** | **10.0** | **84.5** | **~35 wk** |
|   | Pre-project (discovery, env provisioning) | — | — | — | — | — | +3 wk |
|   | Contingency (20 % of calendar) | — | — | — | — | — | +7 wk |
|   | **Calendar total** | | | | | | **~45 wk (~10.5 mo)** |

**Effort total: 84.5 engineer-weeks.**
At a blended ₹1.2 L / EW: **~₹1.01 Cr** (~$119 k USD).
At ₹2.5 L / EW (senior/offshore premium): **~₹2.11 Cr** (~$248 k USD).

---

## 3. Effort by workstream

```
Backend     ████████████████████████████████████     36.0  EW   (42.6 %)
Frontend    ██████████████████████████████           30.0  EW   (35.5 %)
QA          ██████████                               10.0  EW   (11.8 %)
DevOps      █████████                                 8.5  EW   (10.1 %)
                                                     ─────
Total                                                84.5  EW
```

Backend-heavy on M1 (schema, tenancy, primitives) and M2 (engine). Frontend dominates M3 (admin panel). DevOps concentrates in M1 (infra scaffolding) and M6 (KMS, pen-test liaison, observability).

---

## 4. Cost model (parametrized)

Multiply EW by your blended rate. Baseline 84.5 EW.

| Blended rate / EW | Engineering cost | Typical team profile |
|---|---:|---|
| ₹0.8 L (~$940) | ₹67.6 L (~$79 k) | Junior–mid offshore squad |
| ₹1.2 L (~$1,410) | ₹1.01 Cr (~$119 k) | Mid-senior offshore squad (baseline) |
| ₹1.8 L (~$2,115) | ₹1.52 Cr (~$179 k) | Senior offshore / mid-onshore India |
| ₹2.5 L (~$2,940) | ₹2.11 Cr (~$248 k) | Senior onshore India / mid EU |
| $10,000 (~₹8.5 L) | ~₹7.18 Cr (~$845 k) | Senior US / WEU contract |

### 4.1 Non-engineering costs to budget separately

| Item | Typical range |
|---|---|
| External pen test (2-week engagement) | ₹4–12 L / $5–14 k |
| Tax / finance advisor (½ week review @ M5) | ₹2–5 L / $2.5–6 k |
| SOC 2 readiness advisor (pre-audit gap analysis) | ₹8–20 L / $10–23 k |
| AWS infra (dev + staging + prod, 3 regions, first year) | ₹12–25 L / $14–30 k |
| Observability SaaS (Sentry / Datadog / similar, first year) | ₹3–8 L / $3.5–9 k |
| License + tools (GitHub Enterprise, CI minutes, design tools) | ₹2–5 L / $2.5–6 k |

Grand-total reference (baseline engineering + mid non-engineering): **~₹1.4–1.8 Cr (~$165–210 k)** for full-scope delivery.

---

## 5. Scenarios

### 5.1 MVP (fastest to a usable demo)

Scope = M1 + M2 + partial M3 ("M3a": Partners + Policies + Payouts + Commission rates; no Taxonomy/TaxRules/Settings UI) + minimal M4 (partner dashboard + payouts read-only).

| Milestone | Included? | EW |
|---|---|---:|
| M1 Foundation | full | 16.0 |
| M2 Engine | full | 13.5 |
| M3a Admin core | partial | ~14.0 |
| M4a Partner read-only | partial | ~5.5 |
| **Total** | | **~49.0** |

Calendar with 5-FTE: **~20 wk (~4.5 months)**. Cost at ₹1.2 L/EW: **~₹58.8 L (~$69 k)**.

Trade-off: one tenant, one tax regime (India), no void/reverse UI (API only), no XLSX exports, no 2FA-mandatory, no pen-test. Not production-ready for a regulated environment.

### 5.2 Production-ready (recommended baseline)

Scope = M1 through M4 + M6 hardening. M5 deferred to post-GA.

| Milestone | Included? | EW |
|---|---|---:|
| M1 Foundation | full | 16.0 |
| M2 Engine | full | 13.5 |
| M3 Admin panel | full | 22.5 |
| M4 Partner portal | full | 9.5 |
| M6 Hardening | full | 9.0 |
| **Total** | | **70.5** |

Calendar with 5-FTE: **~30 wk (~7 months)**. Cost at ₹1.2 L/EW: **~₹84.6 L (~$100 k)**.

Trade-off: India-only tax (TDS + GST) at launch; US/EU/AU via post-GA change-requests (~6 wk each). Good fit if your initial launch market is India.

### 5.3 Full global (all jurisdictions at launch)

Scope = full M1–M6 (baseline in §2).

**~84.5 EW · ~35 wk · ~₹1.01 Cr (~$119 k)** before contingency.

### 5.4 Global + aggressive parallelism (larger team)

Scope = full. Team = 4 BE, 3 FE, 1 DevOps, 1 QA = **9 FTE**.

Effort unchanged at 84.5 EW (scope identical). Calendar compresses but not linearly — M1 and M2 remain serialized on the critical path:

| Phase | 5 FTE | 9 FTE |
|---|---|---|
| M1 + M2 (critical path, mostly BE) | 12 wk | 9 wk |
| M3 (FE-heavy, parallelizable) | 9 wk | 5 wk |
| M4 | 4 wk | 3 wk |
| M5 + M6 (concurrent) | 6 wk | 4 wk |
| **Core calendar** | **~31 wk** | **~21 wk** |
| **With 20 % buffer** | ~37 wk | ~25 wk |

Caveat: Brooks' Law. Adding engineers has diminishing returns, and beyond 2 + 3 on the critical path you hit coordination cost. The 9-FTE scenario is realistic only if the team is already gelled.

### 5.5 Scenario comparison

| Scenario | Effort (EW) | Calendar | Cost @ ₹1.2 L/EW | Cost @ ₹2.5 L/EW | Market fit |
|---|---:|---:|---:|---:|---|
| MVP demo | ~49 | ~20 wk | ₹58.8 L | ₹1.22 Cr | Internal / pilot |
| Production-ready (IN only) | ~70.5 | ~30 wk | ₹84.6 L | ₹1.76 Cr | India launch |
| Full global (baseline) | ~84.5 | ~35 wk | ₹1.01 Cr | ₹2.11 Cr | Multi-region launch |
| Full global (9 FTE) | ~84.5 | ~25 wk | ₹1.01 Cr | ₹2.11 Cr | Multi-region, tight deadline |

---

## 6. Sensitivity analysis

Largest swing factors, ranked by impact on calendar.

| Driver | Baseline | If worse (and how worse) | Delta |
|---|---|---|---|
| M3 admin panel scope | 22.5 EW | +6 EW if wizard live-preview stays in scope under tight UX iteration | +1.5 wk |
| Postgres tenancy / constraint learning curve | 16 EW M1 | +4 EW if team is new to `stancl/tenancy` multi-DB mode | +1 wk |
| Pen-test remediation | 1 EW implicit | +3 EW if findings are deep | +1 wk |
| Tax rule correctness iteration | 13.5 EW M2 | +3 EW if external advisor raises gaps | +0.5 wk |
| Cutover + production bake-time (post-M4) | 1 wk | +2 wk if parallel-run monitoring reveals issues | +2 wk |
| Team attrition mid-project | 0 | 1 senior engineer leaving mid-M3 = +6 wk | +6 wk |

Baseline + worst-case on all drivers = **+12 weeks** (~3 months) beyond calendar. This is why §2 includes a 20 % contingency rather than a 10 % one.

---

## 7. Early-warning triggers

Signals that the plan is slipping and re-planning is needed:

- M1 ends with CI still red or tenancy isolation tests flaky → add 1 wk before starting M2.
- M2 concurrent-payout test suite still flaky after 2 attempts → stop, re-architect the lock strategy, +1 wk.
- M3 week 6 of 9: fewer than 60 % of resources have list + form views in staging → cut taxonomy/tax-rule UI from M3, defer to M5.
- Pen test booked < 3 weeks before GA date → push GA by 2 weeks or book a second vendor.

---

## 8. What this estimate does **not** include

- **Legacy data migration** from the current MIS (custom command, parallel-run reconciliation) — a separate ~8–12 EW workstream if required.
- **Hypercare / warranty period** (post-GA on-call support) — plan 2–4 EW / month for first 3 months.
- **Ongoing maintenance** — rule-of-thumb 15–20 % of build effort per year for bug fixes, dependency upgrades, small features.
- **Internationalization / localization** beyond currency + `tax_id_type` — UI string translation is ~4–6 EW per language pair.
- **Mobile apps**, **customer-facing portals**, **real-time notifications**, **reporting BI layer** — all post-GA workstreams.
- **Training & documentation for end-users** — separate PM / TechWriter workstream.

---

## 9. Recommended path

**Start with Scenario 5.2 (production-ready, India-only).** 70.5 EW, ~7 months at 5 FTE. It delivers a defensible launch in a single market with full hardening and a clean architecture ready to extend.

Post-GA, schedule M5 (additional tax regimes) as a focused 6-week project per market, priced per jurisdiction. This keeps the initial budget below ₹1 Cr while preserving full global optionality.

If the initial market is multi-region from day one, go Scenario 5.3 (full baseline). Avoid 5.4 (9 FTE) unless the team already exists; hiring and gelling the larger team will cost more calendar than it saves.

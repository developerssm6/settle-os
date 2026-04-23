# SettleOS — UI / UX Design

Companion to [MODERN_DESIGN.md](MODERN_DESIGN.md). Covers the experience layer of SettleOS: personas, information architecture, visual system, screen specs, interaction patterns, and accessibility. Built on Vuetify 3 (Material Design 3 foundation) — this document describes *how* we use it, not a rebuild of the component library.

---

## 1. Principles

1. **Calc-transparent.** Every monetary number must be explainable. If you see ₹2,340 commission, one click shows how it was derived (rate id, components, date window).
2. **Zero-surprise defaults.** Partners only see their own data — never a "sorry, unauthorized" after-the-fact. Admin actions show consequences before executing (bulk process count, affected rows).
3. **Data-dense tables, not dashboards full of cards.** This is a back-office tool. Every screen answers a question; most screens are tables with excellent filters.
4. **Keyboard-first for admins.** Admins live in this app 8 hours a day. `/` focuses search, `n` opens create, `Esc` closes dialogs, arrow keys navigate table rows.
5. **Progressive disclosure.** Policy create is a 4-step wizard, not a 60-field wall. Commission rate form only shows the vehicle attributes relevant to the selected vehicle type.
6. **Forgiving.** Destructive actions are two-step (confirm dialog with typed confirmation for cascading deletes). Soft delete everywhere — there's always an "Archived" filter.
7. **Performance budget.** Every table page ≤ 150 ms TTI on 4G. Heavy lists are server-paginated (`VDataTableServer`). Exports are queued with toast notifications, never blocking the UI.

---

## 2. Personas & jobs to be done

| Persona | Role | Primary jobs |
|---|---|---|
| **Rhea — Operations Admin** | `admin` | Enter new policies, calculate & process payouts, generate invoices, reconcile at month-end |
| **Arun — Finance Admin** | `admin` | Review payouts before processing, verify TDS, download XLSX for accounting, audit breakdowns |
| **Priya — Partner (agent)** | `partner` | Enter her own policy sales, check commission earned, download her own payout statements |
| **Siba — Auditor** | `auditor` | Read-only access to everything; export activity logs; no writes |

JTBD framing:
- *"When a policy is sold, I want to see what commission I'll earn for it so I can trust the system."* → real-time payout calc + visible breakdown.
- *"When a commission rate changes, I want to recalculate affected open payouts so month-end is correct."* → bulk recalc with preview.
- *"When I process 200 payouts at once, I want to know exactly how much I'm committing to pay."* → bulk-action confirmation shows total ₹.

---

## 3. Information architecture

### 3.1 Admin sitemap

```
/admin
├── Dashboard                     (default landing for admin)
├── Partners
│   ├── List (default)
│   ├── Create
│   └── :id
│       ├── Overview / profile
│       ├── Commission overrides
│       ├── Tax config (TDS)
│       ├── Documents
│       └── Activity
├── Policies
│   ├── List
│   ├── Create   (wizard)
│   └── :id (show / edit / archive)
├── Payouts
│   ├── List   (default filters: status = calculated, current month)
│   ├── :id    (detail + breakdown + invoice)
│   └── Bulk actions: Calculate • Recalculate • Process • Export
├── Commission Rates
│   ├── Global rates (default view)
│   ├── Partner overrides (filter switch)
│   └── Create / Edit
├── Insurers
├── Taxonomy
│   ├── Business types · Policy types · Coverage types
│   ├── Vehicle types · Fuel · Ages · Engine · Seats · Weights
│   ├── Makes · Models
│   └── Two-wheeler · Misc-D subtypes
├── Reports & Exports
│   ├── Policy export
│   ├── Payout export
│   └── Commission audit
├── Settings
│   ├── Invoice issuer (company details)
│   ├── Users & roles
│   ├── Activity log
│   └── System
└── Profile
```

### 3.2 Partner sitemap

```
/partner
├── Dashboard                  (default landing for partner)
├── My Policies
│   ├── List
│   ├── Create  (wizard)
│   └── :id
├── My Payouts
│   ├── List
│   └── :id (breakdown + download invoice)
├── Reports
└── Profile
```

### 3.3 Auditor sitemap

Identical to Admin, with every write action hidden via Policy gates. A subtle **"Read-only"** chip appears in the app bar.

---

## 4. Design tokens

Visual system is Vuetify's Material 3 defaults, re-skinned with brand tokens. Defined once in `resources/js/plugins/vuetify.ts`.

### 4.1 Color

| Token | Light | Dark | Usage |
|---|---|---|---|
| `primary` | `#1E63E4` (trust-blue) | `#7FA9F5` | App bar, primary buttons, active nav |
| `secondary` | `#5A6678` | `#9CA6B7` | Secondary buttons, chips |
| `success` | `#12855D` | `#4BC28E` | Processed payouts, active rates |
| `warning` | `#C6820A` | `#F0B350` | Expiring rates, pending status |
| `error` | `#C0392B` | `#F0685A` | Cancelled, validation |
| `info` | `#1E63E4` | `#7FA9F5` | Informational banners |
| `surface` | `#FFFFFF` | `#111419` | Cards, tables |
| `surface-variant` | `#F4F6FA` | `#1A1F27` | Page background, row hover |
| `on-surface` | `#0B1220` | `#E6EAF2` | Body text |
| `outline` | `#D5DAE3` | `#2A303A` | Dividers, input borders |

Rates shown with semantic coloring:
- `effective_to < today` → warning chip "Expired"
- `effective_from > today` → info chip "Upcoming"
- otherwise → no chip (implied active)

### 4.2 Typography

Vuetify base with overrides:

| Role | Font | Size / weight |
|---|---|---|
| Display | Inter | 34 / 600 |
| Headline (page titles) | Inter | 24 / 600 |
| Title (card/section) | Inter | 18 / 600 |
| Body | Inter | 14 / 400 |
| Caption / helper | Inter | 12 / 400 |
| **Numeric** (monetary, percent) | **JetBrains Mono** | 14 / 500, tabular-nums |

Monospaced numbers for every currency/percent cell — column alignment matters in tables.

### 4.3 Spacing & radius

- Base unit: 4 px. Vuetify spacing scale 1–16 × 4 px.
- Card radius: 12 px. Button radius: 8 px. Chip radius: full.
- Section gutter: 24 px on ≥ md, 16 px on sm.

### 4.4 Elevation

- Level 0: page backgrounds.
- Level 1: cards, tables.
- Level 2: sticky app-bar, filter bar.
- Level 4: dialogs, menus.
- Never use level ≥ 6.

### 4.5 Iconography

Material Design Icons (`@mdi/font`), only. No mixing of icon sets.

---

## 5. Layout system

### 5.1 Shell — `AdminLayout.vue` / `PartnerLayout.vue`

```
┌────────────────────────────────────────────────────────────────┐
│  VAppBar  [ logo ][ search /] [ env chip ][ role chip ][ user ]│  56 px
├────────┬───────────────────────────────────────────────────────┤
│        │  Breadcrumb · Page title ······ [ page actions ]      │  64 px
│        │  ─────────────────────────────────────────────────    │
│  Drawer│                                                       │
│  240px │          Page content (max-width 1440 px, fluid)     │
│        │                                                       │
│        │                                                       │
└────────┴───────────────────────────────────────────────────────┘
                     VSnackbar (flash/toasts, bottom-right)
```

- Drawer: `VNavigationDrawer rail` collapses to 72 px on `md-and-down`. Remembered in localStorage.
- App bar has a global `⌘K` command palette (search across partners, policies, payouts).
- Env chip (`dev` · `stage` · `prod`) is unmissable in non-prod.
- Role chip (`Admin` / `Partner` / `Auditor · read-only`) always visible.

### 5.2 Auth shell — `AuthLayout.vue`

Centered card (max 420 px) on a subtle illustration background. Brand mark top-center. Supports light/dark.

### 5.3 Page template

```
<VContainer fluid class="pa-6">
  <PageHeader title="Payouts" :breadcrumbs="..." :actions="..." />
  <Filters v-model="filters" :config="..." />
  <DataTable :items="payouts" :meta="meta" />
</VContainer>
```

Every list page uses this exact triplet. Consistency > novelty.

---

## 6. Component library (custom wrappers over Vuetify)

| Component | Wraps | Why it exists |
|---|---|---|
| `DataTable.vue` | `VDataTableServer` | Hooks into Inertia paginator props; standard row actions, expansion slot, empty/loading/error states |
| `Filters.vue` | `VRow` of inputs | Chip-based active filters, debounced URL sync, "Save as view" |
| `PageHeader.vue` | `VRow` + `VBreadcrumbs` | Title, breadcrumbs, right-aligned action buttons, secondary subtitle |
| `MoneyField.vue` | `VTextField` | `₹` prefix, tabular-nums input, paste-cleans commas, blurs to formatted (`1,23,456.00`) |
| `PercentField.vue` | `VTextField` | `%` suffix, 0–100 clamp, 2-decimal |
| `StatusChip.vue` | `VChip` | Maps `PayoutStatus` / rate status to color + icon |
| `PayoutBreakdown.vue` | `VTimeline` | Reads `payout.breakdown` JSON and renders a step-by-step trace |
| `VehicleAttributeForm.vue` | `VForm` + conditional fields | Shows only attributes relevant to selected `vehicle_type` |
| `ConfirmDialog.vue` | `VDialog` | Default no, red affirmative, optional "type the name" gate |
| `ExportButton.vue` | `VBtn` + progress toast | Dispatches queued export; shows toast with progress + download link |
| `BulkActionBar.vue` | `VAppBar` (sticky bottom) | Appears when rows are selected; shows selection count + affected-₹ total for destructive/financial ops |

---

## 7. Key screens

### 7.1 Admin · Dashboard

```
┌──────────────────────────────────────────────────────────────┐
│ Good morning, Rhea                                            │
│                                                               │
│ ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐    │
│ │ Policies  │ │ Commission│ │ Pending   │ │ Active    │    │
│ │ this month│ │ calculated│ │ payouts   │ │ partners  │    │
│ │  142 ▲8%  │ │ ₹4.7L ▲12%│ │  23       │ │  47       │    │
│ └───────────┘ └───────────┘ └───────────┘ └───────────┘    │
│                                                               │
│ Policies by day                       Top partners (MTD)      │
│ ┌─────────────────────────┐          ┌────────────────────┐  │
│ │  VSparklineBar            │          │ 1. Priya     ₹54k  │  │
│ │                           │          │ 2. Sahil     ₹42k  │  │
│ └─────────────────────────┘          └────────────────────┘  │
│                                                               │
│ Recent activity  (ActivityLog feed · last 20)                 │
└──────────────────────────────────────────────────────────────┘
```

- KPI cards link to filtered list views (click "Pending payouts" → Payouts page with `status=calculated` filter preapplied).
- No chart junk. `VSparklineBar` only, one metric per chart.

### 7.2 Admin · Payouts list

This is the **most trafficked screen**.

- Filters: Partner (typeahead), Insurer, Business type, Status (multi), Policy date range. Active filters show as `VChip`s with × to remove.
- Columns: Policy # · Partner · Insurer · Premium (₹) · Commission (₹) · TDS · Net PO · Status · `[expand]`.
- Row expansion shows `PayoutBreakdown.vue`.
- Row action menu: Show policy · Recalculate · Download invoice · Cancel.
- Select checkboxes enable `BulkActionBar`:

```
┌─────────────────────────────────────────────────────────────┐
│  23 selected · Total commission ₹54,320 · TDS ₹2,716        │
│                             [ Recalculate ] [ Process 23 ]   │
└─────────────────────────────────────────────────────────────┘
```

- `[ Process 23 ]` opens a confirm dialog showing the breakdown and requires typed confirmation `PROCESS` for quantities ≥ 20.

### 7.3 Admin · Policy create (wizard)

4 steps in a `VStepper`:

1. **Basics** — partner, customer (typeahead with inline-create), insurer, business type, policy type, coverage type, policy date, premium.
2. **Vehicle** (motor only) — vehicle type selector, `VehicleAttributeForm` auto-adjusts visible fields, registration number, own damage, third party.
3. **Documents** — drag & drop into `VFileInput`, multi-file, shown as chips.
4. **Review & save** — summary card + *live payout preview* (runs `CalculatePayout` in preview mode; shows which rate will match, commission estimate). Save button disabled if preview throws `NoApplicableRate` — user sees "No commission rate configured for this combination. Add a rate or adjust attributes." with a link.

Autosave between steps as a draft (localStorage keyed on a draft uuid + partner).

### 7.4 Admin · Payout detail

Two-column layout:

- Left (60 %): **Breakdown**. `PayoutBreakdown.vue` renders the stored JSON as a timeline:
  ```
  ●  Resolved rate — Partner override #421 (effective 2026-01-01 → open)
     Insurer: ICICI Lombard   Vehicle: Private Car · Petrol · 1–3Y
  ●  OD  — 8.00 % × ₹12,500 = ₹1,000.00
  ●  TP  — 5.00 % × ₹4,800  = ₹240.00
  ●  Net commission         = ₹1,240.00
  ●  Flat amount            = ₹0.00
  ●  Total commission       = ₹1,240.00
  ●  TDS 5% on net          = ₹62.00
  ●  Net payout             = ₹1,178.00
  ```
- Right (40 %): policy summary, partner card, payout state machine visualized, action buttons (Recalculate, Process, Cancel, Download invoice).

### 7.5 Admin · Commission rates

- Top-of-page segmented control: **Global** · **Partner overrides** · **All**.
- Filters: Insurer, Business type, Vehicle type, Effective date on (datepicker — rates active on this date).
- Column emphasis on `effective_from → effective_to` as a compact range cell. Expired rows dimmed and tagged `Expired`. Upcoming rows tagged `Upcoming`.
- Row action: Clone as new override (useful to create a partner override from a global baseline).

### 7.6 Admin · Commission rate form

- Stepped disclosure: Business type first → reveals policy/coverage type → reveals relevant vehicle-attribute fields via `VehicleAttributeForm`. No field visible unless it applies.
- Rate inputs: `PercentField` for OD/TP/Net; `MoneyField` for flat. All four visible; at least one must be non-zero (validated).
- Effective window: `effective_from` required, `effective_to` optional ("Open-ended — leave blank").
- Partner field: "Global rate" by default; toggle to "Partner-specific" reveals partner selector.
- After save: "Rate saved. 14 open payouts match this new rate's window. [Recalculate them now]?" — links to bulk recalc.

### 7.7 Partner · Dashboard

```
┌──────────────────────────────────────────────────────────────┐
│ Hi, Priya                                                     │
│                                                               │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐ │
│ │ Policies   │ │ Commission │ │ Unpaid     │ │ TDS (YTD)  │ │
│ │ this month │ │ this month │ │ to me      │ │            │ │
│ │   12       │ │ ₹14,320    │ │ ₹6,180     │ │ ₹720       │ │
│ └────────────┘ └────────────┘ └────────────┘ └────────────┘ │
│                                                               │
│ Recent policies (last 10)      Pending payouts (5)            │
│ [ table ]                      [ list ]                       │
└──────────────────────────────────────────────────────────────┘
```

- No admin-oriented widgets; partner sees only their own numbers.

### 7.8 Partner · Policies / Payouts

Same `DataTable.vue` as admin, with the admin-only columns hidden via column config. Same `PayoutBreakdown` component in expansion.

---

## 8. Key user flows

### 8.1 Process month-end payouts (Admin)

1. Navigate **Payouts** (filters preset to current month, status=Calculated).
2. Scan totals in the footer row (sum of Net PO).
3. Select all via header checkbox → `BulkActionBar` shows total.
4. "Process N" button → confirm dialog with breakdown.
5. Type `PROCESS` to confirm (for N ≥ 20).
6. Toast: "Queued — processing 200 payouts". Status column animates to "Processed" as jobs complete.
7. Done state: export XLSX for accounting via `ExportButton`.

### 8.2 Roll out a new commission rate (Admin)

1. **Commission Rates → Create**.
2. Fill form; select "Global". Rate saved.
3. Banner: "14 payouts in this rate's window will be re-evaluated. [Recalculate]?"
4. Click → queued recalc. Toast shows progress; `PayoutBreakdown` for affected payouts updates with new rate id.

### 8.3 Partner creates a policy and checks expected commission (Partner)

1. **My Policies → Create** (wizard).
2. Step 4 "Review & save" shows `CalculatePayout` preview: expected commission, TDS, net PO. If no rate matches, explicit message (not silent zero).
3. Save. Policy appears in list. Payout row auto-created in state `Calculated`.
4. **My Payouts** → expand row to view breakdown.

### 8.4 Auditor downloads activity for a period

1. **Settings → Activity log** (visible read-only).
2. Filter by user + date range.
3. Export XLSX via `ExportButton`. Queued; toast with link when ready.

---

## 9. State design

Every data view has explicit empty / loading / error states — no blank screens.

| State | Treatment |
|---|---|
| **Loading** (initial) | Vuetify skeleton loader matching the row shape. `VDataTableServer` built-in skeleton; no spinner-over-data. |
| **Loading** (filter refetch) | Subtle linear progress at the top of the table; rows dim to 60 %. |
| **Empty** (no data yet) | Centered illustration + one-line reason + a CTA (e.g. "No policies yet. [Add your first policy]"). |
| **Empty** (filters excluded everything) | Different illustration + "No matches for these filters. [Clear filters]". Distinct from "no data at all" — never conflated. |
| **Error** | Inline `VAlert error` at the top of the page with the reason, retry button, and a "Copy error id" link (surface log correlation id from response header). |
| **Optimistic** | Not used for monetary ops. Payout calc and process always show deterministic in-flight state; no optimistic UI for financial data. |
| **Stale** | When a bulk recalc is running on the server, the payouts table shows a banner: "Recalculation in progress — some values will update shortly." |

---

## 10. Forms

Rules:

1. **Validate on blur, not keystroke** — except required / type-mismatch.
2. **Server validation always wins** — surface field errors next to the field plus a summary at top on save failure.
3. **Required marker**: `*` in label, red. Optional never marked.
4. **Helper text** is for format hints only (e.g. "DD/MM/YYYY"). Not for marketing.
5. **Dangerous fields** (effective windows, rate values) show a diff preview in the confirm dialog when editing existing records.
6. **Autosave drafts** for long forms (Policy wizard). Drafts keyed on user + resource + uuid, visible from a "Drafts" menu.

Input types:

| Data | Component |
|---|---|
| Money | `MoneyField` |
| Percent | `PercentField` |
| Date | `VDatePicker` via `VMenu` (not native date input — poor UX across browsers) |
| Date range | Twin `VDatePicker` side-by-side with presets ("This month", "Last 30 days", "FY 2025-26") |
| Multi-select taxonomy | `VAutocomplete multiple` with chips |
| Policy number / registration | `VTextField` with regex mask |

---

## 11. Tables

- **Density**: default `compact` (32 px rows) for admin; `default` (48 px) for partner. Toggle in column menu.
- **Sticky header** always. **Sticky first column** only on wide tables (Payouts).
- **Row expansion** for detail rather than a separate page when the extra info is small (payout breakdown fits).
- **Monetary columns right-aligned**, tabular-nums, with a subtotal row at the bottom for filtered set: `Σ on page · Σ all matches (server-computed)`.
- **Column chooser** via header overflow menu, persisted per user.
- **URL-synced state**: page, filters, sort reflected in querystring so links are shareable.
- **Bulk selection** only where bulk actions exist. Selecting across pages shows an interstitial "Selected 23 on this page. [Select all 412 matches]?"
- **Row click** goes to detail; icons and chips handle their own clicks (stopPropagation).

---

## 12. Feedback & notifications

| Channel | Use |
|---|---|
| `VSnackbar` (bottom-right) | Transient confirmations ("Policy saved"), non-blocking progress ("Export queued · 40%"), with action ("Undo", "View") |
| `VAlert` (inline) | Persistent page-level info / error / warning |
| `VBanner` (top of page) | System-wide notices: "Maintenance at 22:00 IST", "Recalculation in progress" |
| `VDialog` | Confirmations, destructive actions, multi-step flows |
| Toast stack | Max 3 visible; oldest auto-dismisses after 6 s. Errors persist until dismissed. |

Never use modal dialogs for *informational* content. Use banners.

---

## 13. Accessibility

Non-negotiable:

- WCAG 2.1 AA contrast on all text (our tokens pass; verify in CI via `@axe-core/vue` in dev).
- Every interactive element has a visible focus ring (Vuetify default + 2 px outline).
- Keyboard: every action reachable without mouse. Skip-to-content link on every layout.
- Live regions: toast container is `aria-live=polite`; critical errors `aria-live=assertive`.
- Labels: every form field has a persistent label (not placeholder-as-label).
- Charts: every `VSparkline` has a `VTable` fallback rendered visually-hidden with the data series.
- Color is never the only signal. Status uses color **plus** icon **plus** chip text.
- Motion: respects `prefers-reduced-motion` — disable page-transition slides and skeleton pulse.

---

## 14. Responsive

Breakpoints follow Vuetify (`xs < 600 < sm < 960 < md < 1280 < lg < 1920 < xl`).

| Element | ≥ md | sm | xs |
|---|---|---|---|
| Drawer | Open (240 px) | Rail (72 px) | Off-canvas overlay |
| Tables | Full columns | Column chooser trims to 6 | Card list view (one policy/payout per card with the 3 key fields + tap-to-expand) |
| Wizards | `VStepper` horizontal | `VStepper` vertical | Vertical, one step per screen |
| KPI cards | 4-column grid | 2-column grid | Stacked |
| Bulk action bar | Fixed bottom-left | Fixed bottom, full-width | Fixed bottom, full-width |

Mobile target is not **primary** (this is a back-office tool), but a partner checking their dashboard or latest payout on a phone should have a usable experience. Policy creation on mobile is **explicitly de-emphasized** — desktop-recommended banner for wizards on xs.

---

## 15. Microcopy

- **Tone**: direct, calm, precise. Finance context — no exclamation points.
- **Numbers**: always formatted (`₹1,23,456.00`); never `123456` raw.
- **Dates**: display `DD MMM YYYY` (e.g. `23 Apr 2026`). Store ISO.
- **Verbs on buttons**: "Create policy" not "Submit"; "Process 23 payouts" not "OK"; "Download invoice" not "Download".
- **Error messages** state what the user can do:
  - Bad: "Invalid input"
  - Good: "Own damage must be greater than 0 for motor policies"
- **Empty states** invite action:
  - "No commission rates configured for ICICI × Private Car. [Add one]"
- **Confirmation dialogs** restate the action and the cost:
  - "Process 23 payouts totaling ₹54,320? This marks them as paid and cannot be undone."

---

## 16. Implementation notes for devs

- Every page is one `.vue` file in `resources/js/pages/{Admin|Partner}/...` using `<script setup lang="ts">`.
- Shared primitives live in `resources/js/components/`. Page-specific components co-locate next to the page (`Admin/Payouts/_Breakdown.vue`).
- Route generation via Ziggy — never hand-write URLs in Vue.
- Props are typed via `spatie/typescript-transformer` from PHP DTOs — no hand-written TS mirrors.
- All Vuetify imports use tree-shakable `createVuetify({ components: { VDataTable, ... } })` to keep bundle lean.
- Theme is defined once in `plugins/vuetify.ts`; do not hardcode colors in components — use `color="primary"` etc.
- Storybook (optional M-5) for the custom components in `resources/js/components/`.

---

## 17. Open questions (to resolve with stakeholders before build)

1. **Partner self-signup** or admin-only invite? (Current design assumes admin invite.)
2. **Multi-currency** in scope? (Current design: INR-only.)
3. **Policy edit after payout processed** — allowed, disallowed, or edit-with-audit-and-recalc?
4. **Invoice numbering scheme** — per-issuer sequential with FY reset? Custom format per issuer?
5. **2FA** — mandatory for admin / auditor, optional for partner, or optional for all?
6. **SLA for recalc job** — is a 2-minute queue acceptable for month-end, or does this need to be synchronous?

Default assumptions are embedded in the sections above; flag any you want to challenge.

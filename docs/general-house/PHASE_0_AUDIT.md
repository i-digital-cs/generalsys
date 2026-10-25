# General House Operations — Phase 0 Audit

**Date:** 2026-10-25  
**Scope:** Discovery only — **no migrations, services, routes, or UI implemented**  
**Repo:** TEST `/home/d14098/generalsys.idigital-cs.com` · remote `i-digital-cs/generalsys`  
**Status:** CONDITIONAL APPROVAL (2026-10-25) — design revised; Phase 1 **BLOCKED** until explicit authorization  
**Revision pack:** `DESIGN_CHANGE_SUMMARY.md`

---

## 1. Existing architecture

### 1.1 Top-level modules (`App\Support\AppModule`)

| Portal label | Code key | What exists today |
|---|---|---|
| **General house** | `service` | Dashboard + **Valuation sheets only** |
| **Mohra** | `product` | Dashboard + Valuation + **full Gross ERP** (`/product/gross`) |
| **HR** | `hr` | Employees, attendance, payroll, permissions |
| **Settings** | `settings` | App settings, valuation assignments (Manager) |

Access to Service/Product: Manager always; others need `valuation_assignments.module ∈ {service,product}`.

### 1.2 What “General House” is today

**Branding + Service valuation module — not an operations/ERP codebase.**

- Sidebar (`config/module_menu.php` → `service`): dashboard, valuations.
- Laundry appears only as **valuation score fields** (`laundry_king_*`, `laundry_mart_*`) on valuation sheets.
- **No** laundry orders, invoices, zones, treatments, driver pickup lists, or GH-specific tables.

### 1.3 Mohra Gross (closest existing “ops” system)

Full ERP under `/product/gross` (docs: `docs/GROSS_MODULE.md`, schema §22):

- Catalog: categories, attributes (color/size/model), items (`ITM-#####`), images, stock PIECE ledger
- Parties: `gross_customers` (`name`,`phone`,`address` only), suppliers, shipping companies
- Sales: `MH-YYYYMM-####`, line discounts amount/percent, shipping fee, COD
- State: `DRAFT → CONFIRMED(+fulfillment) → DELIVERED|REFUSED|CANCELLED`
- Finance: treasury vouchers (cash/bank), expense types
- ACL: `gross_user_pages` page keys + policies via `GrossAccess`

**This is garment retail + courier fulfillment — not laundry pickup/invoice/delivery lifecycle.**

### 1.4 Drivers today

| Concept | Reality |
|---|---|
| RBAC `DRIVER` | HR role: clock, daily report, media photos/GPS (1 user on TEST) |
| Valuation “drivers” | Sheet JSON targets — not logistics |
| Gross shipping | Courier **companies**, not employee drivers |

### 1.5 Scheduling / reports / tests

- Scheduler (`routes/console.php`): `queue:work`, `reports:warn-missing` — **no Gross/GH logistics cron**.
- Money: `DECIMAL(12,2)`, snapshot labels on invoice lines, sequence tables.
- Tests: extensive Gross + HR/attendance suites; **zero** laundry-ops tests.

### 1.6 Live DB probe (TEST)

- ~65 tables; **27 `gross_*`** (+ product expense budgets).
- **Zero** `gh_*` / laundry-order / zone / treatment tables.
- Roles present: MANAGER, TEAM_LEADER, EMPLOYEE, ASSISTANT, DRIVER.

---

## 2. Reusable components

| Asset | Reuse how |
|---|---|
| Auth / RBAC / Sanctum session | Extend; add GH permissions/policies |
| `AppModule::SERVICE` + `valuation_assignments` | Gate GH under General House (`service`) |
| Blade + Bootstrap 5 RTL/LTR + `docs/UI_FRAMEWORK.md` | All GH UI |
| Form Requests + Policies + thin controllers | Same as Gross/HR |
| Service-layer SSOT for money & state | Mirror Gross pattern (never Blade/JS authority) |
| Sequence generators (`gross_invoice_sequences`) | Pattern for `GH-ORD-######` / invoice numbers |
| `DECIMAL(12,2)` + currency (EGP/AED) | Monetary columns; default **AED** for UAE laundry (confirm) |
| Hostinger constraints | `database` queue, file/db cache, single `schedule:run`, local Vite build |
| DRIVER role shell | Extend menus/routes for driver workspace (do not invent parallel login) |
| Gross page-ACL pattern (`gross_user_pages`) | Analogous `gh_user_pages` or Spatie-style permissions — decide |
| Audit / history patterns | Attendance/Gross history tables as design references |
| Existing Gross customers | **Do not merge** into laundry customers without owner approval (different domain) |

---

## 3. Missing components (must build)

| Domain | Gap |
|---|---|
| Catalog | Service categories, products, treatments, product–treatment offerings & prices |
| Customers | B2C/B2B, multi-address, tax/billing, contracts, restricted catalogs |
| Geography | Zones, min order amounts, override audit |
| Orders | References, scheduling windows, driver assignment, event history |
| Logistics SM | LATER → PICKUP → WORKING → DELIVERY → COMPLETED (+ exceptions) |
| Scheduler job | Idempotent 24h-before-pickup eligibility (Hostinger-safe) |
| Invoicing | Draft/issue, line+invoice discounts, VAT, snapshots, finalize → DELIVERY |
| Payments | Cash/online, independent of logistics status |
| Driver UI | Mobile lists, drag-order persistence, confirm pickup/delivery |
| Dashboard/reporting | Ops + financial aggregates |
| Permissions | Full GH matrix (catalog → finance) |
| Tests | Full phase matrix in owner brief §14 |

---

## 4. Database compatibility

| Approach | Recommendation |
|---|---|
| Reuse `gross_*` tables for laundry | **Reject** — wrong stock model (PIECE garments), wrong states, Mohra-only ACL, customer schema too thin |
| New `gh_*` (or `general_house_*`) schema under Service module | **Recommend** — forward-only migrations; no truncate; no rewrite of Gross/HR tables |
| Share `users` for drivers/dispatchers | **Yes** |
| Touch payroll/attendance tables | **Forbidden** |

Currency coexistence: Gross invoices are multi-currency capable; GH should store explicit `currency` (likely AED). Confirm tax jurisdiction (UAE VAT).

---

## 5. Integration points

```text
Portal "General house" (service)
  ├── existing: Valuation
  └── NEW: Operations submenu (catalog, customers, orders, logistics, invoices, drivers, reports)

users (DRIVER / dispatch roles)
  └── GH driver workspace + assignment

Laravel scheduler (single cron)
  └── NEW: gh:promote-pickup-eligible (idempotent)

Optional later:
  └── Treasury posting of cash collections (reuse Gross treasury? — owner decision)
```

Do **not** put GH routes under `/product/gross`. Prefer `/service/gh/...` or `/service/operations/...`.

---

## 6. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Confusing General House branding with Mohra Gross | High | Separate namespace, menu, docs; never share invoice tables |
| Reusing `gross_customers` without B2B/addresses | High | New GH customer model |
| Floating-point money | High | Decimal-safe engine; tests |
| Race on order/invoice finalize | High | Transactions + idempotent transitions |
| 24h pickup job double-fire on shared hosting | Med | Idempotent status guard + advisory lock/row version |
| DRIVER sees financial data | High | Policies; hide money without permission |
| Scope creep vs Mohra fulfillment | Med | Explicit state machine doc; no silent copy of Gross statuses |
| Attendance RC still CONDITIONAL on LIVE | Med | Keep GH work isolated; no attendance/payroll edits |
| Owner-undefined commercial rules (min order surcharge vs validation; tax order) | High | Decision log — **block Phase 5 until answered** |

---

## 7. Proposed implementation (high level)

1. **Module home:** extend Service sidebar with GH Operations groups (page ACL similar to Gross).
2. **New domain services:** `app/Services/GeneralHouse/{Catalog,Customers,Pricing,Orders,Logistics,Invoicing,Payments,Reporting}/`.
3. **New models/migrations** prefixed `gh_` (names provisional until `DATABASE_DESIGN.md` approval).
4. **State machine service** as sole writer of order status.
5. **Invoice calculation engine** as sole writer of money totals.
6. **Phases 1–8** as in owner brief; gate each phase with tests + UI/UX + report.
7. **TASKS.md** registration: `T-GH-0` … `T-GH-8` (see file update).

**Product ↔ category:** recommend **product belongs to one primary category** (simpler admin UX); treatments are many-to-many via offerings. Cross-category reuse deferred unless owner requires it.

---

## 8. Decisions requiring owner approval

See `DECISION_LOG.md` (D-GH-001 …). Blocking highlights:

1. **Min-order commercial policy:** validation-only vs surcharge to reach minimum vs either (configurable).
2. **Tax calculation order** relative to line/invoice discounts and minimum.
3. **VAT rate(s)** and who may mark exempt.
4. **Online payment:** gateway now vs integration stubs only.
5. **Treasury:** post GH cash into Gross treasury vs separate GH cash ledger.
6. **Module placement:** confirm Service (`General house`) not Mohra.
7. **Driver = existing `DRIVER` role** vs new dispatcher roles.
8. **Exception states** (cancel/fail/reschedule) allowed set.
9. **Invoice void/credit-note** rules before Phase 5.
10. **Currency default AED** vs dual EGP/AED at launch.

---

## 9. Phase 0 deliverables checklist

| Document | Path |
|---|---|
| This audit | `docs/general-house/PHASE_0_AUDIT.md` |
| Business requirements | `docs/general-house/BUSINESS_REQUIREMENTS.md` |
| Architecture | `docs/general-house/ARCHITECTURE.md` |
| Database design (proposed) | `docs/general-house/DATABASE_DESIGN.md` |
| State machine | `docs/general-house/STATE_MACHINE.md` |
| Financial rules (revised defaults) | `docs/general-house/FINANCIAL_RULES.md` |
| UI/UX specification | `docs/general-house/UI_UX_SPECIFICATION.md` |
| Implementation plan | `docs/general-house/IMPLEMENTATION_PLAN.md` |
| Decision log | `docs/general-house/DECISION_LOG.md` |
| Risk register | `docs/general-house/RISK_REGISTER.md` |
| Design change summary | `docs/general-house/DESIGN_CHANGE_SUMMARY.md` |
| Task board entries | `docs/TASKS.md` (Phase GH) |

---

## 10. Stop line

**Phase 0 design revised per CONDITIONAL APPROVAL.**  
**No migrations / models / controllers / services / UI** until explicit authorization to begin **Phase 1**.

Remaining blocking confirmations: **C-GH-TAX-MIN**, **C-GH-CORR**, **C-GH-CANCEL**, **C-GH-MULTI-CUR** (see `DECISION_LOG.md`).

```text
GENERAL HOUSE IMPLEMENTATION REPORT
PHASE: 0 — Discovery & Design (revised)
STATUS: CONDITIONAL APPROVAL — design docs updated; implementation BLOCKED

IMPLEMENTED FEATURES: Documentation only (revision pack)
FILES UPDATED: docs/general-house/{DATABASE_DESIGN,STATE_MACHINE,FINANCIAL_RULES,ARCHITECTURE,DECISION_LOG,RISK_REGISTER,DESIGN_CHANGE_SUMMARY,PHASE_0_AUDIT}.md
DATABASE MIGRATIONS: none
PERMISSIONS: none
AUTOMATED TEST RESULTS: N/A (no code)
UI/UX VERIFICATION: N/A
SECURITY VERIFICATION: N/A
FINANCIAL INTEGRITY: defaults documented — not legally validated; C-GH-TAX-MIN open
REGRESSION RESULTS: N/A
PRODUCTION IMPACT: none
OPEN RISKS: see RISK_REGISTER.md
OWNER DECISIONS REQUIRED: YES (C-GH-TAX-MIN, C-GH-CORR, C-GH-CANCEL, C-GH-MULTI-CUR)
NEXT PHASE: 1 — only after explicit authorization
APPROVAL REQUIRED: YES (Phase 1 start)
```

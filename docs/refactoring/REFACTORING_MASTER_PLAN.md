# Refactoring master plan — Task 2

**Authorization:** Owner approved Phase 0 (audit/plan). **Phase 1+ requires explicit owner approval before application code changes.**  
**Constraints:** No new features; no unapproved behavior changes; protect Task 1 / 1.6 attendance + settled payroll; AGENTS.md §3.0; Hostinger limits.

Strategy: **incremental extracts**, high-impact / lower-risk first where possible; separate **test repairs** from **architecture moves**; never big-bang rewrite.

---

## Phase dependency overview

```text
Phase 0  Audit & plan                          ← COMPLETE (this pack)
    ↓
Phase 1  Baseline hygiene (stale tests + ACL characterization)
    ↓
Phase 2  Attendance domain seams (windows / DTO / calculator boundary)
    ↓
Phase 3  Payroll boundary freeze (aggregator/suggestion clarity; privacy UI)
    ↓
Phase 4  Authorization policies for HR domains (Attendance, Permission, Vacation)
    ↓
Phase 5  Gross sales/stock extracts (fulfillment, COD, StockService purity)
    ↓
Phase 6  Cross-cutting (namespaces, Blade ViewModels, dead command cleanup)
    ↓
Phase 7  Optional performance (N+1 audits) — only with measured evidence
```

Historical attendance **data reconciliation** remains **out of scope** (separate owner approval).

---

## Phase 0 — Architecture assessment (DONE)

| | |
|---|---|
| **Modules** | All (inventory) |
| **Deliverables** | `docs/refactoring/*` |
| **Code/DB** | Unchanged |
| **Acceptance** | Findings + duplication matrix + regression baseline + this plan |

---

## Phase 1 — Baseline hygiene (tests & known defects) — **DONE 2026-10-13**

| | |
|---|---|
| **Outcome** | Full suite **922 PASS / 0 FAIL**; protected **95 PASS**. See `PHASE_1_EXIT_REPORT.md`. |
| **Highlights** | Stale Task-1.6 / flexible-start / privacy asserts corrected; sales/purchase **currency immutable** after confirm restored; `GrossAccess` page-key cache cleared per test (fixed false ACL allow under ID reuse). |

---

## Phase 2 — Attendance domain seams — **DONE 2026-10-13**

| | |
|---|---|
| **Outcome** | `ShiftWindowResolver` + `ClockPageService` extracted; AttendanceService **847** LOC; ClockController **225** LOC; full suite **925 PASS**. See `PHASE_2_EXIT_REPORT.md`. |
| **Preserved** | Public window/late facades; Task 1/1.6 grace; no schema/backfill. |

---

## Phase 3 — Payroll boundary freeze — **DONE 2026-10-13**

| | |
|---|---|
| **Outcome** | `PayrollWriteGuard`; locked idempotent approve; immutability/privacy tests; full suite **932 PASS**. Formulas preserved. See `PHASE_3_EXIT_REPORT.md`. |

---

## Phase 4 — HR authorization policies — **DONE 2026-10-13**

| | |
|---|---|
| **Outcome** | `PermissionPolicy` + `VacationPolicy`; `HrRequestWriteGuard`; balance row locks; full suite **940 PASS**. See `PHASE_4_EXIT_REPORT.md`. |
| **Preserved** | Existing decide/force allow/deny; leave ≠ vacation rules; Task 1.6 attendance; Phase 3 payroll immutability; no schema. |
| **Deferred** | Broad `AttendancePolicy` for all clock/history routes (needs owner matrix); cancel/edit after submit (not a product feature). |

---

## Phase 5 — Gross sales & stock extracts — **DONE 2026-10-13**

| | |
|---|---|
| **Outcome** | `SalesInvoiceFulfillmentService` + `SalesInvoiceCodCollectionService`; repair command via `StockService`; `GrossStockIntegrityTest`; Gross **217** / full **947 PASS**. See `PHASE_5_EXIT_REPORT.md`. |
| **Preserved** | Confirm≠stock-out; handToCourier SALE_OUT; currency lock; catalog pieces_per_carton=1; no schema. |
| **Deferred** | Further controller LOC shrink; inventing ledger movements for repair clamp; wiring unused `applyRefuseReturn`. |

---

## Phase 6 — Cross-cutting cleanup — **DONE 2026-10-13 (PASS WITH CONDITIONS)**

| | |
|---|---|
| **Outcome** | Employees namespace consolidate; payroll job tries/timeout; valuation display prep; GROSS_MODULE §6 fixed; RISK-GROSS-001 characterized (not remodeled). Full **951 PASS**. See `PHASE_6_EXIT_REPORT.md`. |
| **Conditions** | RISK-GROSS-001 accepted until owner approves repair formula/ledger change. |
| **Deferred** | AttendanceGapPolicy 403 flip; Attendance model locator; SalesInvoiceController thin; N+1 measurement (Phase 7). |

---

## Phase 7 — Performance — **DONE 2026-10-13 (PASS WITH CONDITIONS)**

| | |
|---|---|
| **Outcome** | Synthetic query baselines; `monthForUser` 35+→**7**; `teamTodayStatus` →**5**; gaps/OT eager loads; `preventLazyLoading` in local; `QueryBudgetTest`. Full **953 PASS**. See `PHASE_7_EXIT_REPORT.md`. |
| **Conditions** | Metrics are SQLite/synthetic (not Hostinger production latency); RISK-GROSS-001 unchanged; indexes proposed only. |
| **Task 2** | Phases 0–7 complete pending owner overall sign-off. |

---

## Explicit non-goals

- New product features  
- Historical attendance backfill / payroll money rewrite  
- Redis, websockets, server-side npm  
- Big-bang domain rewrite or repository layer for its own sake  
- Changing grace thresholds or effective date without owner approval  

---

## Global acceptance for Task 2 completion (future)

- Protected 95 green throughout  
- Full suite at or better than Phase 1 cleaned baseline  
- God services reduced below AGENTS guideline or explicitly wavered with owner note  
- Policies for money + attendance privacy paths  
- Docs updated; ADRs optional for major seams  
- Owner sign-off per phase

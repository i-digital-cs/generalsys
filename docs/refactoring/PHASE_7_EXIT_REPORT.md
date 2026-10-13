# Task 2 Phase 7 — Exit report (performance baseline, query optimization & final validation)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 7 only.  
**Further phases / business-policy changes:** Not authorized.

Baseline carried forward: Phase 6 full suite **951 PASS**.

---

## 1. Benchmark methodology

| Item | Detail |
|---|---|
| Environment | Local PHPUnit / SQLite in-memory (`RefreshDatabase`) + one-off `artisan tinker` measurement after `migrate:fresh` |
| Dataset | **Synthetic fixtures** (8-team members; Aug 2026 month with ~25 attendance days + 5 rest days) |
| Metric | **SQL query count** via `DB::enableQueryLog()` — not wall-clock latency |
| Warm/cold | Cold DB per measurement (fresh migrate or RefreshDatabase) |
| Limitation | **Not production MySQL Hostinger benchmarks.** Results labeled **synthetic**. Absolute ms not claimed. |

AGENTS page budget: **≤ 30 queries** per user-facing page.

---

## 2. Before / after metrics (synthetic query counts)

| Workload | Before (estimated from code) | After (measured) | Delta |
|---|---|---|---|
| `HoursComparisonService::monthForUser` (31 days, 5 rest days) | ~35+ (31× `isRestDate` + base) | **7** | ≈ −80% queries |
| `HoursComparisonService::teamTodayStatus` (8 employees) | ~20+ (8× rest + 8× vacation + base) | **5** | ≈ −75% queries |
| Approvals gaps list | N× `teamLeaders` via display names | Eager `attendance.user.teamLeaders` | N+1 removed |
| Approvals overtime list | Per-row `loadMissing(segments,gaps)` | Eager `segments`,`gaps` | N+1 removed |

**CI budgets** (`QueryBudgetTest`): `monthForUser ≤ 15`; `teamTodayStatus ≤ 25`.

---

## 3. N+1 audit summary

| Path | Finding | Action |
|---|---|---|
| TL team dashboard / month hours | Critical rest-day + segments/gaps storm | **Fixed** |
| Approvals gaps | Missing `teamLeaders` | **Fixed** |
| Approvals overtime | Missing `segments`/`gaps` | **Fixed** |
| Admin employees / payroll index / Gross lists / employee requests | Already eager-loaded | No change |
| Attendance history | Already batched | No change |
| `VacationRequestService::attachRestAvailability` | Per-row rest swap count | **Follow-up** (UI may not display) |
| `teamMonthSummary` | Still N × `monthForUser` | **Mitigated** (each month cheaper); bulk redesign deferred |

`Model::preventLazyLoading()` enabled for **`local` only** (not production, not testing — avoids CI churn from intentional lazy loads).

---

## 4. Query optimizations shipped

1. `monthForUser`: `with(['photos','segments','gaps'])` + `datesInMonth` set lookup instead of per-day `isRestDate`.
2. `teamTodayStatus`: batch `restUserIdsOnDate` / `vacationUserIdsOnDate`.
3. `AttendanceGapService::listForActor`: `attendance.user.teamLeaders`.
4. `AttendanceTimeReviewService::listOvertimeForActor`: `segments`, `gaps`.

**No** financial/stock formula changes. **RISK-GROSS-001 unchanged.**

---

## 5. Index recommendations (NOT applied — need owner approval)

| Table | Candidate | Pattern | Notes |
|---|---|---|---|
| `rest_days` | `(user_id, date)` | Month/day rest lookups | Likely already covered — verify `SHOW INDEX` on MySQL before adding |
| `vacations` | `(user_id, status, start_date, end_date)` | Range overlap for approvals/hours | Composite; write overhead on vacation CRUD |
| `attendances` | existing `(user_id, date)` | Confirm present per schema doc | Do not duplicate |

No migrations in Phase 7.

---

## 6. Architecture quality (Phase 0 → Phase 7)

| Theme | Phase 0 | After Phase 7 |
|---|---|---|
| Attendance seams | God `AttendanceService` | + `ShiftWindowResolver`, `ClockPageService`, grace policy |
| Payroll integrity | Soft draft checks | `PayrollWriteGuard`, locked approve, privacy tests |
| HR auth | `abort_unless` only | Policies + `HrRequestWriteGuard` |
| Gross stock | Competing repair writer | StockService SSOT + fulfillment/COD extracts |
| Cross-cutting | Namespace split, no job timeout | Employees NS, Hostinger job tries/timeout |
| Performance | Unmeasured | Synthetic budgets + measured hot-path cuts |
| Coupling | High | Clearer domain seams; some god LOC remains (waived with owner notes in prior phases) |
| Testability | 914/7 fail → | **953 PASS / 0 FAIL** |

Line-count reduction alone is **not** the quality proof; invariants + green regression + documented ownership are.

---

## 7. Business behavior

**PRESERVED** — attendance grace/history, payroll formulas/immutability, HR lifecycle, Gross PIECE/CARTON & currency lock — protected packs green.

---

## 8. Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

---

## 9. Remaining risks

| Risk | Status |
|---|---|
| RISK-GROSS-001 repair clamp | **UNCHANGED** (accepted) |
| Gap Policy 403 flip | Follow-up |
| Attendance `app(ShiftConfig)` | Accepted debt |
| SalesInvoiceController size | Follow-up |
| `teamMonthSummary` N× month | Mitigated cost; bulk path optional |
| Production MySQL latency unmeasured | Documented limitation |
| Index candidates | Awaiting owner approval |

---

## 10. Regression results

| Suite | Result |
|---|---|
| QueryBudget + HoursComparison + TL dashboard/approvals | PASS |
| Protected attendance/payroll/HR/Gross integrity | PASS |
| Full `php artisan test` | **953 PASS / 0 FAIL** |
| Pint `--dirty` | PASS |

---

## 11. Files changed

| Path | Change |
|---|---|
| `app/Services/Attendance/HoursComparisonService.php` | Eager load + batch rest/vacation |
| `app/Services/Attendance/AttendanceGapService.php` | Eager `teamLeaders` |
| `app/Services/Attendance/AttendanceTimeReviewService.php` | Eager segments/gaps |
| `app/Providers/AppServiceProvider.php` | `preventLazyLoading` in `local` |
| `tests/Feature/Performance/QueryBudgetTest.php` | **New** |
| `docs/refactoring/PHASE_7_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` / `REFACTORING_MASTER_PLAN.md` | Progress |

---

## 12. Task 2 overall verdict

Task 2 (Phases 0–7) completes as **PASS WITH CONDITIONS**:

- Architecture and safety improved across attendance, payroll, HR, Gross, cross-cutting, and measured performance.
- Conditions: RISK-GROSS-001 still accepted; production latency not measured; some follow-up debt remains.

**No further Task 2 phase without new owner authorization.**

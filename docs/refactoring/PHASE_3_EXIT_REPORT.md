# Task 2 Phase 3 — Exit report (Payroll integrity, freeze & privacy)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 3 only.  
**Phase 4:** Not started.

---

## 1. Payroll lifecycle map

```text
(none) --generate--> DRAFT --approve--> APPROVED
                         ^                |
                         |                v
                    (edit/refresh/     PAID (status exists for
                     adjustments)       employee visibility;
                                        no in-app mark-paid
                                        workflow — out of scope)
```

| Transition | Owner | Notes |
|---|---|---|
| Create/refresh DRAFT | `PayrollGenerator` + `PayrollDraftService` | Skips existing APPROVED/PAID unchanged |
| Edit counts / adjustments | `PayrollDraftService` | `PayrollWriteGuard` + `PayrollPolicy::update` |
| Approve | `PayrollGenerator::approve` | Manager-only; lockForUpdate; idempotent |
| DRAFT show | Recalc advances only (`syncAttendanceCounts=false`) | Does not rewrite day counts |

---

## 2. Financial rule ownership (canonical)

| Rule | Owner |
|---|---|
| Net / component money math | `PayrollCalculator` + `docs/PAYROLL_CALCULATION.md` |
| Attendance month facts | `PayrollAggregator` (`late_minutes_total` = date-aware exact; suggestion = punch raw) |
| Grace / historical late | `AttendanceGracePolicy` / `AttendanceService::exactLateMinutes` (Task 1.6) |
| Draft-only writes | `PayrollWriteGuard` (service) + `PayrollPolicy` (HTTP) |
| Advance sync on draft/approve | `PayrollDraftService` / `AdvanceService` |
| TL money privacy | `PayrollPolicy` + route `role:MANAGER,ACCOUNTANT` + menu config |

No competing calculators introduced.

---

## 3. Authorization matrix (verified)

| Actor | Index/show admin | Edit draft | Approve | Export PDF | Own salary APPROVED/PAID |
|---|---|---|---|---|---|
| MANAGER | yes | yes | yes | yes | n/a |
| ACCOUNTANT | yes | yes | **no** | yes | yes (own) |
| TEAM_LEADER | **no** | **no** | **no** | **no** | **no** |
| EMPLOYEE | **no** | **no** | **no** | own only | own only |
| ASSISTANT | **no** payroll routes | — | — | — | yes (own) |

---

## 4. Before / after architecture

| Area | Before | After |
|---|---|---|
| Draft write gate | `InvalidArgumentException` in DraftService | **`PayrollWriteGuard`** → `AuthorizationException` (403) |
| Controller update/storeAdjustment | FormRequest only | FormRequest **+** `$this->authorize('update')` |
| Approve concurrency | Simple status check | **`lockForUpdate`** + idempotent no-op for settled |
| Aggregator docs | Inline comments | Class-level ownership of late totals vs calculator |
| Tests | Partial settled coverage | **`PayrollImmutabilityTest`** (7 cases) |

Formulas: **unchanged**.

---

## 5. Confirmed findings & corrections

| Finding | Class | Action |
|---|---|---|
| Settled edit via service threw non-auth exception | Integrity / UX | WriteGuard → AuthorizationException |
| Missing controller authorize on update/storeAdjustment | Defense in depth | Added authorize calls |
| Concurrent double-approve could re-enter advances | Concurrency | lockForUpdate + status re-check |
| APPROVED→PAID UI/API not implemented | Product gap | Documented; **not** built (no new features) |
| API_DOCUMENTATION mark-paid | Doc drift | Noted as aspirational; no Phase 3 change to API doc unless needed |

No unauthorized broadening of access.

---

## 6. Files changed

| Path | Change |
|---|---|
| `app/Services/Payroll/PayrollWriteGuard.php` | **New** |
| `app/Services/Payroll/PayrollDraftService.php` | Use WriteGuard |
| `app/Services/Payroll/PayrollGenerator.php` | Locked idempotent approve |
| `app/Services/Payroll/PayrollAggregator.php` | Ownership docs |
| `app/Http/Controllers/Admin/PayrollController.php` | Authorize update/storeAdjustment |
| `tests/Feature/Admin/PayrollImmutabilityTest.php` | **New** |
| `docs/refactoring/PHASE_3_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` / `REFACTORING_MASTER_PLAN.md` | Progress |

---

## 7. Regression results

```text
Protected attendance/payroll (+ Phase 2/3 filters): 105 passed
Full suite: 932 passed (10097 assertions), 0 failed, 0 skipped
Pint: passed
php -l: clean
DATABASE CHANGES: NO
```

Phase 2 baseline was 925; +7 immutability tests → 932.

---

## 8. Remaining risks / follow-ups

- **PAID** is a visible status without an in-app transition; setting PAID today requires DB/seeder/factory — optional future feature under separate approval.
- `PayrollDraftService` / `PayrollGenerator` still share advance-sync helpers (acceptable duplication; extract later if needed).
- Excel export job auth relies on dispatcher user id — existing pattern; not changed.
- Phase 4 (HR policies) not started.

```text
TASK 2 — PHASE 3: PASS
PAYROLL ARCHITECTURE: IMPROVED
FINANCIAL FORMULAS: PRESERVED
APPROVED PAYROLL IMMUTABILITY: PASS
PAID PAYROLL IMMUTABILITY: PASS
PAYROLL PRIVACY: PASS
AUTHORIZATION: PASS
ATTENDANCE INTEGRATION: PASS
FULL REGRESSION: PASS
STATIC ANALYSIS: PASS
DATABASE GOVERNANCE: PASS
UNRESOLVED RISKS: No in-app APPROVED→PAID workflow (documented)
READY FOR PHASE 4: YES (pending owner approval)
```

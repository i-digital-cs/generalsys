# Task 1.6 completion report — Grace effective date & historical protection

**Date:** 2026-10-13  
**Owner approval:** implement effective-date correction + historical safeguards  
**Historical backfill:** **NO** (not authorized)

## A. Root cause

Task 1 wired `AttendanceGracePolicy` into recalculate/summarize/exactLate without gating on the attendance **workday**. Once deployed, any reopen/recalculate/summary path applied the 15-minute threshold to September (and earlier) rows — including financially settled periods — instead of only from **2026-10-01**.

## B. Files changed

| Path | Change |
|---|---|
| `config/attendance.php` | `grace_policy_effective_date` = `2026-10-01` |
| `app/Services/Attendance/AttendanceGracePolicy.php` | Date-aware `isActiveForAttendanceDate` + all grace APIs |
| `app/Services/Attendance/AttendanceTimeCalculator.php` | Freeze closed pre-Oct derived fields; historical summarize balance |
| `app/Services/Attendance/AttendanceService.php` | `exactLateMinutes` uses stored pre-Oct; counted post-Oct |
| `app/Services/Attendance/AttendanceHistoryService.php` | Kind gate respects workday policy |
| `app/Services/Payroll/PayrollAggregator.php` | Exact late date-aware; punch suggestion separate |
| `app/Http/Controllers/Admin/PayrollController.php` | Draft suggestion = punch late; live exact separate |
| `tests/Unit/Attendance/AttendanceGraceEffectiveDateTest.php` | Boundary + historical + payroll settled |
| `tests/Unit/Attendance/AttendanceGracePolicyTest.php` | Effective-date cases |
| Feature fixtures | October dates where grace UI is asserted |
| `docs/attendance/*` | Rules, matrix, this report |

## C. Historical protection

- Pre-Oct **read:** stored `late_minutes` / `early_departure_minutes` (override wins); no grace reclassification.
- Pre-Oct **write:** closed-day `recalculate` skips rewriting late/early/status.
- Reports spanning Sep/Oct resolve policy **per attendance workday**.
- No historical backfill executed.

## D. October policy (verified)

| Workday | Scenario | Result |
|---|---|---|
| 2026-09-30 | Stored late/early 10 | Unchanged; grace inactive |
| 2026-10-01 | 09:10–16:50 | Counted late/early **0**; balanced |
| 2026-10-02 | 09:16–17:00 | Counted late **16** |

## E. Payroll protection

- `AttendanceGraceEffectiveDateTest::test_approved_payroll_cannot_refresh_from_attendance` → 403; totals unchanged.
- `test_paid_payroll_cannot_be_edited` → 403; late total unchanged.
- `PayrollPolicy::update` requires DRAFT only.

## F. Regression tests

```text
php artisan test --filter='AttendanceGracePolicyTest|AttendanceGraceEffectiveDateTest|AttendanceTimeSummaryTest|AttendanceHistoryServiceTest|AttendanceTimeBreakdownTest|AttendanceMultiPunchTest|AttendanceServiceTest|PayrollDraftTest|HoursComparison'
→ 95 passed (387 assertions)
Failed: 0 | Skipped: 0 | Blocked: 0
```

## G. Database governance

No schema migrations. No data backfill. `AGENTS.md` §3.0 respected.

## H. Remaining risks

- Production/local DBs that already ran Task 1 recalculates on pre-Oct rows may already hold grace-adjusted values; this fix **stops further silent rewrites** but does **not** restore prior stored numbers (backfill not authorized).
- First-close of a brand-new pre-Oct row (rare) still uses legacy “late=0 unless override” — intentional freeze of inconsistent historical semantics.

## I. Final status

```text
EFFECTIVE-DATE POLICY: PASS
PRE-OCTOBER READ PROTECTION: PASS
PRE-OCTOBER WRITE PROTECTION: PASS
OCTOBER GRACE POLICY: PASS
SETTLED PAYROLL PROTECTION: PASS
REGRESSION TESTS: PASS
DATABASE GOVERNANCE: PASS
HISTORICAL BACKFILL EXECUTED: NO
READY FOR TASK 2: NO
```

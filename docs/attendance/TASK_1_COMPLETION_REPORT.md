# Task 1 completion report — Attendance grace & hours classification

**Date:** 2026-10-13  
**Status:** PASS WITH CONDITIONS (no historical backfill)

## A. Root cause

1. **No arrival/departure grace:** early departure stored as full raw minutes; late often left at 0 until Admin override (`exactLateMinutes` was raw).
2. **Deficit UI used raw duration delta** (`overtimeDeltaMinutes &lt; 0`) and the attendance register labeled **shortfall** whenever **credited &lt; planned**, so grace-eligible short days (e.g. +10 / −10 within 15) showed as deficit/نقص even when policy requires balanced.
3. **Surplus vs deficit:** register never distinguished surplus; credited-cap days with raw surplus could be misread when comparing the wrong field.

## B. Files changed

| Path | Reason |
|---|---|
| `app/Services/Attendance/AttendanceGracePolicy.php` | Canonical threshold + duration balance |
| `config/attendance.php` | `arrival_grace_minutes` / `departure_grace_minutes` |
| `app/Services/Attendance/AttendanceTimeCalculator.php` | Persist counted late/early; summarize balance |
| `app/Services/Attendance/AttendanceTimeSummary.php` | Policy undertime / classification |
| `app/Services/Attendance/AttendanceService.php` | `exactLateMinutes` + raw helpers |
| `app/Services/Attendance/AttendanceHistoryService.php` | Register kind: shortfall / surplus via policy |
| Blade metrics / timeline / TL attendance filter | Labels نقص / متوازن / زيادة |
| `lang/{en,ar}/attendance*.php` | Classification + surplus kind |
| `MASTER_CONTEXT.md` | Protected invariant |
| `docs/attendance/*` | Rules, matrix, tests, this report |
| Unit/feature tests listed in test matrix | Regression + policy examples |

## C. Before vs after (examples)

| Scenario | Before | After |
|---|---|---|
| 09:10–16:50 (480 planned) | ~20 min short / shortfall | counted late/early 0; deficit 0; **balanced** |
| 09:16–17:00 | raw late 16 | counted late **16**; deficit **16** |
| 08:45–17:30 | often “present” only | kind **surplus**; not auto OT |
| Clock-in 20 min late (open) | flexible start kept late 0 | still late 0 while open; counted on close |

## D. Consumer consistency

See `ATTENDANCE_CONSUMER_MATRIX.md`. Month `shortfall_raw` / `shortfall_credited` remain physical facts for payroll hours tip (documented, not redefined).

## E. Test evidence

```text
php artisan test --filter='AttendanceGracePolicyTest|AttendanceTimeSummaryTest|AttendanceHistoryServiceTest|AttendanceTimeBreakdownTest|AttendanceMultiPunchTest|AttendanceServiceTest|PayrollDraftTest'
→ 82 passed
```

## F. Remaining risks

- **Historical closed rows** may still show pre-grace early minutes until recalculated — no backfill without owner approval.
- Multi-attendance days sum raw late/early across rows (acceptable; rare EXTRA+FT same day).
- Gaps / unpaid breaks still reduce raw hours; grace only exempts boundary late/early, not mid-shift gaps (existing product rule).

## G. Final status

```text
TASK 1: PASS WITH CONDITIONS
GRACE PERIOD POLICY: VERIFIED
WORKING HOURS CLASSIFICATION: VERIFIED
ATTENDANCE CONSUMER CONSISTENCY: VERIFIED
REGRESSION TESTS: PASS
READY FOR TASK 2: NO (await owner approval; historical backfill optional)
```

## H. Database migration gate (Task 1 code — no schema)

Task 1 changed calculation/services/UI only. Any **historical attendance recalculate** (Task 1.5 / later) must follow `AGENTS.md` §3.0 and `docs/DEPLOYMENT.md` (version-controlled command, approval, no silent `migrate` backfill of payroll).

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

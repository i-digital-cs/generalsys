# Task 2 Phase 2 — Exit report (Attendance architecture)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 2 only.  
**Phase 3:** Not started.

---

## Before → after

| Component | Before | After |
|---|---|---|
| `AttendanceService` | ~1009 LOC; clock + windows + late helpers + dead private status/score | **847 LOC**; clock orchestration + facades; windows delegated |
| Shift windows | Private methods on `AttendanceService` | **`ShiftWindowResolver`** (196 LOC) — SSOT for expected start/end / align |
| Clock UI assembly | Fat `ClockController::show` (~80 lines of queries/branching) | **`ClockPageService`** (115 LOC); controller `show` is one view call |
| `ClockController` | 302 LOC | **225 LOC** |
| Grace / recalculate | Unchanged | Unchanged (`AttendanceGracePolicy`, `AttendanceTimeCalculator`) |
| Planned hours | `ShiftConfig` | Still `ShiftConfig` (not duplicated into window resolver beyond align) |

Public `AttendanceService` window APIs (`expectedStartFor`, `expectedEndFor`, `expectedEndForAttendance`, late helpers) **preserved** as facades.

---

## Extracted responsibilities

1. **`ShiftWindowResolver`** — assignment/profile windows, overnight rollover, `alignEndWithPlannedHours`.  
2. **`ClockPageService`** — clock page read model (still calls `closeStaleOpenBeforeShiftStart` via `AttendanceService` — intentional side effect of opening the page).  
3. Removed unused private `computeEarlyDepartureMinutes` / `resolveStatus` / `attendanceScore` from `AttendanceService` (logic already lives in `AttendanceTimeCalculator`). Kept `computeLateMinutes` stub (returns 0) — still used on create for flexible start.

---

## Files changed

| Path | Role |
|---|---|
| `app/Services/Attendance/ShiftWindowResolver.php` | **New** |
| `app/Services/Attendance/ClockPageService.php` | **New** |
| `app/Services/Attendance/AttendanceService.php` | Inject resolver; delegate; trim dead code |
| `app/Http/Controllers/Employee/ClockController.php` | Use `ClockPageService` |
| `tests/Unit/Attendance/ShiftWindowResolverTest.php` | **New** characterization |
| `docs/attendance/ATTENDANCE_CONSUMER_MATRIX.md` | Consumer rows |
| `docs/refactoring/PHASE_2_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` | Progress log |
| `docs/refactoring/REFACTORING_MASTER_PLAN.md` | Phase 2 marked done |

---

## Duplicate logic

- Window math no longer duplicated as private copies inside `AttendanceService`.  
- Planned-hours SSOT remains **`ShiftConfig`** (distinct from physical shortfall / policy deficit — not collapsed).  
- `Attendance` model still uses `app(ShiftConfig::class)` for Blade helpers — **remaining debt** (safe to leave; changing accessors risks view breakage without payoff).

---

## Tests

| Suite | Result |
|---|---|
| Protected attendance/payroll + Clock + ShiftWindowResolver | **119 passed** (targeted filter) |
| Full `php artisan test` | **925 passed** (10073 assertions), 0 failed, 0 skipped |
| Pint | passed |
| `php -l` on touched PHP | clean |

---

## Performance

No intentional query changes beyond consolidating today-attendance lookups inside `ClockPageService` (same queries as before). No N+1 investigation in this phase.

---

## Database

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS: NOT APPLICABLE
HISTORICAL BACKFILL: NO
```

---

## Invariants verified (via green suites)

- Task 1 / 1.6 grace effective date + historical freeze  
- Flexible start / multi-punch / overnight / clock HTTP  
- Payroll draft aggregation + settled protection tests in protected pack  

---

## Remaining technical debt / follow-ups

- `AttendanceService` still large (availability, stale-close, notifications) — further slice only with characterization.  
- Model `app(ShiftConfig::class)` accessors.  
- `HoursComparisonService` still large (Phase 2 out of scope for deep extract).  
- Phase 3: payroll boundary freeze (not started).

---

## Risks

| Risk | Mitigation |
|---|---|
| Facade drift vs resolver | `ShiftWindowResolverTest::test_facade_matches_attendance_service_expected_end` |
| Circular DI | Resolver depends only on `ShiftConfig`; ClockPage → AttendanceService one-way |

```text
TASK 2 — PHASE 2: PASS
ATTENDANCE ARCHITECTURE: IMPROVED
BUSINESS BEHAVIOR: PRESERVED
HISTORICAL PROTECTION: PASS
PAYROLL REGRESSION: PASS
FULL TEST SUITE: PASS
STATIC ANALYSIS: PASS (php -l + Pint)
DATABASE GOVERNANCE: PASS
UNRESOLVED RISKS: Model ShiftConfig service-locator; AttendanceService still >300 LOC
READY FOR PHASE 3: YES (pending owner approval)
```

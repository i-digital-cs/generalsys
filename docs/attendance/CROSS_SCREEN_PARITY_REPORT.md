# Cross-screen attendance parity report

## Method

Automated assertions in:

- `tests/Feature/Attendance/CrossScreenAttendanceParityTest.php`
- `tests/Unit/Attendance/AttendanceCalculationEngineTest.php`

Canonical source per session: `AttendanceCalculationEngine::forAttendance` → `countedLateMinutes` / `countedEarlyMinutes`.

Compared consumers for the same employee / date / policy:

1. Attendance history (`AttendanceHistoryService`)
2. Hours / dashboard month totals (`HoursComparisonService`)
3. Payroll live aggregate (`PayrollAggregator`) — draft path only
4. Engine result (employee/admin review equivalent)

Screens that only render service integers (employee clock, TL attendance list, HR review Blade) inherit parity once services agree.

## Fixture coverage

| Fixture | Covered |
|---|---|
| Mohamed Reda (overnight) | Yes — named fixture |
| Mohamed Amir (day) | Yes |
| Rahma (day, within grace) | Yes |
| Normal daytime | Yes (Amir / Rahma / unit suite) |
| Overnight | Yes (Reda) |
| Missing checkout | Yes — open provenance |
| Grace 15 vs 16 | Yes — unit engine test |
| Finalized payroll immutable | Yes — APPROVED snapshot vs live aggregate |
| Month aggregate = sum of sessions | Yes |
| Multiple shifts / segments | Covered by existing forensic + time summary suites (not re-duplicated here) |
| Auto-closed / supervisor | Provenance unit tests + prior forensic suite |
| Historical effective date | Existing `PayrollLateMinutesFromClockTest` / grace date tests |

## Results

See `docs/attendance/SSOT_REGRESSION_RESULTS.md` for command output and PASS/FAIL. Parity tests must be green for acceptance.

## Naming note

Where a screen shows “physical shortfall hours” (`shortfall_raw`) that is a **different metric** from grace `counted_deficit_minutes` — documented in SSOT architecture glossary.

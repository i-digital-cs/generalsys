# Attendance consumer matrix (Task 1 + Task 1.6)

All grace consumers must resolve policy via `AttendanceGracePolicy::isActiveForAttendanceDate($attendance->date)` (workday). Never use “today” or a report-wide global switch.

| Consumer | Behavior | Pre-Oct | From 2026-10-01 |
|---|---|---|---|
| **`AttendanceCalculationEngine`** | **SSOT façade** → typed `AttendanceCalculationResult` | wraps same domain | wraps same domain |
| `AttendanceGracePolicy` | Canonical thresholds + duration balance | inactive (fail-safe) | active |
| `ShiftWindowResolver` | Expected start/end + planned-hours align (Phase 2) | same for all dates | same |
| `ShiftConfig` | Planned hours SSOT (assignment → profile → type) | same | same |
| `ClockPageService` | Clock screen read-model; late via engine | — | — |
| `AttendanceTimeCalculator::recalculate` | Persist counted late/early/status | freeze closed derived fields | grace counted |
| `AttendanceTimeCalculator::summarize` | Display / classification | stored late/early + historical balance | grace balance |
| `AttendanceService::exactLateMinutes` | Payroll / month late | stored (override wins) | counted late |
| `AttendanceService::exactEarlyMinutes` | Month early totals (SSOT with late) | stored | counted early |
| `AttendanceService::rawLateMinutes` / `rawEarlyMinutes` | Punch facts | always raw | always raw |
| `AttendanceHistoryService` | day kind + late/early via engine sums | legacy shortfall; stored late path via exact* | policy deficit/surplus; exact* |
| Clock / dashboard / report Blade | deficit/balanced/surplus | stored / historical summarize | grace summarize |
| `HoursComparisonService` shortfall_raw / shortfall_credited | physical planned−hours | unchanged | unchanged |
| `HoursComparisonService` late/early totals | `exactLateMinutes` / `exactEarlyMinutes` | stored path | counted path |
| `PayrollAggregator::late_minutes_total` | sum `exactLateMinutes` | stored path | counted path |
| `PayrollAggregator::suggested_late_minutes_total` | draft UI tip + apply-suggested | exact late (grace SSOT) | exact late (same as `late_minutes_total`) |
| `PayrollAggregator::suggested_early_departure_minutes_total` | draft help / transparency | exact early | exact early |
| `PayrollAggregator::raw_late_minutes_total` | diagnostic only | punch raw | never for draft apply |
| OT suggestion | raw surplus | unchanged | unchanged |
| Settled payroll refresh/edit | policy DRAFT-only | blocked APPROVED/PAID | blocked APPROVED/PAID |
| Auto clock-out grace (60m) | separate config | unchanged | unchanged |

Every UI classification of deficit/surplus must go through `AttendanceGracePolicy` or `AttendanceTimeSummary` / `AttendanceCalculationEngine` — do not re-implement thresholds in Blade.

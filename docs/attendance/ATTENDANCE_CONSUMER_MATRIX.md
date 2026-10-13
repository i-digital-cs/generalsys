# Attendance consumer matrix (Task 1 + Task 1.6)

All grace consumers must resolve policy via `AttendanceGracePolicy::isActiveForAttendanceDate($attendance->date)` (workday). Never use “today” or a report-wide global switch.

| Consumer | Behavior | Pre-Oct | From 2026-10-01 |
|---|---|---|---|
| `AttendanceGracePolicy` | Canonical thresholds + duration balance | inactive (fail-safe) | active |
| `AttendanceTimeCalculator::recalculate` | Persist counted late/early/status | freeze closed derived fields | grace counted |
| `AttendanceTimeCalculator::summarize` | Display / classification | stored late/early + historical balance | grace balance |
| `AttendanceService::exactLateMinutes` | Payroll / month late | stored (override wins) | counted late |
| `AttendanceService::rawLateMinutes` / `rawEarlyMinutes` | Punch facts | always raw | always raw |
| `AttendanceHistoryService::kind` | shortfall / surplus / present | legacy shortfall semantics | policy deficit/surplus |
| Clock / dashboard / report Blade | deficit/balanced/surplus | stored / historical summarize | grace summarize |
| `HoursComparisonService` shortfall_raw / shortfall_credited | physical planned−hours | unchanged | unchanged |
| `PayrollAggregator::late_minutes_total` | sum `exactLateMinutes` | stored path | counted path |
| `PayrollAggregator::suggested_late_minutes_total` | draft UI tip only | punch raw | punch raw |
| OT suggestion | raw surplus | unchanged | unchanged |
| Settled payroll refresh/edit | policy DRAFT-only | blocked APPROVED/PAID | blocked APPROVED/PAID |
| Auto clock-out grace (60m) | separate config | unchanged | unchanged |

Every UI classification of deficit/surplus must go through `AttendanceGracePolicy` or `AttendanceTimeSummary` derived from it — do not re-implement thresholds in Blade.

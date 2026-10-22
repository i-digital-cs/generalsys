# Attendance calculation consumer inventory

Inventory date: 2026-10-22 · Scope: full application (services, HTTP, Blade, payroll, jobs)

## Dependency map (authoritative → consumers)

```
AttendanceCalculationEngine
 ├─ AttendanceTimeCalculator::summarize → AttendanceTimeSummary
 ├─ AttendanceService::exactLateMinutes / exactEarlyMinutes
 ├─ AttendanceService::rawLateMinutes / rawEarlyMinutes
 ├─ AttendanceGracePolicy
 └─ CheckoutProvenance
      │
      ├─ AttendanceHistoryService (day late/early sums, duration balance)
      ├─ ClockPageService (open-session late display)
      ├─ ClockController (report reminder late/early)
      ├─ HoursComparisonService (month late/early totals + interval meta)
      ├─ PayrollAggregator (late_minutes_total via exactLateMinutes)
      ├─ Attendance review / punch-range Blade (summary components)
      └─ Parity tests (CrossScreenAttendanceParityTest)
```

Persistence path (write, not display): `AttendanceService` clock in/out → `AttendanceTimeCalculator::recalculate`.

## Discovered calculation sites

| Location | What it did | SSOT disposition |
|---|---|---|
| `AttendanceTimeCalculator` | Late/early/hours/balance | **Authoritative** persist + summarize |
| `AttendanceGracePolicy` | Grace + duration balance | **Authoritative** |
| `AttendanceService::exactLateMinutes` | Payroll/reporting late | **Authoritative** counted late |
| `AttendanceService::exactEarlyMinutes` | **Added** counted early | **Authoritative** counted early |
| `AttendanceService::rawLate/Early` | Punch facts | **Authoritative** raw |
| `AttendanceHistoryService::rowForDay` | Summed **stored** `late_minutes` / `early_departure_minutes` | **Refactored** → engine `sumExact*` |
| `HoursComparisonService` month totals | Late via exact; early via **stored** | **Refactored** → `exactEarlyMinutes` |
| `HoursComparisonService::actualForDay` | Interval late/early from stored | **Refactored** → exact* |
| `ClockController::reportReminderPayload` | Stored late/early | **Refactored** → engine |
| `ClockPageService` | Stored `open->late_minutes` | **Refactored** → engine |
| `PayrollAggregator` | Already `exactLateMinutes` | Keep (domain primitive) + warm assignments |
| Blade dashboards / TL attendance | Display `$row['late_minutes']` from services | Consumer only (no math) |
| `attendance-time-metrics` / `breakdown` | Direct `AttendanceTimeCalculator::summarize` + stored applied late | **Refactored** → Engine; applied = exact* |
| `PortalStatsService::distinctLate` | SQL `late_minutes > 0` | **Refactored** → exactLate per today row |
| `PayrollExportService` | Exports snapshot `late_minutes_total` | Snapshot consumer (OK) |
| `AbsenceAttendanceApplier` | Seeds zeros on synthetic rows | Write path, not display calc |
| JS (payroll edit tip) | Copies suggested late into input | UI only; suggestion from aggregator |

## Intentional non-interchangeable metrics

| Metric | Producer | Used by |
|---|---|---|
| `shortfall_raw` / `shortfall_credited` | HoursComparison | Dashboards (physical hours gap) |
| Grace `counted_deficit_minutes` | Grace duration balance | History kind shortfall/surplus |
| `suggested_late_minutes_total` | PayrollAggregator punch raw | Draft payroll UI tip only |
| Settled `payroll_records.late_minutes_total` | Snapshot at approve | Exports / payslips |

## Not independent calculators

Controllers under `app/Http/Controllers` for attendance/payroll do not implement grace math after this refactor. Blade uses service-provided integers and `<x-*>` summary components.

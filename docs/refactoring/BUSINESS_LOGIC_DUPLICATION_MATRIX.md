# Business logic duplication matrix — Task 2 Phase 0

**Rule:** Prefer consolidation only where duplicates disagree or drift. Do not invent abstractions for one consumer.

Protected attendance/payroll invariants: `docs/attendance/ATTENDANCE_BUSINESS_RULES.md`.

---

## D-01 — Late minutes (counted vs raw vs stored)

| | |
|---|---|
| **Canonical** | `AttendanceGracePolicy` + `AttendanceService::exactLateMinutes` / `rawLateMinutes`; persist via `AttendanceTimeCalculator::recalculate` |
| **Duplicates / near-duplicates** | Historical: readers that still assumed “exact = punch when column 0” (`PayrollLateMinutesFromClockTest`); UI suggestion uses punch via `PayrollAggregator::suggested_late_minutes_total` |
| **Consumers** | PayrollAggregator, HoursComparisonService, AttendanceHistoryService, dashboards, draft payroll UI |
| **Behavioral differences** | Pre-Oct: exact = **stored** (Task 1.6). Post-Oct: exact = grace-counted punch. Suggestion = always punch raw |
| **Refactoring risk** | **High** if merged incorrectly |
| **Characterization tests** | `AttendanceGraceEffectiveDateTest`, `AttendanceGracePolicyTest`, `PayrollDraftTest` late suggestion; update `PayrollLateMinutesFromClockTest` to match Task 1.6 |
| **Strategy** | Keep single policy; document suggestion vs exact; fix outdated tests — do not reintroduce punch-as-exact for Sep |

## D-02 — Early departure minutes

| | |
|---|---|
| **Canonical** | `AttendanceGracePolicy::countedEarlyMinutes` + calculator persist / summarize |
| **Duplicates** | Historical stored early on closed pre-Oct rows; raw early helpers |
| **Consumers** | Summarize, history kind, TL metrics |
| **Differences** | Pre-Oct freeze vs Oct threshold |
| **Risk** | High |
| **Tests** | Grace policy + EffectiveDate + TimeSummary |
| **Strategy** | Same as D-01; no parallel threshold in Blade |

## D-03 — Planned hours resolution

| | |
|---|---|
| **Canonical (intended)** | `ShiftConfig::plannedHours` / `resolveForAttendance` |
| **Duplicates** | `User::plannedHoursFor`; `Attendance::resolvedPlannedHours` (service locator); `HoursComparisonService::plannedHoursForProfile`; `ShiftWindowValidator::plannedHoursForType`; cached `attendances.planned_hours` column + sync command/observers |
| **Consumers** | Clock, OT delta, payroll hours tip, dashboards |
| **Differences** | Live profile vs frozen column can disagree until recalculate/sync (known; tests cover live planned for OT) |
| **Risk** | Medium–High |
| **Tests** | `AttendanceTimeBreakdownTest` live profile; `HoursComparisonServiceTest`; sync command tests if any |
| **Strategy** | One resolver API; column = cache only; model accessors delegate without `app()` if possible |

## D-04 — Expected shift window (start/end)

| | |
|---|---|
| **Canonical** | `AttendanceService::expectedStartFor` / `expectedEndFor` / private `expectedStart`/`expectedEnd` |
| **Duplicates** | Assignment windows in `ShiftAssignmentService`; profile `work_start_time`/`work_end_time`; overnight align helpers |
| **Consumers** | Late/early, geofence outside-window notify, clock UI schedule preview |
| **Risk** | High |
| **Tests** | `AttendanceServiceTest` overnight / online half / flexible start |
| **Strategy** | Extract `ShiftWindowResolver` used by AttendanceService + Calculator (F-13) |

## D-05 — Hours balance classification (deficit / balanced / surplus)

| | |
|---|---|
| **Canonical** | `AttendanceGracePolicy::durationBalance` (+ historical stored path in calculator) |
| **Duplicates** | History “shortfall” kind legacy pre-Oct; `HoursComparisonService` physical `shortfall_raw` / `shortfall_credited` (documented as **different** — payroll tip facts) |
| **Consumers** | UI badges, register filters, summaries |
| **Differences** | Physical shortfall ≠ policy deficit (intentional) |
| **Risk** | Medium if renamed blindly |
| **Tests** | HistoryServiceTest, TimeSummaryTest, consumer matrix |
| **Strategy** | Keep names distinct; never replace shortfall_raw with grace deficit in payroll tips |

## D-06 — Payroll money calculation

| | |
|---|---|
| **Canonical** | `PayrollCalculator::calculate` + `docs/PAYROLL_CALCULATION.md` |
| **Duplicates** | Draft service recalculate orchestration; export formatting; Blade `<x-money>` |
| **Consumers** | Generator, draft update, approve, employee payslip |
| **Risk** | Critical if math drifts |
| **Tests** | `PayrollCalculatorTest` (fixture net 2808.00), `PayrollRegressionTest`, draft/export |
| **Strategy** | Never duplicate day-rate formulas in controllers/views |

## D-07 — Payroll late total aggregation

| | |
|---|---|
| **Canonical** | `PayrollAggregator::exactLateMinutesTotal` → `AttendanceService::exactLateMinutes` |
| **Duplicates** | Manual draft `late_minutes_total`; punch suggestion field |
| **Risk** | Medium |
| **Tests** | PayrollDraftTest, EffectiveDate approved refresh 403 |
| **Strategy** | Keep suggestion separate from applied total |

## D-08 — Permission penalties

| | |
|---|---|
| **Canonical** | `PermissionPenaltyCalculator` + force doubling in request service; transitions via `HrRequestWriteGuard` (Phase 4) |
| **Duplicates** | Config rates; historical auto-penalty flags (`payroll.auto_penalties=false` — money manual on draft) |
| **Risk** | Medium |
| **Tests** | `PermissionPenaltyCalculatorTest`, force/approval feature tests |
| **Strategy** | Keep calculator as single money-fact helper; UI must not invent rates |

## D-09 — Absence ↔ attendance application

| | |
|---|---|
| **Canonical** | `AbsenceAttendanceApplier` |
| **Duplicates** | None found at service layer beyond applier |
| **Risk** | Medium on extract |
| **Tests** | `AbsencePermissionTest` |
| **Strategy** | Keep next to permissions domain |

## D-10 — Vacation balance / rest-day swap

| | |
|---|---|
| **Canonical** | `VacationBalanceService` (locked balance rows) + `VacationRequestService` + `HrRequestWriteGuard` (Phase 4) |
| **Duplicates** | Credit UI vs rest-day cost semantics evolved (Gate 2.9 retired per BUILD_AND_TEST_GATES) |
| **Risk** | Medium |
| **Tests** | Vacation request/approval/credit tests |
| **Strategy** | Document current credit vs swap rules in MASTER_CONTEXT before renaming |

## D-11 — Gross stock movements

| | |
|---|---|
| **Canonical** | `StockService` (all runtime + `repairMoveOnHandToWithCourier`) |
| **Duplicates** | None for balance writes after Phase 5; `RepairGrossDeliveredStock` already via `markDelivered` |
| **Risk** | High |
| **Tests** | StockServiceTest, sales fulfillment pipeline, repair command tests |
| **Strategy** | All stock mutations through StockService; commands call service methods |

## D-12 — Gross sales invoice lifecycle

| | |
|---|---|
| **Canonical** | `SalesInvoiceService` façade + `SalesInvoiceFulfillmentService` + `SalesInvoiceCodCollectionService` (+ Net/Allocation/Treasury) |
| **Duplicates** | Controller still orchestrates many steps; display/export/import split (good) |
| **Risk** | High |
| **Tests** | SalesInvoice* feature suite |
| **Strategy** | Continue vertical extract (fulfillment, COD) without changing status machine |

## D-13 — Team scoping (multi-TL)

| | |
|---|---|
| **Canonical** | `TeamMembershipService` + query scopes on users |
| **Duplicates** | Ad-hoc `team_leader_id` / `ledBy` checks in multiple TL services |
| **Risk** | High (privacy) |
| **Tests** | `DualTeamScopeTest`, `DualTeamLeaderSchemaTest` |
| **Strategy** | One scope helper; policies call same helper |

## D-14 — Notifications

| | |
|---|---|
| **Canonical** | `AppNotificationService` |
| **Duplicates** | Many `Notification` classes; some direct notify from AttendanceService |
| **Risk** | Low–Medium |
| **Tests** | `NotificationTest` |
| **Strategy** | Keep facade service; avoid notifying from controllers |

---

## Modules with little duplication (observe only)

| Module | Notes |
|---|---|
| Evaluations | Mostly `EvaluationService` / `DailyScoreService` |
| Valuation | Calculator + module tests; Blade still duplicates display math |
| Product expense | Small service surface |
| Portal | `PortalStatsService` |

---

## Priority order for consolidation

1. D-01 / D-02 / D-05 (attendance invariants — stabilize tests, no behavior change)  
2. D-03 / D-04 (planned hours + windows — enable AttendanceService split)  
3. D-06 / D-07 (payroll — freeze calculator API)  
4. D-11 / D-12 (Gross — largest LOC, after attendance freeze)  
5. D-13 (scoping + policies)  
6. Remainder opportunistic

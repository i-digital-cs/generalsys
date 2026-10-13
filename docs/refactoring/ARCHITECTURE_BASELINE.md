# Architecture baseline — Task 2 Phase 0

**Date:** 2026-10-13  
**Scope:** Read-only inventory of the live codebase. No application code or DB changes in Phase 0.  
**Stack:** PHP 8.2 / Laravel 11 · Blade + Bootstrap 5 (RTL) · MySQL 8 · Hostinger shared hosting.

## 1. Sources consulted

| Document | Status |
|---|---|
| `AGENTS.md` | Present — mandatory engineering rules |
| `ENGINEERING_CONSTITUTION.md` | Present — index to invariants |
| `MASTER_CONTEXT.md` | Present — product + hosting |
| `DESIGN.md` | **Absent** |
| `docs/TASKS.md` | Present — task board + progress log |
| ADRs | **None found** under repo |
| `docs/attendance/*` (rules, consumer matrix, Task 1 / 1.6 reports) | Present |
| `docs/DEPLOYMENT.md` + `AGENTS.md` §3.0 | Present — DB governance |

Docs were cross-checked against `app/` (LOC, constructors, policies, tests). Where docs and code diverge, **code + failing tests** win for baseline risk.

## 2. Size snapshot

| Area | Measure |
|---|---|
| `app/**/*.php` | ~37,567 LOC |
| `tests/**/*.php` | ~26,477 LOC |
| Blade views | 164 files |
| Eloquent models | 54 |
| Policies | 17 |
| Test classes (`*Test.php`) | 155 |
| Service domains under `app/Services/` | 18 folders |

## 3. Module inventory

| Domain | Controllers | Services (key) | Policies | Notes |
|---|---|---|---|---|
| **Attendance / clock** | `Employee/ClockController`, TL attendance review/history, photos | `AttendanceService` (1009), `AttendanceTimeCalculator`, `AttendanceGracePolicy`, `HoursComparisonService` (770), history/gap/photo/shift | Partial (`AttendancePhotoPolicy`, `ShiftAssignmentPolicy`); no `AttendancePolicy` | Protected Task 1 / 1.6 invariants |
| **Payroll** | `Admin/PayrollController` (333) | `PayrollCalculator`, `PayrollAggregator`, `PayrollDraftService`, `PayrollGenerator`, export/jobs | `PayrollPolicy` | TL 403 on money; APPROVED/PAID immutable |
| **Employees / HR admin** | `Admin/EmployeeController`, documents, rest days, settings | `Employees/*`, `Employee/EmployeeDashboardService` | `EmployeePolicy` | Split `Employee` vs `Employees` namespaces |
| **Permissions (leave/late)** | Employee requests, TL approvals | `PermissionRequestService`, `PermissionPenaltyCalculator`, `AbsenceAttendanceApplier` | Route/role middleware; no dedicated Policy class | |
| **Vacations** | Requests, approvals, credit | `VacationRequestService`, `VacationBalanceService` | Middleware / service scoping | |
| **Evaluations / scores** | TL + employee evaluation | `EvaluationService`, `DailyScoreService` | Middleware | |
| **Approvals inbox** | `TeamLeader/ApprovalController` (203) | `ApprovalsListFilters` | Thin filter helper; logic in controller + other services | |
| **Dashboard / portal** | Multiple `DashboardController`s, `PortalController` | `DashboardService`, calendars, `PortalStatsService` | Role middleware | Duplicate controller names across namespaces |
| **Gross (ERP)** | Large `SalesInvoiceController` (585), purchase/stock/treasury/… | `SalesInvoiceService` (978), `StockService` (711), `ItemService` (643), many siblings | Gross* policies | Highest complexity concentration |
| **Valuation** | Sheet + assignment | `ValuationCalculator`, copy/stats | Valuation policies | Heavy `@php` in Blade |
| **Product expense** | Module controller | `ProductExpenseService` | ProductExpense policies | Baseline test failure on 403 vs 302 |
| **Auth / impersonation** | Auth + Admin impersonation | `ImpersonationService`, `PostLoginRedirector` | | |
| **Notifications** | `NotificationController` | `AppNotificationService` (376) | | |
| **Reports** | Annual report | `ReportService` | | |
| **Observers / jobs / commands** | — | Shift profile + assignment observers; payroll jobs; attendance repair/sync commands | | Recalculate entry points must respect grace effective date |

## 4. Intended architecture (from AGENTS.md)

1. Controllers: validate (Form Request) → authorize (Policy) → call Service → response.  
2. Services own business rules; constructor DI; no god classes (~300 lines guideline).  
3. Models thin: casts, relations, accessors/scopes — not calculations.  
4. Hostinger: no Redis/Supervisor/Node-on-server; queues `database`; heavy work chunked jobs.

## 5. Observed architecture (as-built)

**Strengths**

- Clear `app/Services/<Domain>/` layout for most features.
- Payroll math centralized in `PayrollCalculator` + documented fixtures.
- Attendance grace centralized in `AttendanceGracePolicy` with workday effective date (Task 1.6).
- Broad automated suite (914+ passing tests on full run).
- Form Requests widely used; Gross and Payroll policies exist for money-sensitive paths.

**Gaps vs AGENTS.md**

| Rule | Evidence |
|---|---|
| Controllers thin | `SalesInvoiceController` 585 LOC; `ClockController` 302 LOC with query assembly and preferred-shift branching |
| Services ≤ ~300 LOC | Multiple services ≫ 300: AttendanceService 1009, SalesInvoiceService 978, HoursComparison 770, Stock 711, Item 643 |
| Models thin / no calculations | `Attendance::resolvedPlannedHours` / `overtimeDeltaMinutes` call `app(ShiftConfig::class)` (service locator) |
| Authorization always via Policy | Many controllers rely only on route `role:` middleware; no `AttendancePolicy`; TL controllers often lack `$this->authorize` |
| No repositories | Confirmed — query logic lives in services (acceptable for Laravel; some services mix query + domain heavily) |

## 6. Critical protected invariants (must not change in refactor)

See `docs/attendance/ATTENDANCE_BUSINESS_RULES.md` and Task 1.6 report:

- Workday `< 2026-10-01`: historical stored late/early/status; no new grace; closed recalculate freeze.
- Workday `≥ 2026-10-01`: 15-min threshold grace; deficit/balanced/surplus; surplus ≠ auto OT.
- APPROVED/PAID payroll immutable.
- TL never sees monetary payroll amounts (`PayrollPolicy` + UI defense in depth).

## 7. Hosting / deploy constraints (refactor envelope)

- No new infra requiring Redis, Supervisor, or server-side npm.
- Schema only via new migrations (`AGENTS.md` §3.0).
- Historical attendance reconciliation **out of scope** for Task 2 unless separately approved.

## 8. Baseline conclusion

The system is a **modular monolith** with good domain folders but **uneven SRP**: attendance and Gross concentrate god-service + fat-controller risk. Phase 1+ should extract seams inside those domains without changing protected behavior.

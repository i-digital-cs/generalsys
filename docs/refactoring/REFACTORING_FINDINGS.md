# Refactoring findings — Task 2 Phase 0

**Date:** 2026-10-13  
**Method:** Static inspection of `app/`, `routes/`, `resources/views/`, plus full `php artisan test` baseline.  
**Severity:** Critical / High / Medium / Low  
**Refactoring risk:** High / Medium / Low (chance of behavior break)

---

## F-01 — God service: `AttendanceService`

| | |
|---|---|
| **Evidence** | `app/Services/Attendance/AttendanceService.php` — **1009 LOC**, **27** public methods; clock, stale-close, expected windows, late math, shift availability, notifications coupling |
| **Severity** | High |
| **Risk** | High if split carelessly (clock + grace consumers) |
| **Notes** | Constructor already DI-heavy (`ShiftConfig`, `RestDayService`, `DailyReportService`, `AppNotificationService`, `GeofenceService`, `AttendanceTimeCalculator`, `AttendanceGapService`, `AttendanceGracePolicy`). Candidate extract: shift-window resolver, stale-close, availability — keep clock orchestration thin. |

## F-02 — God service: `SalesInvoiceService` + fat controller

| | |
|---|---|
| **Evidence** | `SalesInvoiceService` **978 LOC**; `SalesInvoiceController` **585 LOC**, **25** `authorize` calls, many fulfillment/COD/media paths |
| **Severity** | High |
| **Risk** | High (stock + treasury side effects) |
| **Notes** | Partial extract already exists (`SalesInvoiceNetService`, Display/Export/Import/Media). Next: fulfillment pipeline + COD collection as dedicated services; shrink controller to HTTP mapping only. |

## F-03 — Oversized: `HoursComparisonService`, `StockService`, `ItemService`

| | |
|---|---|
| **Evidence** | 770 / 711 / 643 LOC respectively under Attendance/Gross |
| **Severity** | High |
| **Risk** | Medium–High |
| **Notes** | HoursComparison duplicates planned-hours resolution vs `ShiftConfig` / model helpers. Stock mixes movements, balances, repairs. |

## F-04 — Fat / query-heavy `ClockController`

| | |
|---|---|
| **Evidence** | `app/Http/Controllers/Employee/ClockController.php` **302 LOC**; `show()` runs `Attendance::query()` for auto-clock-out + today’s attendances, preferred-shift `if` branches (lines ~35–76+) |
| **Severity** | Medium |
| **Risk** | Medium |
| **Notes** | Move “clock page DTO” assembly into a dedicated read service; keep mutations in `AttendanceService`. |

## F-05 — Business logic on Eloquent model via service locator

| | |
|---|---|
| **Evidence** | `Attendance::resolvedPlannedHours` / `overtimeDeltaMinutes` → `app(ShiftConfig::class)` (`app/Models/Attendance.php` ~164–175) |
| **Severity** | Medium |
| **Risk** | Medium (call sites in Blade/tests may rely on accessors) |
| **Notes** | Violates AGENTS “models thin / no calculations”. Prefer explicit service or keep accessors but inject via intentional API documented as presentation helpers. |

## F-06 — Authorization inconsistency (Policy vs role middleware)

| | |
|---|---|
| **Evidence** | Policies exist for Payroll, Employee, Gross invoices, Valuation, etc. Many TL/Employee controllers omit `$this->authorize` (e.g. `ClockController`, `ApprovalController`, attendance review/history). Team scoping often inside services. |
| **Severity** | High (defense-in-depth gap) |
| **Risk** | Medium (behavior may already be correct via middleware + service scopes) |
| **Notes** | Refactor: add policies for Attendance / Permission / Vacation without changing allow/deny matrix; characterize with 403 tests first. |

## F-07 — Namespace / naming duplication

| | |
|---|---|
| **Evidence** | `app/Services/Employee` vs `Employees`; multiple `DashboardController`, `EvaluationController`, `AdvanceController`, `RestDayController` |
| **Severity** | Low–Medium |
| **Risk** | Low |
| **Notes** | Confusing for agents; consolidate namespaces in a low-risk rename phase with IDE/refs. |

## F-08 — Blade `@php` / presentation logic concentration

| | |
|---|---|
| **Evidence** | `@php` in valuation sheets, TL attendance/approvals, dashboards, portal (30+ views). Valuation score/month stats compute in view. |
| **Severity** | Medium |
| **Risk** | Medium |
| **Notes** | Prefer ViewModels / service-prepared props; especially valuation and attendance badge classification (must keep using `AttendanceGracePolicy` / summary). |

## F-09 — Recalculate entry-point sprawl (attendance write paths)

| | |
|---|---|
| **Evidence** | `AttendanceTimeCalculator::recalculate`; observers `UserShiftProfileObserver`, `ShiftAssignmentPlannedHoursObserver`; commands `SyncAttendancePlannedHoursCommand`, `RepairStaleOneMinuteClosesCommand`; TL time review |
| **Severity** | High for historical safety |
| **Risk** | High if observers reintroduced without date gate |
| **Notes** | Task 1.6 freeze must remain; any extract must pass `AttendanceGraceEffectiveDateTest`. |

## F-10 — Outdated characterization tests vs protected invariants

| | |
|---|---|
| **Evidence** | Full suite: `PayrollLateMinutesFromClockTest` expects punch late on **2026-09-*** when `late_minutes=0` → fails (0≠104 / 20≠50) after Task 1.6 stored-path. `DualDefaultShiftsTest` expects `STATUS_LATE` on clock-in Aug 2026 → gets `PRESENT` (flexible start). |
| **Severity** | High for **baseline honesty** (not a product regression of Task 1.6) |
| **Risk** | Low if tests updated to match approved policy; High if code “fixed” to old expectations |
| **Notes** | Phase 1 must **not** revert grace effective-date to make these green. Update tests or mark as intentional historical semantics. |

## F-11 — Pre-existing full-suite failures (non–Task-1.6)

| | |
|---|---|
| **Evidence** | `PayrollPrivacyTest` TL sidebar still sees `nav.payroll` string; `GrossHardeningTest` draft mutation expectation; `PurchaseInvoiceFlowTest` sales user 200 vs 403 on purchases index; `ProductExpenseModuleTest` 302 vs 403 |
| **Severity** | Medium–High (auth/UI drift) |
| **Risk** | Medium |
| **Notes** | Treat as Phase 0 known defects / test drift — separate from architecture extract. Do not “fix green” by weakening assertions without product decision. |

## F-12 — No ADRs / no DESIGN.md

| | |
|---|---|
| **Evidence** | Glob find: zero ADR files; no `DESIGN.md` |
| **Severity** | Low (process) |
| **Risk** | Low |
| **Notes** | Phase 0 docs under `docs/refactoring/` become the working architecture record; optional ADR template later. |

## F-13 — Circular / tight coupling (Attendance ↔ Calculator)

| | |
|---|---|
| **Evidence** | `AttendanceService` constructs with `AttendanceTimeCalculator`; calculator methods take `AttendanceService` as argument for expected windows — mutual knowledge without PHP circular constructor (good), but high cognitive coupling |
| **Severity** | Medium |
| **Risk** | Medium |
| **Notes** | Extract `ShiftWindowResolver` interface used by both. |

## F-14 — Missing transactions / race notes (observation)

| | |
|---|---|
| **Evidence** | Clock-in uses `DB::transaction` in `AttendanceService` (good). Gross confirm/stock paths use transactions in services — verify per-method during Gross extract. Shared hosting + database queues → long jobs must stay chunked (`GeneratePayrollJob`). |
| **Severity** | Medium |
| **Risk** | Medium |
| **Notes** | Characterization tests around concurrent double clock-in already exist (`second open rejected`). |

## F-15 — Dead / obsolete signals

| | |
|---|---|
| **Evidence** | No `TODO`/`FIXME`/`dd(`/`dump(` hits in `app/` + resources on Phase 0 scan. Auto clock-out largely disabled but command/notification remnants remain. |
| **Severity** | Low |
| **Risk** | Low |
| **Notes** | Confirm dead commands before deletion (AGENTS: no dead code). |

---

## Summary table

| ID | Theme | Severity | Refactor risk |
|---|---|---|---|
| F-01 | AttendanceService god class | High | High |
| F-02 | Sales invoice god + fat controller | High | High |
| F-03 | HoursComparison / Stock / Item size | High | Med–High |
| F-04 | ClockController assembly | Medium | Medium |
| F-05 | Model service locator | Medium | Medium |
| F-06 | Policy gaps | High | Medium |
| F-07 | Namespace duplication | Low–Med | Low |
| F-08 | Blade business-ish logic | Medium | Medium |
| F-09 | Recalculate sprawl | High | High |
| F-10 | Stale tests vs Task 1.6 | High (tests) | Low if tests fixed correctly |
| F-11 | Pre-existing suite failures | Med–High | Medium |
| F-12 | Missing ADRs | Low | Low |
| F-13 | Attendance coupling | Medium | Medium |
| F-14 | Transaction / race vigilance | Medium | Medium |
| F-15 | Obsolete auto-clock remnants | Low | Low |

# GH-9.1 — Test Failure Analysis

**Date:** 2026-10-31  
**Baseline GH-9 full suite:** 1195 passed / **4 failed** / 1 skipped  
**Post-remediation full suite:** **1203 passed** / **0 failed** / 1 skipped

---

## 1. DriverRoleTest (GH-related)

| Field | Detail |
|---|---|
| Severity | HIGH (release gate) |
| Exact assertion | `assertSame(['employee.clock','employee.report','employee.media'], $routes)` at `tests/Feature/DriverRoleTest.php:133` |
| Evidence | Actual `config('menu.DRIVER')` prepends `gh.driver.dashboard` (GH-7) |
| Root cause | Test outdated vs intentional DRIVER GH workspace menu |
| Caused by GH? | **Yes** (menu addition) |
| Authorization incorrect? | **No** — Manager-only GH manage unchanged; DRIVER workspace is scoped |
| Unauthorized modules? | **No** — only GH driver routes + existing HR clock/report/media |
| Fix | Updated expectation; PostLoginRedirector still `employee.clock` |
| Launch impact | Was blocking “full suite green”; now green |

---

## 2. Admin DashboardTest — month select

| Field | Detail |
|---|---|
| Severity | MEDIUM |
| Exact assertion | `assertSee('value="2026-09"')` at `tests/Feature/Admin/DashboardTest.php:174` |
| Evidence | On 2026-10-31, `Carbon::createFromFormat('Y-m','2026-09')->startOfMonth()` → **2026-10-01** (day-31 overflow) |
| Root cause | Classic Carbon `Y-m` without day anchor |
| Caused by GH? | **No** — predates GH; manifests on days 29–31 |
| Baseline | Pre-GH worktree lacked vendor; reproduced via isolated Carbon probe on TEST |
| Fix | `App\Support\YearMonth::start()` used in `MonthCalendarBuilder`, `ApprovalsListFilters`, `HoursComparisonService`, dashboard controllers |
| Attendance business rules changed? | **No** — date parsing only |
| Launch impact | Calendar/month filters now correct at month-end |

---

## 3. EmployeeDashboardTest — ONLINE_HALF

| Field | Detail |
|---|---|
| Severity | LOW |
| Exact assertion | `assertSee(__('shifts.types.ONLINE_HALF'))` at `EmployeeDashboardTest.php:139` |
| Evidence | Second shift used `now()->addDay()` → on Oct 31 = **Nov 1**, outside current-month calendar |
| Root cause | Date-sensitive test fixture |
| Caused by GH? | **No** |
| Fix | Pin both assignments inside `now()->startOfMonth()->addDays(2|5)` |
| Attendance business rules changed? | **No** |

---

## 4. ApprovalsFilterTest — rejected + month

| Field | Detail |
|---|---|
| Severity | MEDIUM |
| Exact assertion | `assertSee('reason-rejected-bob')` with `month=2026-09` at line 64 |
| Evidence | Bob’s `request_date=2026-09-15`; `ApprovalsListFilters::monthStart()` used same overflowing `Y-m` parse → October window → row excluded |
| Root cause | Same Carbon overflow as §2 |
| Caused by GH? | **No** |
| Fix | `YearMonth::start` in `ApprovalsListFilters::monthStart()` |
| Attendance/Payroll rules changed? | **No** |

---

## Post-fix verification

| Suite | Result |
|---|---|
| Full `php artisan test` | 1203 passed, 1 skipped, 0 failed (12550 assertions) |
| `--filter=GeneralHouse` | 96 passed |
| Isolated RC GH suite | 96 passed |

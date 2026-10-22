# Attendance SSOT — final architecture & cross-screen enforcement audit

**Date:** 2026-10-22  
**Scope:** TEST only · **LIVE:** not deployed  
**Migrations required:** **NONE**

---

## 1. Rule ownership matrix

| Business rule | Authoritative owner | Cooperating helpers | Must NOT reimplement |
|---|---|---|---|
| Arrival/departure grace thresholds (15/16) | `AttendanceGracePolicy` | config `attendance.php` | Blade, controllers, SQL filters |
| Duration balance / deficit / surplus (grace era) | `AttendanceGracePolicy::durationBalance` | Engine `dayDurationBalance` | History kind logic outside policy |
| Raw late / early (punch vs window) | `AttendanceService::rawLateMinutes` / `rawEarlyMinutes` | `ShiftWindowResolver` | Controllers / Blade |
| Counted late (override + date-aware) | `AttendanceService::exactLateMinutes` | Grace + adjusted override | Summing `late_minutes` column |
| Counted early (date-aware) | `AttendanceService::exactEarlyMinutes` | Grace | Summing `early_departure_minutes` |
| Persist derived fields on close | `AttendanceTimeCalculator::recalculate` | Grace, ShiftConfig | Silent SQL updates |
| Display summary (raw/system/applied/OT/gaps) | `AttendanceTimeCalculator::summarize` → `AttendanceTimeSummary` | Engine wraps | Blade-local math |
| Checkout provenance | `CheckoutProvenance` | Engine result | Ad-hoc `is_auto` interpretation only in Blade labels OK |
| Planned hours / windows | `ShiftConfig` + `ShiftWindowResolver` | — | Hardcoded H:i in views |
| Canonical session DTO | `AttendanceCalculationResult` | Engine `forAttendance` | Parallel DTOs |
| Consumer entry façade | `AttendanceCalculationEngine` | delegates to calculator, grace, AttendanceService | Competing façades |

**Persisted columns** (`late_minutes`, `early_departure_minutes`, `actual_hours`, …) are **derived snapshots** written by `recalculate`. Cross-screen **counted** metrics use `exact*` / Engine, not “trust the column alone” (except pre-effective-date exact* which intentionally returns stored).

**Engine delegation:** yes — `forAttendance` → `summarize` + `exactLateMinutes` + `exactEarlyMinutes` + `CheckoutProvenance` + grace active flag.

**Bypass risk (before this audit):** Portal SQL `late_minutes > 0`; Blade metrics/breakdown calling calculator directly with stored applied late; History/Hours early from stored. **Fixed on TEST.**

Domain primitives (`exact*` on `AttendanceService`, `PayrollAggregator`) remain allowed — same implementation, not a second rule.

---

## 2. Complete consumer inventory (post-fix)

| Consumer | Metric shown | Source path |
|---|---|---|
| Employee clock | Late preview / open late | Engine → exactLate |
| Report reminder banner | Late / early | ClockController → Engine exact* |
| TL / Admin attendance history | Day late / early / kind | HistoryService → Engine sums + durationBalance |
| Hours month / dashboards | Late / early / shortfall_raw/credited | HoursComparison → exact*; shortfall = physical planned−hours |
| Attendance time metrics / breakdown Blade | Summary badges | Engine → summarize (applied late = exact*) |
| Portal home `late_today` | Distinct late users | Engine exactLate (or STATUS_LATE) |
| Portal month hours | Planned / actual | ShiftConfig resolve + stored `actual_hours` (credited snapshot) |
| Employee dashboard hours today | Sum `actual_hours` | Persisted credited snapshot |
| Payroll draft live aggregate | `late_minutes_total` | PayrollAggregator → exactLate |
| Payroll suggested late | Punch raw tip | rawLateMinutes (explicitly not counted) |
| Payroll Excel / PDF / payslip | Settled late | **Snapshot** `payroll_records.late_minutes_total` |
| OT review | OT minutes / cap | summarize / review service |
| Force / auto close commands | Write path | AttendanceService / calculator |
| APIs | — | No dedicated attendance JSON API resources found |
| Livewire | — | None in repo |
| Jobs / scheduler | Stale close, sync planned | Domain services |

Full prior map: `CALCULATION_CONSUMER_INVENTORY.md` (updated by this audit’s fixes).

### Remaining intentional non-interchangeable metrics

- `shortfall_raw` / `shortfall_credited` ≠ grace deficit  
- `suggested_late_minutes_total` ≠ `late_minutes_total`  
- Settled payroll late ≠ live exact aggregate  

---

## 3. Violations found & fixed (TEST)

| Violation | Fix |
|---|---|
| History summed stored late/early | Engine `sumExact*` (prior SSOT pass) |
| HoursComparison early from stored | `exactEarlyMinutes` + warm assignments |
| Clock reminder / clock page stored late | Engine exact* |
| `summarize` applied late/early = stored columns | Now `exactLateMinutes` / `exactEarlyMinutes` |
| Blade metrics/breakdown bypassed Engine | Resolve via `AttendanceCalculationEngine` |
| Portal `late_today` SQL on `late_minutes` | Load today rows; count via exactLate |
| Assignment N+1 on month/payroll sums | `warmScheduledAssignments` |

---

## 4. Cross-screen parity evidence

`tests/Feature/Attendance/CrossScreenAttendanceParityTest` — Mohamed Reda / Amir / Rahma fixtures; history = hours = payroll live late/early; open checkout provenance; APPROVED payroll snapshot immutable; month sum = session sum.

`AttendanceCalculationEngineTest` — grace 15 vs 16; provenance AUTO_STALE; hours/payroll match engine.

Post-audit: Blade applied late aligns with exact*; portal late_today matches exact when stored=0 (`PortalStatsServiceTest`).

---

## 5. Test count reconciliation

| Command / inventory | Tests | Assertions |
|---|---|---|
| RC1 release filter (docs/REGRESSION_TEST_RESULTS.md) | **180** | **828** |
| Same RC1 filter today | **180** | **828** |
| RC1 filter + SSOT + PortalStats | **198** | **908** |
| `--filter='Attendance'` (substring) | **178** | **767** |
| `tests/Unit/Attendance` + `tests/Feature/Attendance` dirs only | **88** | **324** |

**Why 175 vs 180?** Not deleted tests. Prior “175 attendance” used `--filter='Attendance'`, which **misses** RC1 cases whose names lack that substring (e.g. `ClockTest`, `ClockLocation*`, `Geofence` in `DualTeamScopeTest`, parts of DualDefault, ShiftWindow, etc.). RC1 inventory remains **180**. SSOT added Engine / Architecture / Parity (+ Portal late test outside Attendance filter) → **198** under RC1∪SSOT∪Portal.

---

## 6. Historical & payroll safety

| Check | Evidence |
|---|---|
| 17 fabricated auto-closes unchanged | TEST DB still has IDs 35,102,528,542,554,562,573,584,593,616,629,636,646,657,680,691 with `is_auto_clock_out=1`, outs unchanged; `updated_at` predates this audit |
| 1 manual-review (666) unchanged | Present; auto=1; out `2026-10-20 11:13:24` |
| No silent historical rewrite | No migrations; detect-fabricated dry-run only historically |
| Pre-effective exact* uses stored | `PayrollLateMinutesFromClockTest` |
| APPROVED payroll immutable | CrossScreen + GraceEffectiveDate / PayrollImmutability suites |

---

## 7. Performance

| Path | Sample (TEST) | Notes |
|---|---|---|
| HoursComparison 10-day month | ~57 ms, ~99 queries | Includes photos/segments/gaps eager load; assignment warm removes per-row assignment queries |
| Engine day sums | Warm once per collection | `sumExact*` / payroll totals |
| Portal late_today | O(today’s punches) PHP exactLate | Correctness over SQL approximation; scoped to one day |

No result caching added (no invalidation surface). No LIVE timing baseline available in this pass.

---

## 8. Architectural regression protection

- `AttendanceSsotArchitectureTest` — DI on History/Clock/Portal; Blade uses Engine; Controllers forbid `diffInMinutes`; HoursComparison forbids stored early sum  
- `AGENTS.md` §1.1b + `ENGINEERING_CONSTITUTION.md` + `SSOT_CALCULATION_ARCHITECTURE.md`

---

## 9. Git changed-file manifest (uncommitted)

**Modified:** `AGENTS.md`, `ENGINEERING_CONSTITUTION.md`, `ClockController.php`, `AttendanceHistoryService.php`, `AttendanceService.php`, `AttendanceTimeCalculator.php`, `ClockPageService.php`, `HoursComparisonService.php`, `PayrollAggregator.php`, `PortalStatsService.php`, `ATTENDANCE_CONSUMER_MATRIX.md`, `attendance-time-breakdown.blade.php`, `attendance-time-metrics.blade.php`, `PortalStatsServiceTest.php`

**Added:** `AttendanceCalculationEngine.php`, `AttendanceCalculationResult.php`, `CheckoutProvenance.php`, SSOT docs (architecture, inventory, discrepancy, parity, regression, **this audit**), `CrossScreenAttendanceParityTest.php`, `AttendanceCalculationEngineTest.php`, `AttendanceSsotArchitectureTest.php`

**Migrations:** none.

---

## 10. Final release readiness verdict

**CONDITIONAL**

| Gate | Status |
|---|---|
| Ownership / one rule one implementation (counted late/early) | PASS on TEST |
| Consumers accounted for | PASS |
| Confirmed SSOT violations fixed | PASS on TEST |
| Parity + architecture + portal tests | PASS |
| Historical 17+1 / payroll snapshots | PASS (unchanged) |
| Test inventory reconciled | PASS (180 RC1; 198 with SSOT/Portal) |
| LIVE deploy | **BLOCKED** — owner approval required |

Do **not** treat as LIVE APPROVED. Ship to LIVE only after owner review of this audit + RC attendance package.

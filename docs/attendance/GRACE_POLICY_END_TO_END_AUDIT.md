# Grace policy — end-to-end audit & enforcement

**Environment:** TEST only (`/home/d14098/generalsys.idigital-cs.com`, DB `d14098_general`, URL `https://generalsys.idigital-cs.com`)  
**Date:** 2026-10-22  
**LIVE deploy / historical payroll rewrite:** **NOT performed** (owner approval required)

---

## 1. Actual implementation paths

| Concern | Authoritative path |
|---|---|
| Threshold math | `AttendanceGracePolicy::countedLateMinutes` / `countedEarlyMinutes` / `durationBalance` |
| Effective date | `config('attendance.grace_policy_effective_date')` default **2026-10-01**; gate = attendance **workday** |
| Persist on close | `AttendanceTimeCalculator::recalculate` → writes counted `late_minutes` / `early_departure_minutes` / `status` |
| Read / payroll / screens | `AttendanceService::exactLateMinutes` / `exactEarlyMinutes` → engine façade `AttendanceCalculationEngine` |
| Raw punch (pre-grace) | `AttendanceService::rawLateMinutes` / `rawEarlyMinutes` |
| History list | `AttendanceHistoryService` → `sumExact*` + `dayDurationBalance` |
| Month hours UI | `HoursComparisonService::monthForUser` → sums `exact*` |
| Payroll draft totals | `PayrollAggregator::aggregate` → `late_minutes_total` = sum `exactLateMinutes` |
| Portal late KPI | `PortalStatsService::distinctLate` → **exactLateMinutes only** (fixed this audit) |
| Excel / PDF | Snapshot of `payroll_records.late_minutes_total` (settled) |
| API Resources | None under `app/Http` |

**Locked rules (unchanged):**

```text
counted = raw ≤ 15 ? 0 : raw     // threshold, never raw−15
effective: date >= 2026-10-01
pre-Oct: exact* returns stored columns; recalculate freezes derived fields
```

**Working-hours (Rule C):** `durationBalance` exempts within-grace arrival/departure from counted deficit; actual worked hours / punch timestamps never rewritten.

---

## 2. Boundary test matrix

Automated in `tests/Unit/Attendance/GracePolicyEndToEndTest.php` (clock-in/out + persist + deficit + status).

### Late arrival (workday ≥ 2026-10-01)

| Raw min | Counted | Status | Deficit (on-time out) |
|---:|---:|---|---:|
| 0 | 0 | PRESENT | 0 |
| 1 | 0 | PRESENT | 0 |
| 13 | 0 | PRESENT | 0 |
| 14 | 0 | PRESENT | 0 |
| 15 | 0 | PRESENT | 0 |
| 16 | 16 | LATE | 16 |
| 17 | 17 | LATE | 17 |
| 18 | 18 | LATE | 18 |
| 30 | 30 | LATE | 30 |
| 60 | 60 | LATE | 60 |

### Early departure (same matrix)

Identical counted/status/deficit behaviour via `countedEarlyMinutes` → `STATUS_EARLY_LEAVE` when counted > 0.

### Pre-effective date

Same raw values → counted = raw (no 15-minute threshold); stored September rows not rewritten on recalculate.

---

## 3. Raw versus counted values

| Layer | Raw | Counted |
|---|---|---|
| Policy | input | `≤15 → 0`, else full raw |
| Persisted columns (Oct+) | not stored as raw | `late_minutes` / `early_departure_minutes` |
| `AttendanceTimeSummary` | `rawLateMinutes` / `rawEarlyMinutes` | `systemLateMinutes` / `appliedLateMinutes` |
| Payroll | `suggested_late_minutes_total` (draft tip) | `late_minutes_total` (exact*) |
| Punches / `raw_actual_hours` | unchanged by grace | — |

**Never subtracts 15** — asserted for 16/17/18/30/60.

---

## 4. Working-hours deficit calculations

`AttendanceGracePolicy::durationBalance`:

```text
exemptArrival  = rawLate ≤ 15 ? rawLate : 0
exemptDeparture = rawEarly ≤ 15 ? rawEarly : 0
if actual ≥ planned → surplus = actual − planned (not auto OT)
else deficit = max(0, planned − actual − exemptArrival − exemptDeparture)
```

Example verified: 10 late + 20 early on 8h day → counted late 0, early 20, deficit **20**.

**Intentional non-grace metrics (documented):** `HoursComparisonService` `shortfall_*` and payroll `hours_deduction` remain **physical** planned−hours, not grace deficit.

---

## 5. Payroll integration evidence

| Path | Behaviour | Result |
|---|---|---|
| `PayrollAggregator::late_minutes_total` | sum `exactLateMinutes` | Matches session counted |
| `suggested_late_minutes_total` | sum `rawLateMinutes` | Tip only; 10 raw → suggested 10, exact total **0** |
| Draft generate / refresh | uses exact aggregate | PASS |
| APPROVED / PAID refresh/edit | forbidden (`PayrollPolicy`) | PASS (existing Task 1.6 tests) |
| Excel col L / PDF | settled snapshot | No live recompute |

**Residual product risk (not silently changed):** Admin UI “Use suggested” can copy **raw** punch sum into draft `late_minutes_total`. Default aggregation remains exact*. Documented; changing UX needs owner product decision.

---

## 6. Cross-screen parity results

Fixture: Oct clock-in +16 → counted **16**.

| Surface | Late value | Match |
|---|---:|:---:|
| `AttendanceCalculationEngine::exactLateMinutes` | 16 | ✓ |
| TL/Admin history day row | 16 | ✓ |
| `HoursComparisonService` month totals | 16 | ✓ |
| `PayrollAggregator` `late_minutes_total` | 16 | ✓ |
| Portal `late_today` (exact > 0) | counts user | ✓ |

Raw vs counted differentiated in summary / payroll suggested fields.

### Shift edge cases (automated)

| Case | Result |
|---|---|
| Daytime FT | PASS |
| Overnight 21:00→09:00 (10 / 16 late) | PASS |
| Multi-segment (first in / last out) | PASS |
| Late + early together | PASS |
| Early arrival + late departure (surplus) | PASS |
| Missing checkout (open: stored late 0; live exact applies) | PASS |
| Supervisor-entered checkout via recalculate | PASS |
| Shift assignment window (10:00) + grace | PASS |
| Pre-2026-10-01 freeze | PASS |
| On/after 2026-10-01 grace | PASS |
| Auto-checkout | Covered by existing `AttendanceForceClockOut*` inventory (no grace-threshold rewrite of punches) |

---

## 7. Defects found and fixed

### Fixed on TEST

1. **Portal `late_today` OR’d stale `STATUS_LATE`** (`PortalStatsService`)  
   - Before: `status === LATE || exactLate > 0` inflated KPI when status stale and counted = 0.  
   - After: **only** `exactLateMinutes > 0`.  
   - Regression: `GracePolicyEndToEndTest::test_portal_late_today_ignores_stale_status_when_counted_zero`.

### Documented — not auto-backfilled

2. **Stale stored columns on some Oct TEST rows** (sample ids 1046–1055): punches imply raw late **20**, stored `late_minutes=0`, `status=PRESENT`, while **exactLateMinutes=20** (SSOT read correct).  
   - Cause: rows likely written before policy write-path or via seed; Oct read path recomputes.  
   - **No silent bulk recalculate** this audit (would flip status → LATE and could change draft payroll on refresh). Owner may approve a dedicated Artisan backfill later.

3. **Payroll “Use suggested” = raw** — intentional tip; risk if Admin applies without reading labels. Left as product CONDITIONAL.

### Not defects

- No “deduct 15” math found in `AttendanceGracePolicy`.  
- Physical shortfall ≠ grace deficit (architecture doc).  
- Pre-Oct stored semantics preserved.

---

## 8. Full regression results

**Rounding rule (documented, unchanged):**

```text
raw minutes = (int) Carbon::diffInMinutes(...)   // truncate toward 0
15m00s → 15 → counted 0
15m01s → 15 → counted 0
15m59s → 15 → counted 0   // NOT round-half-up
16m00s → 16 → counted 16
```

**New suite:** `GracePolicyEndToEndTest` — **61 passed** (296 assertions).

**Inventory filter** (prior 212 + SSOT/tooltip/grace E2E):

```bash
php artisan test --filter='AttendanceServiceTest|DualDefaultShiftsTest|AttendanceGrace|ShiftWindow|ClockTest|AttendanceForceClockOut|AttendanceHistory|AttendanceMultiPunch|AttendanceTimeBreakdown|ForensicAttendanceRegressionTest|AttendanceGracePolicy|ClockCta|Geofence|AttendanceSchema|AttendancePhoto|AttendanceGap|HoursComparison|ClockLocation|PayrollLateMinutes|AttendanceCalculationEngine|AttendanceSsotArchitecture|CrossScreenAttendanceParity|PortalStatsServiceTest|HistoricalPresentationIntegrity|FinalTargetedAcceptance|AttendanceUiTooltipRefinement|GracePolicyEndToEnd'
```

**Result: 273 passed (1284 assertions)**

Focus SSOT (680/681) unchanged: late exact **1383** / **183**.

---

## 9. Git changed-file summary (this audit slice)

| Path | Change |
|---|---|
| `app/Services/Portal/PortalStatsService.php` | late_today = exact* only |
| `tests/Unit/Attendance/GracePolicyEndToEndTest.php` | **new** boundary + edge + parity suite |
| `docs/attendance/GRACE_POLICY_END_TO_END_AUDIT.md` | **this file** |

Broader working tree still contains prior SSOT/tooltip work (uncommitted). **No push / no LIVE deploy.**

---

## 10. Final verdict

### **CONDITIONAL**

| Gate | Status |
|---|---|
| Policy math (threshold ≠ deduction) | PASS |
| Effective date 2026-10-01 | PASS |
| Boundary + seconds truncation | PASS |
| Deficit Rule C | PASS |
| Cross-screen counted parity (engine/history/hours/payroll) | PASS |
| Portal KPI SSOT | PASS (fixed) |
| Punches / actual worked preserved | PASS |
| Settled payroll immutable | PASS |
| Stored-column drift on some Oct TEST rows | OPEN (exact* OK; backfill needs owner) |
| Payroll “Use suggested” raw tip | OPEN (product) |
| LIVE | **BLOCKED** without owner approval |

**TEST readiness:** Grace policy is enforced end-to-end through the SSOT domain for counted late/early/deficit and payroll exact totals. Address stored drift / suggested-button UX before treating as unconditional APPROVED for LIVE.

# Attendance & payroll — final release candidate audit

**Environment:** TEST only — `/home/d14098/generalsys.idigital-cs.com` · DB `d14098_general` · URL `https://generalsys.idigital-cs.com`  
**Date:** 2026-10-22  
**Branch tip:** `main` (ahead of packaging; **not pushed**)  
**LIVE deploy / push / merge / historical rewrite:** **NOT performed**

---

## Executive verdict

### **CONDITIONAL — TEST release candidate ready for owner packaging review**

| Gate | Result |
|---|---|
| Code + tests + Vite build (SSOT / grace / tooltips / payroll integrity) | PASS |
| Working tree packagable without `git add .` (exclude `error_log`) | YES |
| October payroll money on TEST | N/A (0 Oct payslips) |
| Stored-vs-exact Oct drift (30 real rows) | OPEN (read-only; no backfill) |
| Overnight profile / window product decisions | OPEN |
| LIVE | **BLOCKED** |

---

## 1. Git change manifest

**Porcelain paths:** 65 (modified / deleted / untracked).  
**Do not use** `git add .` — exclude `error_log`.

### Required source change

| Path | Role |
|---|---|
| `AGENTS.md` | SSOT calculation rule pointer |
| `ENGINEERING_CONSTITUTION.md` | Constitution index touch |
| `app/Services/Attendance/AttendanceCalculationEngine.php` | **new** SSOT façade |
| `app/Services/Attendance/AttendanceCalculationResult.php` | **new** typed result |
| `app/Services/Attendance/CheckoutProvenance.php` | **new** checkout provenance |
| `app/Services/Attendance/AttendanceReviewPresentation.php` | **new** review/tooltip narrative |
| `app/Services/Attendance/AttendanceService.php` | exact/raw late-early, warm assignments |
| `app/Services/Attendance/AttendanceTimeCalculator.php` | grace persist / summarize |
| `app/Services/Attendance/AttendanceHistoryService.php` | history payload + review |
| `app/Services/Attendance/HoursComparisonService.php` | month exact* consumers |
| `app/Services/Attendance/ClockPageService.php` | clock late via exact* |
| `app/Services/Portal/PortalStatsService.php` | late_today = exact* only |
| `app/Services/Payroll/PayrollAggregator.php` | suggested = exact; early suggestion; raw diagnostic |
| `app/Services/Payroll/PayrollDraftService.php` | `applySuggestedLateMinutes` server SSOT |
| `app/Http/Controllers/Admin/PayrollController.php` | apply-suggested-late |
| `app/Http/Controllers/Employee/ClockController.php` | reminder exact* |
| `routes/web.php` | `admin.payroll.apply-suggested-late` |
| `resources/js/app.js` | `initHpTooltips` |
| `resources/scss/_hr-pro.scss` | `.hp-tooltip` styles |
| `resources/views/admin/payroll/show.blade.php` | server apply form (no JS raw inject) |
| `resources/views/team-leader/attendance/index.blade.php` | review badge / metrics |
| `resources/views/components/attendance-*.blade.php` (+ new metric/review/session/hp-tooltip) | presentation consumers |
| `lang/en|ar/attendance.php` | review / tooltip strings |
| `lang/en|ar/payroll.php` | suggestion help + flash |

### Required test

| Path |
|---|
| `tests/Unit/Attendance/AttendanceCalculationEngineTest.php` |
| `tests/Unit/Attendance/AttendanceSsotArchitectureTest.php` |
| `tests/Unit/Attendance/AttendanceUiTooltipRefinementTest.php` |
| `tests/Unit/Attendance/FinalTargetedAcceptanceTest.php` |
| `tests/Unit/Attendance/GracePolicyEndToEndTest.php` |
| `tests/Unit/Attendance/HistoricalPresentationIntegrityTest.php` |
| `tests/Feature/Attendance/CrossScreenAttendanceParityTest.php` |
| `tests/Unit/Payroll/PayrollGraceFinancialIntegrityTest.php` |
| `tests/Unit/Portal/PortalStatsServiceTest.php` (modified) |
| `tests/Feature/Admin/PayrollDraftTest.php` (modified) |

### Required documentation

| Path |
|---|
| `docs/attendance/SSOT_CALCULATION_ARCHITECTURE.md` |
| `docs/attendance/SSOT_FINAL_ARCHITECTURE_AUDIT.md` |
| `docs/attendance/SSOT_REGRESSION_RESULTS.md` |
| `docs/attendance/CALCULATION_CONSUMER_INVENTORY.md` |
| `docs/attendance/CALCULATION_DISCREPANCY_REPORT.md` |
| `docs/attendance/CHECKOUT_PROVENANCE_RULES.md` |
| `docs/attendance/CROSS_SCREEN_PARITY_REPORT.md` |
| `docs/attendance/HISTORICAL_PRESENTATION_AUDIT.md` |
| `docs/attendance/HISTORICAL_PRESENTATION_REGRESSION.md` |
| `docs/attendance/FINAL_TARGETED_ACCEPTANCE_AUDIT.md` |
| `docs/attendance/ATTENDANCE_UI_TOOLTIP_REFINEMENT.md` |
| `docs/attendance/GRACE_POLICY_END_TO_END_AUDIT.md` |
| `docs/attendance/PAYROLL_GRACE_FINANCIAL_INTEGRITY_AUDIT.md` |
| `docs/attendance/ATTENDANCE_CONSUMER_MATRIX.md` (updated) |
| `docs/attendance/FINAL_RELEASE_CANDIDATE_AUDIT.md` (**this file**) |

### Required compiled asset

| Path | Note |
|---|---|
| `public/build/manifest.json` | points to new hashes |
| `public/build/assets/app-BBdTcOWU.js` | includes tooltip init |
| `public/build/assets/app-DTRGYXEQ.css` | LTR |
| `public/build/assets/app-gX4Nzyks.css` | RTL |
| `public/build/assets/app-ClkB_cEM.css` (**deleted**) | superseded |
| `public/build/assets/app-D-MHGUTc.js` (**deleted**) | superseded |
| `public/build/assets/app-IzK3I7R8.css` (**deleted**) | superseded |

### Unrelated / unsafe — **exclude from RC commit**

| Path | Class |
|---|---|
| `error_log` | Temporary/generated PHP CLI fatals (Sep–Oct). **Do not commit.** |

### Packaging recommendation

**YES — working tree can safely become a release candidate** if the owner stages **explicit paths** (all required source/test/docs/assets above) and **omits `error_log`**. No secrets (`.env`) appear in the change set. No LIVE tree modifications in this inventory.

---

## 2. Complete test & build results

### Regression inventory

```bash
php artisan test --filter='AttendanceServiceTest|DualDefaultShiftsTest|AttendanceGrace|ShiftWindow|ClockTest|AttendanceForceClockOut|AttendanceHistory|AttendanceMultiPunch|AttendanceTimeBreakdown|ForensicAttendanceRegressionTest|AttendanceGracePolicy|ClockCta|Geofence|AttendanceSchema|AttendancePhoto|AttendanceGap|HoursComparison|ClockLocation|PayrollLateMinutes|AttendanceCalculationEngine|AttendanceSsotArchitecture|CrossScreenAttendanceParity|PortalStatsServiceTest|HistoricalPresentationIntegrity|FinalTargetedAcceptance|AttendanceUiTooltipRefinement|GracePolicyEndToEnd|PayrollGraceFinancialIntegrity|PayrollDraftTest|RC1'
```

**Result: 311 passed (1451 assertions)** — **matches** latest baseline exactly (311 / 1451).

### Count reconciliation

| Milestone | Tests | Assertions |
|---|---:|---:|
| Earlier SSOT/tooltip inventory | 212 | 988 |
| + grace E2E / portal | 273 | 1284 |
| + payroll financial integrity + PayrollDraft in filter | **311** | **1451** |
| This RC re-run | **311** | **1451** |

Delta from 212 → 311 is additive suites (engine, SSOT arch, cross-screen, historical, tooltips, grace E2E, payroll financial, draft), not weakened assertions.

Coverage includes: RC1 attendance, SSOT architecture, cross-screen parity, historical checkout provenance, tooltip presentation, grace policy, payroll financial integrity.

### Frontend production build

```text
npm run build  →  PASS (vite v6.4.3, ~4.06s)
manifest → app-BBdTcOWU.js + app-DTRGYXEQ.css + app-gX4Nzyks.css
```

Tooltip bundle markers present (`hp-tooltip` / hover-focus-click init path from prior browser verification).

---

## 3. Historical drift inventory (read-only)

**Query:** closed attendance with `date >= 2026-10-01` where stored late/early ≠ `exactLateMinutes` / `exactEarlyMinutes`.  
**No recalculate / no row updates performed.**

| Cohort | Count |
|---|---:|
| Total drift rows | **40** |
| Synthetic (0 segments, factory-like) | **10** (ids **1046–1055**, uid 1044 Joy Wilderman) |
| Real (has segments) | **30** |

### Synthetic 1046–1055

| Field | Pattern |
|---|---|
| Shift | FULL_TIME · profile 09:00–17:00 · punch 09:20–17:00 |
| Stored late / status | 0 / PRESENT |
| SSOT exact late | **20** |
| Segments | **0** |
| Oct payroll | none |
| Root cause | Direct factory/insert; never `AttendanceTimeCalculator::recalculate` |
| Correction | Optional delete/quarantine after owner confirm — **not run** |

### Real records (30) — detail

Legend: **ΔL** = exactLate − storedLate; **ΔE** = exactEarly − storedEarly.  
**Payroll dep** = linked 2026-09/10 payslip (month:status:late). **Oct payrolls on TEST = 0** for all.

| ID | Shift | Profile window | Stored L→exact L (Δ) | Stored E→exact E (Δ) | Status / provenance | Payroll dep | Root cause | Correction recommended? |
|---:|---|---|---|---|---|---|---|---|
| 478 | FULL_TIME | 10:00–18:00 | 0→0 (0) | 3→0 (−3) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late0 | Within-grace early persisted; exact applies grace→0; stale EARLY_LEAVE | Yes — optional recalculate for label consistency; **no Oct money** |
| 485 | ONLINE_FULL | 12:00–00:00 | 0→33 (+33) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Write-path stored late 0; Oct exact recomputes punch late | Yes (stored/status); Sept APPROVED untouched |
| 496 | ONLINE_FULL | 12:00–00:00 | 0→185 (+185) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Same | Yes (stored/status) |
| 507 | FULL_TIME | 10:00–18:00 | 0→19 (+19) | 114→114 (0) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late0 | Late column stale; early already counted | Yes (late/status) |
| 509 | ONLINE_HALF | 06:00–18:00 | 0→721 (+721) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Punch vs wide/day window → huge exact late; stored 0 | Yes + **review shift window** |
| 513 | FULL_TIME | 22:00–10:00 | 0→0 | 4→0 (−4) | EARLY_LEAVE / AUTO_STALE | 9:DRAFT:late0 | Within-grace early + auto-stale close | Yes (early/status); draft Sept only |
| 514 | FULL_TIME | 09:00–21:00 | 0→41 (+41) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 vs counted 41 | Yes |
| 521 | FULL_TIME | 06:00–18:00 | 0→21 (+21) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 523 | FULL_TIME | 10:00–18:00 | 0→439 (+439) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Late punch vs day window; stored 0 | Yes + window review |
| 524 | ONLINE_HALF | 06:00–18:00 | 0→720 (+720) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Evening ONLINE_HALF vs 06:00 start → ~12h late | Yes + **shift assignment review** |
| 527 | ONLINE_HALF | 10:00–12:00 | 0→0 | 1→0 (−1) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late0 | Within-grace early stored | Yes (label) |
| 532 | ONLINE_FULL | 10:00–12:00 | 0→0 | 2→0 (−2) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late0 | Within-grace early stored | Yes (label) |
| 536 | FULL_TIME | 06:00–18:00 | 0→70 (+70) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 537 | ONLINE_FULL | 12:00–00:00 | 0→43 (+43) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 538 | ONLINE_HALF | 06:00–18:00 | 0→723 (+723) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Same class as 524 | Yes + shift review |
| 542 | FULL_TIME | **00:00–12:00** | 0→1385 (+1385) | 0→0 | PRESENT / AUTO_STALE | 9:DRAFT:late0 | Night punch vs midnight-start profile; auto-stale | Yes + **overnight profile decision** |
| 543 | FULL_TIME | 09:00–21:00 | 0→18 (+18) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 555 | FULL_TIME | 09:00–21:00 | 0→37 (+37) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 559 | FULL_TIME | 10:00–18:00 | 0→286 (+286) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Midday clock-in; stored 0 | Yes |
| 562 | FULL_TIME | **00:00–12:00** | 0→1386 (+1386) | 0→0 | PRESENT / AUTO_STALE | 9:DRAFT:late0 | Same as 542 (Reda) | Yes + profile |
| 569 | FULL_TIME | 10:00–18:00 | 0→66 (+66) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late9 | Stale late 0 | Yes |
| 573 | FULL_TIME | **00:00–12:00** | 0→1381 (+1381) | 0→0 | PRESENT / AUTO_STALE | 9:DRAFT:late0 | Reda overnight/auto-stale | Yes + profile |
| 574 | FULL_TIME | 09:00–21:00 | 0→30 (+30) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 575 | ONLINE_FULL | 10:00–22:00 | 0→0 | 5→0 (−5) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late7 | Within-grace early stored | Yes (label) |
| 581 | FULL_TIME | 10:00–18:00 | 0→105 (+105) | 132→132 (0) | EARLY_LEAVE / EMPLOYEE | 9:APPROVED:late0 | Late column stale; early OK | Yes (late) |
| 584 | FULL_TIME | **00:00–12:00** | 0→1330 (+1330) | 0→0 | PRESENT / AUTO_STALE | 9:DRAFT:late0 | Reda overnight/auto-stale | Yes + profile |
| 590 | FULL_TIME | 06:00–18:00 | 0→42 (+42) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 593 | FULL_TIME | **00:00–12:00** | 0→1390 (+1390) | 0→0 | PRESENT / AUTO_STALE | 9:DRAFT:late0 | Reda overnight/auto-stale | Yes + profile |
| 601 | FULL_TIME | 06:00–18:00 | 0→17 (+17) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Stale late 0 | Yes |
| 602 | ONLINE_HALF | 06:00–18:00 | 0→721 (+721) | 0→0 | PRESENT / EMPLOYEE | 9:APPROVED:late0 | Same class as 524/538 | Yes + shift review |

**Cross-cutting root causes**

1. **Stale write snapshot:** Oct+ `exact*` recomputes grace-counted punch lateness; many rows still have `late_minutes=0` / `PRESENT` from an older close path (flexible-start / pre-grace writer).  
2. **Within-grace early labels:** stored early 1–5 with `EARLY_LEAVE` while exact early = 0.  
3. **Window / overnight mismatch:** profiles `00:00–12:00` (Reda) or ONLINE_HALF starting 06:00 with evening punches → very large exact late; presentation shows review — product may need profile correction, not only column backfill.  
4. **AUTO_STALE** checkout provenance on several Reda/Umer rows — presentation integrity already documented; no punch rewrite.

**Payroll dependency summary:** No October payslips. September APPROVED/DRAFT snapshots exist; **immutable** for APPROVED. Future Oct draft generate will use **exact*** (counted), so money will follow SSOT even if stored columns stay stale until backfill.

---

## 4. Financial safety evidence

| Check | Evidence | Status |
|---|---|---|
| Suggested late = grace-counted | `PayrollAggregator`: `suggested_late_minutes_total => $exactLate` | PASS |
| Suggested early = grace-counted | `suggested_early_departure_minutes_total` = sum `exactEarlyMinutes` | PASS |
| Server recomputes suggestions | `PayrollDraftService::applySuggestedLateMinutes` + `POST admin.payroll.apply-suggested-late` | PASS |
| Forged client values ignored | `PayrollGraceFinancialIntegrityTest::test_apply_suggested_late_http_ignores_forged_payload` | PASS |
| Manual HR overrides auditable | `updateCounts` keeps saved late; show does not auto-overwrite | PASS |
| Approved payroll immutable | apply-suggested / refresh / update → 403 on APPROVED/PAID | PASS |
| Historical approved unchanged | Sept APPROVED sample nets untouched this session; 0 Oct payrolls | PASS |

Raw punch sum remains `raw_late_minutes_total` (**diagnostic only**).

---

## 5. Outstanding operational decisions (owner)

1. **Stage & commit RC?** Explicit path list above; exclude `error_log`.  
2. **Owned Oct stored-column backfill?** Dry-run Artisan recalculate for the 30 real drift rows (never silent migrate).  
3. **Synthetic 1046–1055?** Delete/quarantine if confirmed non-production.  
4. **Mohamed Reda overnight profile** (`00:00–12:00` vs ~23:00 punches) — correct profile vs accept large exact late + review badges.  
5. **ONLINE_HALF evening punches** vs 06:00 window (Rawan 509/524/538/602) — assignment/window review.  
6. **`hours_deduction` vs grace Rule C** — keep physical shortfall (current) or align later (product).  
7. **LIVE cutover** — only after owner approval + backup; not in this RC.

---

## 6. Release scope

**In scope for this RC (TEST code/docs/assets):**

- Attendance SSOT engine + consumers  
- Grace policy 15-min threshold from 2026-10-01  
- Historical presentation / checkout provenance (no punch rewrite)  
- Review-required tooltips (AR/EN, RTL/LTR)  
- Portal late KPI exact*  
- Payroll suggested late/early = counted; server apply-suggested  
- Vite-built public assets  

**Out of scope / not done:**

- LIVE deploy, push, merge  
- Automatic historical attendance backfill  
- October payroll generation  
- Changing APPROVED/PAID records  
- Schema migrations (none required for this RC)

---

## 7. Rollback considerations

| Layer | Rollback |
|---|---|
| Code | Revert RC commit(s) on TEST; redeploy prior revision |
| Assets | Restore prior `public/build` hashes from previous commit |
| Data | No attendance/payroll rows modified by this RC → **no data rollback needed** for RC apply |
| If owned backfill runs later | Keep dry-run logs; restore from DB backup before backfill |

---

## 8. Final verdict

### **CONDITIONAL**

The TEST tree is a **viable release candidate for owner review and explicit-path commit**: tests **311/1451** green, Vite build green, payroll suggestion path financially aligned with grace SSOT, approved payroll untouched.

Remaining CONDITIONAL factors are **operational** (Oct stored drift backfill, overnight/window product fixes, LIVE approval)—not open code defects in the RC scope.

**Stop line honored:** no git push, merge, LIVE deployment, or historical data modification.

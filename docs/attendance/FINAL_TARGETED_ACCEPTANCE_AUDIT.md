# Final targeted acceptance audit (TEST)

**Date:** 2026-10-22  
**LIVE:** not deployed · **Historical rows rewritten:** 0 · **Payroll snapshots rewritten:** 0  
**Migrations:** none

---

## 1. Mohamed Reda / att 680 — credited 12.00h provenance

| Question | Answer |
|---|---|
| `credited_hours` column | **NULL** (no TL/Admin manual override) |
| OT approval | `overtime_tl_recommendation=NONE`, `overtime_admin_decision=PENDING`, recommended OT = 0 |
| Payroll snapshot | Not the UI source; settles separately on `payroll_records` |
| Stored `actual_hours` / `raw_actual_hours` / `planned_hours` | **12.00 / 12.00 / 12.00** |
| Engine `creditedProvenance()` | **`POLICY_CAP`** |
| Engine `creditedReviewRequired()` | **true** (unverified `AUTO_STALE` checkout) |

**Root cause:** Attendance policy `min(recorded_raw, planned)` persisted as `actual_hours` when the fabricated auto-close span equalled planned 12h. Display “Credited” previously implied approval; it is **policy-capped recorded duration**, not verified work and not administrative approval.

**Presentation fix:** label as **Credited (policy cap ≤ planned)** + **Credited hours need review** + hint that this is not approval of verified work. Values left unchanged.

---

## 2. Status semantics — “Present” vs LATE

| Concept | Source | UI after fix |
|---|---|---|
| Presence / day kind | `AttendanceHistoryService::kind` (`present` = attended day) | Label **Attended** (not “Present”); secondary color; tooltip: not punctuality |
| Punctuality | Stored `status` + exact late/early | Separate badge **Late** / **On time** / **Early leave** |
| Checkout verification | `CheckoutProvenance` | Auto-closed / employee / supervisor / missing |
| Shift review | `shiftReviewRequired()` | Shift review required |
| Payroll approval | Payroll record status | Unchanged; not shown as attendance kind |

**Root cause:** Day-kind `present` meant “had attendance / not absent,” while `attendance.status=LATE`. Users read “Present” as on time.

---

## 3. Geolocation integrity (680 / 681)

| Item | Value |
|---|---|
| Employee punch coords (680 in) | lat **25.2694281**, lng **55.3301430** (Dubai-area) |
| 681 in / out | Same Dubai-area cluster; out GPS present (employee verified) |
| 680 out GPS | **NULL** (auto close — expected) |
| Office source | Admin settings via `GeofenceService::office()` → `SettingService` keys |
| Office on TEST | lat **30.1198431**, lng **31.3403046** (Cairo-area) |
| Radius | **100 m** (settings); config default fallback 1000 m |
| Formula | Haversine meters; ordering lat/lng correct |
| Stored distance | ~**2,417,798 m** ≈ **2,418 km** |
| Recompute | Matches Haversine office↔punch (± same integer meters) |

**Root cause of ~2,418 km:** Employee punched near **Dubai**; office geofence point is **Cairo**. Distance is geometrically correct — **not** a lat/lng swap bug.  
**Action:** Do **not** change office coordinates without verified evidence of the intended office location / remote-work policy.

---

## 4. Overtime semantics (681)

| Metric | Value |
|---|---|
| Verified worked | **12.70h** (EMPLOYEE checkout) |
| Planned | **12.00h** |
| Net surplus | **42 minutes** |
| Overtime eligible (system suggested) | **42 minutes** |
| Overtime approved | **0** (TL NONE / admin PENDING) |
| Credited applied | **12.00h** (policy cap; OT not approved) |

UI now shows **Overtime eligible (not approved)** separately from **Overtime approved**, with hint that surplus ≠ approved OT.

---

## 5. Changes (TEST presentation / SSOT only)

- `AttendanceCalculationResult`: `creditedProvenance`, `creditedReviewRequired`, `overtimeEligibleMinutes`
- `AttendanceHistoryService`: `punctuality`, OT eligible/approved aggregates, credited review flags
- History Blade: Attended + punctuality; credited policy-cap/review labels; OT eligible vs approved
- Lang EN/AR integrity + punctuality + kinds rename
- Tests: `FinalTargetedAcceptanceTest`

## Regression inventory

```text
php artisan test --filter='AttendanceServiceTest|DualDefaultShiftsTest|AttendanceGrace|ShiftWindow|ClockTest|AttendanceForceClockOut|AttendanceHistory|AttendanceMultiPunch|AttendanceTimeBreakdown|ForensicAttendanceRegressionTest|AttendanceGracePolicy|ClockCta|Geofence|AttendanceSchema|AttendancePhoto|AttendanceGap|HoursComparison|ClockLocation|PayrollLateMinutes|AttendanceCalculationEngine|AttendanceSsotArchitecture|CrossScreenAttendanceParity|PortalStatsServiceTest|HistoricalPresentationIntegrity|FinalTargetedAcceptance'
```

**Result: 207 passed (957 assertions).**

## Remaining manual decisions

1. Confirm whether Reda’s **profile** should be overnight going forward (not a silent historical rewrite).
2. Confirm whether TEST **office lat/lng** should remain Cairo while some staff punch in Dubai (ops/settings — evidence required).
3. Whether to manually correct fabricated 680 checkout / credited hours — **owner approval only**.
4. LIVE deploy of presentation package — **owner approval**.

---

## Verdict

**CONDITIONAL**

- TEST acceptance for traced root causes + presentation clarity: **PASS**
- Historical data / payroll snapshots: **preserved**
- LIVE: **BLOCKED** without owner approval
- Manual ops decisions (profile, office location, fabricated-row correction): **open**

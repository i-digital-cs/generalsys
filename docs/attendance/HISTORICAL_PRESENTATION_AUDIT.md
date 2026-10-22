# Historical presentation audit — attendance 680 / 681

**Environment:** TEST · **Rows modified:** 0 · **Payroll rewritten:** 0  
**Date:** 2026-10-22

## Phase 1 — Exact data sources

### Attendance 680 (MOHAMED REDA)

| Displayed value | Classification | Source / path |
|---|---|---|
| Check-in `2026-10-20 23:03` | Correct stored historical | `attendance.clock_in` |
| Auto-checkout `2026-10-21 11:03` | Unverified historical (fabricated auto) | `clock_out` + `is_auto_clock_out=1` → provenance `AUTO_STALE` |
| Shift window `00:00–12:00` | Historically valid profile (stale vs night work) | User profile `work_start/end`; **no** shift_assignment that day |
| Lateness **1383** | Correct calc vs planned window; **misleading** as trusted late | `exactLateMinutes` = minutes from 00:00 to 23:03; stored `late_minutes=1383` |
| Worked **12.00h** | Unverified recorded duration | `raw_actual_hours` / span = planned 12h fabricated close |
| List “Present” kind | History day-kind (duration/grace), not `status` | `AttendanceHistoryService::kind` → often `present`/`shortfall`; stored **status=LATE** |
| Status LATE | Correct according to stored | `attendance.status` |

**Root cause of 1383:** Not a grace bug. Day profile start midnight + night punch → huge window-relative late. Misleading without shift-review labeling.

**Root cause of 12.00h:** Auto-close at `clock_in + planned` (fabricated). Calculation of span is consistent with stored times; presentation previously implied verified worked time.

### Attendance 681 (MOHAMED AMIR)

| Displayed value | Classification | Source / path |
|---|---|---|
| Check-in / out | Correct stored; employee GPS checkout | provenance `EMPLOYEE` |
| Shift `20:00–08:00` | Matching overnight profile | profile; no assignment row |
| Late **183** | Correct vs 20:00 start | `exactLateMinutes` / stored |
| Raw **~12.70h** | Verified worked | `raw_actual_hours=12.70`; credited/actual capped 12 |

## Phase 2–4 — Presentation repair (TEST)

- Extended `AttendanceCalculationResult` with verified/recorded hours, `shiftReviewRequired()`, late trust flags.
- History list/details: separate **recorded (unverified)** vs **verified worked** / **credited**; late labeled **vs planned window (review)** when shift review required.
- Punch range uses Engine + provenance labels; shows planned window times.
- No DB rewrite of 680/681; no payroll mutation.

## Shift assignment integrity (680)

- Assignments that day: **0**
- Window comes from **stale day profile** `00:00–12:00` while employee punched at night (peer Amir uses overnight profile).
- Action: **review-required** indicator only — do not invent overnight shift in UI or DB without owner evidence.

## Payroll impact

- Live draft aggregate may still include 1383 late for 680-class rows (SSOT math unchanged).
- APPROVED/PAID `late_minutes_total` snapshots unchanged (regression covered).

## Remaining manual review

1. Confirm whether Reda’s profile should be overnight (ops change going forward — not historical rewrite).
2. Optional future HR correction of fabricated outs — **owner-approved** only; out of scope here.
3. Draft payroll reviewers should treat AUTO_STALE + shift-review days carefully.

## LIVE readiness

**CONDITIONAL / BLOCKED for LIVE** until owner approval. TEST presentation repair complete.

# Attendance forensic architecture map

**Status:** Phase −1 complete (read-only discovery)  
**Investigation date:** 2026-10-22  
**Codebase:** Laravel 11.55.0 · PHP 8.2 · MySQL (`d14098_general`) · `APP_TIMEZONE=Africa/Cairo`

---

## 0. Environment identity (verified)

| Signal | Value | Implication |
|---|---|---|
| Host | `d14098.usc1.stableserver.net` | Hostinger shared hosting |
| Path | `/home/d14098/generalsys.idigital-cs.com` | Live site document root |
| `APP_ENV` | `production` | This process is **not** a labelled TEST app |
| `APP_URL` | `https://generalsys.idigital-cs.com` | Production URL |
| DB | `d14098_general` @ `127.0.0.1` | Application database on this host |
| Queue / cache | `database` / `file` | Hostinger-safe |
| Snapshot | `backup.sql` mtime 2026-10-22 13:56; attendance `max(updated_at)` 13:04; **0** rows updated after backup file | Forensics used current DB consistent with dump |

**Gate:** Owner asked for code changes on TEST only. This host presents as **LIVE**. Phase −1/0 are documentation + SELECT-only. **No implementation until a verified TEST environment is confirmed.**

There is **no** separate device-punch / biometric import subsystem in this codebase.

---

## 1. End-to-end pipeline (actual system)

```text
Browser /clock (employee self-service)
        │
        ▼
ClockController  ──validate FormRequest──► AttendanceService::clockIn / clockOut
        │                                         │
        │                                         ├─ closeStaleOpenBeforeShiftStart (side effect)
        │                                         ├─ resolveAssignment / ShiftWindowResolver
        │                                         ├─ AttendanceSegment create/update
        │                                         └─ AttendanceTimeCalculator::recalculate
        │
        ▼
attendance (+ attendance_segments)   ←── single source of punch truth (mutable rows)
        │
        ├─► Reports / TL boards / ClockPageService (H:i display)
        ├─► HoursComparisonService / AttendanceHistoryService
        ├─► AttendanceGracePolicy (effective 2026-10-01)
        └─► PayrollAggregator (late / hours) — settled payroll immutable
```

### What does **not** exist

| Expected in classic “device” designs | This system |
|---|---|
| Raw punch import table | **Absent** — no `punches` / `device_punches` |
| Device ID / punch ID | **Absent** — browser GPS + optional photos |
| Async punch matching job | **Absent** — synchronous HTTP clock |
| Scheduled auto clock-out cron | **Disabled** (`autoClockOutOverdue` returns 0); historical `is_auto_clock_out` remains |
| Immutable punch ledger | **Absent** — segments/attendance rows are updated in place |

**“Raw punch” in this product = `attendance_segments.clock_in` / `clock_out` (+ header mirrors on `attendance`).**

---

## 2. Domain objects

| Layer | Artifact | Role |
|---|---|---|
| Config | `config/attendance.php` | Grace effective date, stale-close lead minutes, geofence, photos, CTA colors |
| Models | `Attendance`, `AttendanceSegment`, `AttendanceGap`, `AttendancePhoto`, `AttendanceDailyScore`, `ShiftAssignment` | Persistence |
| Services | `AttendanceService` | Clock orchestration, stale-close, shift preference |
| | `AttendanceTimeCalculator` | Hours / late / early / score recalculation |
| | `ShiftWindowResolver` / `ShiftConfig` | Expected start/end + planned hours |
| | `AttendanceGracePolicy` | 15-min threshold from 2026-10-01 |
| | `ClockPageService` | Clock UI read-model (triggers stale-close on load) |
| | `HoursComparisonService`, `AttendanceHistoryService`, … | Reporting |
| Controllers | `Employee\ClockController`, TL attendance/review, photo | Thin HTTP |
| Commands | `attendance:repair-stale-one-minute-closes`, `SyncAttendancePlannedHoursCommand`, `AutoClockOutCommand` (no-op path) | Ops / repair |
| Scheduler | `reports:warn-missing`, `queue:work` | **No** attendance recalculation cron |
| Uniqueness | `uq_att_user_date_shift (user_id, date, shift_type)` | One attendance **row** per type per calendar date |
| Segments | `uq_att_seg_att_seq (attendance_id, sequence)` | Multi punch-in/out on same row (mid-shift breaks) |

---

## 3. Shift configuration & assignment

1. **Employee profiles** — `users.shift_types_default` JSON (up to 3: type + start/end + expected_hours); `work_start_time` / `work_end_time` fallback.
2. **TL assignments** — `shift_assignments` per date/type; overlays windows; does not hide other defaults when ≥2 profiles (`plannedAllowedTypes`).
3. **Window math** — `ShiftWindowResolver::expectedEnd`: if end time ≤ start time → `addDay()` (true overnight). Then `alignEndWithPlannedHours` may shorten a long window to planned length.
4. **Business attendance date** — set at clock-in as **`$at->toDateString()`** (calendar day of punch), not “shift occurrence start”.

---

## 4. Clock-in / clock-out algorithm (summary)

### Clock-in (`AttendanceService::clockIn`)

1. `closeStaleOpenBeforeShiftStart` (may fabricate a prior-day checkout).
2. Reject if any open segment exists.
3. Require `shiftType ∈ plannedAllowedTypes`.
4. If row exists for `(user, date, shift_type)` with closed last segment → **new segment** + gap record (same shift type).
5. Else create attendance row + segment 1.
6. Recalculate; optional geofence / outside-window notify.

### Clock-out

1. Close open segment at `now` (or supervisor time).
2. Recalculate header: `clock_in` = first segment in; `clock_out` = last closed segment out; `raw_actual_hours` = sum of segment minutes.

### Stale force-close (`closeStaleOpenBeforeShiftStart`)

Triggered on `/clock` load and before clock-in when:

- Open segment’s attendance **date ≠ today**;
- Open expected end is null **or** not still running (`expectedEnd ≤ clock_in` or `now ≥ expectedEnd`);
- Now is within `force_close_before_shift_minutes` (default 60) of today’s expected start (or start null).

`resolveStaleForceCloseTime`:

- Prefer `expectedEnd` **only if** `expectedEnd > clock_in`;
- Else credit `min(now, clock_in + plannedMinutes)`;
- If still `≤ clock_in` → **`clock_in + 1 minute`** (legacy collapse; repair command exists).

Sets `is_auto_clock_out = true`. **This fabricates a checkout** — there is no separate “missing checkout” state after stale-close.

---

## 5. Multi-shift same day

- Allowed as **separate attendance rows** when `shift_type` differs (unique key).
- Same type again = **additional segments** on the same row (looks “merged” in H:i first→last display).
- `preferredShiftType` uses `availableShiftTypes` → currently **all** `plannedAllowedTypes` (does **not** exclude types already used that day). Evening preference can still return the morning type → segment append instead of second shift row.

---

## 6. Calculations → reports → payroll

| Stage | Behavior |
|---|---|
| Recalculate | Synchronous on every punch / force-close |
| Grace | Workday ≥ 2026-10-01: ≤15 late/early counted 0; else full raw. Pre-Oct closed rows freeze stored late/early |
| UI times | Often `format('H:i')` only — overnight spans lose date context |
| Payroll | Aggregates attendance facts; APPROVED/PAID immutable |

---

## 7. Indexes & transactions

- Attendance unique `(user_id, date, shift_type)`.
- Segments unique `(attendance_id, sequence)`.
- Clock paths use `DB::transaction` around segment write + recalculate.
- No row-level lock / import mutex (single-user HTTP clock; concurrency risk is low but dual-tab possible).

---

## 8. Pipeline verdict

| Stage | Present? | Notes |
|---|---|---|
| Raw punch | Partial | Segments only; mutable |
| Import/normalization | No | Browser clock |
| Employee resolution | Auth user | No device mapping |
| Shift assignment | Yes | Profile + TL assignment |
| Shift matching | Soft | Preference + type pick; not punch-to-occurrence matching |
| Attendance session | Yes | Row per type/day + segments |
| Calculations | Yes | `AttendanceTimeCalculator` |
| Reports | Yes | Multiple consumers |
| Payroll | Yes | Date-aware grace |

**Primary risk surfaces:** stale force-close + calendar-day windows; preferred shift not excluding completed types; header first/last time display for multi-segment rows.

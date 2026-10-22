# Shift matching rules (post-forensic repair)

Canonical services: `AttendanceService`, `ShiftWindowResolver`, `ShiftConfig`.

## 1. Attendance date

- `attendance.date` = calendar date of **clock-in** (`$at->toDateString()`).
- Overnight checkout may fall on the next calendar day; store real datetimes.

## 2. Expected start / end

- Assignment window if SCHEDULED for that date+type; else employee profile window; else type defaults.
- If `end_time ≤ start_time` → end is **next calendar day** (true overnight: 20:00→08:00, 22:00→10:00, 21:00→00:00).
- If `end_time > start_time` → same calendar day (including **00:00→12:00** — **not** auto-overnight).
- `alignEndWithPlannedHours` may shorten a window that is much longer than planned hours.

## 3. Available vs preferred shift type

| Method | Behavior |
|---|---|
| `availableShiftTypes` | All `plannedAllowedTypes` for the day (includes completed types for **mid-shift break**) |
| `unusedPlannedTypes` | Planned types **without** a closed attendance row that day |
| `preferredShiftType` | Prefer **unused** pool; in-window first, else earliest start. Only if every type is already closed, fall back to all available (mid-break) |

Completed morning `ONLINE_FULL` must not be preferred for evening when `ONLINE_HALF` is unused.

## 4. Sessions vs segments

- Unique `(user_id, date, shift_type)` → one session row per type per day.
- Re-clocking the **same** type after close → new **segment** (+ gap), not a new row.
- Different type → new session row.

## 5. Stale auto-close

Triggered on `/clock` load / clock-in when prior-day open punch exists and lead window to today’s start is met.

| Condition | Action |
|---|---|
| Same calendar day open | Do not auto-close |
| Overnight still before `expectedEnd` | Do not auto-close |
| `expectedEnd` null or `≤ clock_in` (misaligned window) | **Do not auto-close** — leave checkout missing |
| Valid `expectedEnd > clock_in` and past end | Auto-close at `min(now, expectedEnd)`; set `is_auto_clock_out=true` |

Never fabricate `clock_in + planned` or `clock_in + 1 minute` as an employee-verified checkout via stale-close.

## 6. Checkout provenance (UI)

| Signal | Meaning |
|---|---|
| `clock_out` null | Missing checkout (open) |
| `is_auto_clock_out` | System stale-close — **not** employee verified |
| `force_closed_by` | Supervisor end-shift |
| else with `clock_out` | Employee (or equivalent) checkout |

Component: `<x-attendance-punch-range>` shows overnight dates + badges.

## 7. Grace (unchanged)

Effective attendance workday ≥ **2026-10-01**: ≤15 late/early counted 0; at 16 count full 16. Actual punch times never rewritten for grace.

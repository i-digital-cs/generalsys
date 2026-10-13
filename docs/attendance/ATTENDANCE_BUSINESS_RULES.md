# Attendance business rules (Task 1 + Task 1.6)

Canonical implementation: `App\Services\Attendance\AttendanceGracePolicy`.

Config (`config/attendance.php`):

| Key | Default | Meaning |
|---|---|---|
| `grace_policy_effective_date` | **2026-10-01** | Attendance **workday** on/after this date uses the new grace policy |
| `arrival_grace_minutes` | **15** | Late threshold (from effective date) |
| `departure_grace_minutes` | **15** | Early-departure threshold (from effective date) |

Resolution uses the attendance row’s `date` (workday), **never** “today”. Missing/invalid workday → historical-compatible path (`isActiveForAttendanceDate` = false).

## Effective-date gate (Task 1.6)

```text
attendance.date <  2026-10-01  → Historical policy (preserve stored semantics)
attendance.date >= 2026-10-01  → Approved 15-minute grace policy
```

Do **not** assume historical = “grace minutes = 0 and recalculate.” Pre-October systems stored inconsistent late/early values; recomputing would rewrite settled facts.

### Pre-October (historical)

- **Read:** `exactLateMinutes` / summarize counted late-early use **stored** `late_minutes` / `early_departure_minutes` (TL override still wins).
- **Write (`recalculate` on already-closed rows):** do **not** rewrite `late_minutes`, `early_departure_minutes`, or `status` unless an explicit late override path applies.
- **First close before effective date:** legacy behavior (no 15-min grace threshold); late often 0 unless override; early = raw.
- **No silent backfill** of historical rows.
- Settled payroll (APPROVED / PAID) remains immutable; draft refresh uses date-aware aggregation only.

### October 1, 2026 onward

Apply Rules A–C below.

## Late arrival (Rule A)

```text
rawLateMinutes = max(0, actualArrival − scheduledStart)
countedLateMinutes = rawLateMinutes ≤ 15 ? 0 : rawLateMinutes
```

Grace is a **threshold**, not a deduction. Never `16 − 15 = 1`.

## Early departure (Rule B)

```text
rawEarlyDepartureMinutes = max(0, scheduledEnd − actualDeparture)
countedEarlyDepartureMinutes = rawEarly ≤ 15 ? 0 : rawEarly
```

Arrival and departure graces are **independent**.

## Working-hours deficit (Rule C)

Grace-exempt minutes also remove the matching **counted duration deficit**. Actual worked duration is never rewritten.

```text
rawDiff = actualWorkedMinutes − plannedMinutes
exemptArrival = rawLate ≤ 15 ? rawLate : 0
exemptDeparture = rawEarly ≤ 15 ? rawEarly : 0

if rawDiff ≥ 0:
  countedDeficit = 0
  countedSurplus = rawDiff
else:
  countedDeficit = max(0, −rawDiff − exemptArrival − exemptDeparture)
  countedSurplus = 0
```

Classification: `deficit` | `balanced` | `surplus`.

Surplus is **not** auto-approved overtime. Payable OT still requires TL recommend + Admin approve.

## Persistence

| Field | Meaning |
|---|---|
| `clock_in` / `clock_out` | Actual punches (never altered for grace) |
| `raw_actual_hours` | Actual worked duration |
| `late_minutes` | Counted late (grace from effective date); frozen on closed pre-Oct recalculates |
| `early_departure_minutes` | Counted early (same gate) |
| `adjusted_late_minutes` | Admin/TL override (bypasses grace for payroll fact) |
| `actual_hours` / credited | Payroll-credited hours (cap / OT approval) |

Raw late/early remain available via `AttendanceService::rawLateMinutes()` / `rawEarlyMinutes()` and `AttendanceTimeSummary::$rawLateMinutes` / `$rawEarlyMinutes`.

## Payroll

- Aggregation `late_minutes_total` → `exactLateMinutes` (date-aware).
- Draft UI **suggestion** may show punch (pre-grace) lateness; applying/refresh still uses exact/date-aware totals.
- APPROVED refresh and PAID edits remain forbidden (`PayrollPolicy`).

## Historical records

Existing closed pre-October rows may still store inconsistent late/early. **No silent backfill** in Task 1.6. See `TASK_1_6_COMPLETION_REPORT.md`.

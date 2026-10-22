# Incident root cause report (Phase 0 / 0B / 0C)

**Status:** Investigation complete; **TEST repair implemented 2026-10-22** (see `IMPLEMENTATION_REPORT.md`)  
**Timezone:** `Africa/Cairo`  
**PII:** Employee IDs + public English display names only; GPS coordinates redacted from narrative

---

## 1. Executive summary

| Case | Verified root cause | Layer |
|---|---|---|
| **A — Mohamed Reda vs Mohamed Amir** | Reda’s profile is a **same-calendar-day** window `00:00→12:00` while he works **overnight** (~23:00→~11:00). Stale-close treats the open punch as abandoned and **fabricates** `clock_out = clock_in + 12h` (`is_auto_clock_out=1`). Amir’s profile is true overnight `20:00→08:00`, so his session stays open until a real manual clock-out. | Stale force-close + wrong shift window for overnight work |
| **B — Rahma multi-shift** | Evening work is often clocked as a **second segment on `ONLINE_FULL`**, not as a separate `ONLINE_HALF` row. `preferredShiftType` still returns `ONLINE_FULL` after morning is done. UI shows first→last `H:i` → looks “merged”. | Shift preference + segment-on-same-type design + presentation |

**Neither incident is explained by lost device punches, duplicate import, or employee cross-assignment.** There is no device punch import path.

---

## 2. Environment & ownership

See `FORENSIC_ARCHITECTURE_MAP.md` §0.

- Investigation used **SELECT-only** queries against `d14098_general`.
- No historical attendance rows were repaired or rewritten in this phase.
- Implementation of fixes is **stopped** until owner designates a TEST target distinct from this LIVE host.

---

## 3. CASE A — Mohamed Reda vs Mohamed Amir

### 3.1 Identity (verified)

| Alias | `user_id` | `name_en` | Profile window | Planned hours |
|---|---|---|---|---|
| Mohamed Reda | **12** | MOHAMED REDA | `FULL_TIME` **00:00 → 12:00** | 12 |
| Mohamed Amir | **13** | MOHAMEDAMIR | `FULL_TIME` **20:00 → 08:00** (overnight) | 12 |

No TL `shift_assignments` rows for either user on the incident day.

### 3.2 Incident date (verified — not assumed)

**Workday / attendance.date:** `2026-10-20`  
**Physical span:** night of 20 Oct → morning of 21 Oct  
**Reported “11:03 / 11:45”** refers to **morning clock-out times on 21 Oct**, not morning arrivals.

### 3.3 Side-by-side database evidence

| Field | Reda `attendance.id=680` | Amir `attendance.id=681` |
|---|---|---|
| `date` | 2026-10-20 | 2026-10-20 |
| `shift_type` | FULL_TIME | FULL_TIME |
| Segment 1 `clock_in` | **2026-10-20 23:03:15** | **2026-10-20 23:03:28** |
| Segment 1 `clock_out` | **2026-10-21 11:03:15** | **2026-10-21 11:45:31** |
| `is_auto_clock_out` | **1** | **0** |
| `force_closed_by` | NULL | NULL |
| Span | exactly **720** minutes | **762** minutes |
| `raw_actual_hours` | 12.00 | 12.70 |
| `late_minutes` | **1383** (vs midnight start) | 183 (vs 20:00 start) |
| Segment `updated_at` | 2026-10-21 **11:45:23** | 2026-10-21 **11:45:31** |
| Clock-out GPS | **NULL** (fabricated close) | Present (real punch) |

### 3.4 Expected window simulation (code path)

For attendance date `2026-10-20`:

| User | `expectedStart` | `expectedEnd` | `expectedEnd > clock_in`? |
|---|---|---|---|
| Reda | 2026-10-20 **00:00** | 2026-10-20 **12:00** | **NO** (12:00 &lt; 23:03) |
| Amir | 2026-10-20 **20:00** | 2026-10-21 **08:00** | **YES** |

### 3.5 Causal chain (Reda)

```text
1. Reda clocks in 2026-10-20 23:03:15 → attendance.date = 2026-10-20
2. Profile end for that date = 12:00 same day → already before clock-in
3. Overnight still-running guard fails (expectedEnd not > clock_in)
4. Next calendar day, /clock load or clock-in triggers closeStaleOpenBeforeShiftStart
5. resolveStaleForceCloseTime uses clock_in + planned(12h) = 2026-10-21 11:03:15
6. finalizeClockOut(..., automatic: true) → is_auto_clock_out=1, no clock-out GPS
7. Real departure intent ~11:45 cannot attach — session already closed
```

Amir at the same wall-clock morning:

```text
expectedEnd = 08:00 next day > clock_in → overnight guard keeps session open past midnight
Manual clock-out 11:45:31 with GPS → correct record
```

### 3.6 Mapping to reported checklist

| Hypothesis | Result |
|---|---|
| 1. 11:45 punch never received | **True for Reda** — no real out punch; close was fabricated earlier in the algorithm |
| 2. Received but rejected | No rejection log; close happened via stale-close |
| 3. Classified as duplicate | No |
| 4. Assigned to another shift/day | No — same row, fabricated out |
| 5. Assigned to another employee | No — Amir’s out GPS is on Amir’s segment |
| 6. Overwritten by recalculation | Recalculate mirrored segment; did not invent 11:03 (stale-close did) |
| 7. Concurrency / upsert loss | Unlikely; timestamps show Reda auto-close ~8s before Amir’s manual out |
| 8. DB correct, UI wrong | **DB wrong for Reda** (fabricated out). UI `H:i` also hides overnight dates |

**About “appears as 11:03 → 11:03”:** DB shows **23:03 → 11:03**, not identical timestamps. Likely reporting confusion from time-only UI and/or comparing only morning out times. Pattern `HH:MM:SS` of out equals in+12h (same clock-face minutes). Confirmed corruption is **fabricated checkout at in+12h**, not literal equal in/out.

### 3.7 Recurrence (Reda)

| Metric | Value |
|---|---|
| Reda attendance rows | 52 |
| `is_auto_clock_out=1` | **17** |
| Exact 720-minute auto-closes | **12** |

Recent examples (same pattern): 10-21→11:01:53, 10-20→11:03:15, 10-18→11:04:11, 10-17→11:01:02, …

### 3.8 Affected code paths

- `AttendanceService::closeStaleOpenBeforeShiftStart`
- `AttendanceService::resolveStaleForceCloseTime`
- `AttendanceService::forceCloseStaleSegment` → `finalizeClockOut(..., automatic: true)`
- `ClockPageService` / `ClockController` (invoke stale-close)
- `ShiftWindowResolver::expectedEnd` (calendar date + HH:mm; overnight only if end≤start)
- Profile data: user 12 `00:00→12:00` **misrepresents** actual overnight duty

---

## 4. CASE B — Rahma, multiple shifts

### 4.1 Identity

| Alias | `user_id` | Profiles |
|---|---|---|
| Rahma | **21** | `ONLINE_FULL` 10:00→12:00 (2h); `ONLINE_HALF` 21:00→00:00 (3h, overnight end) |

### 4.2 Evidence — correct dual-row day (baseline)

**2026-10-19**

| Row | Type | In → Out | Planned / Raw |
|---|---|---|---|
| 663 | ONLINE_FULL | 10:02 → 12:13 | 2.00 / 2.17 |
| 669 | ONLINE_HALF | 21:00 → 23:27 | 3.00 / 2.45 |

Two independent attendance sessions — **system can do the right thing**.

### 4.3 Evidence — “merged” day (incident pattern)

**2026-10-21 — `attendance.id=687` ONLINE_FULL only**

| Layer | Value |
|---|---|
| Header `clock_in` / `clock_out` | **10:09:29 → 22:58:20** (looks like one long shift) |
| Segment 1 | 10:09:29 → 12:05:45 (~116 min) |
| Segment 2 | 20:57:59 → 22:58:20 (~120 min) |
| `raw_actual_hours` | 3.93 (sum of segments) |
| `planned_hours` | 2.00 (FULL only) |
| Separate ONLINE_HALF row | **Missing** |

Merge stage: **session creation** (second clock-in reused `ONLINE_FULL` → new segment), then **presentation** (first/last H:i). Not a SQL aggregation bug that deleted a HALF row.

### 4.4 Why preference picks FULL again

Simulation at `2026-10-21 20:57:59`:

```text
availableShiftTypes = ONLINE_FULL, ONLINE_HALF
hasAttendanceForShift(ONLINE_FULL) = yes
hasAttendanceForShift(ONLINE_HALF) = no
preferredShiftType = ONLINE_FULL   ← defect
```

Cause: `remainingPlannedTypes()` returns all `plannedAllowedTypes` and **does not exclude types already punched**. After morning FULL ends, FULL is “after_window”; HALF may be “before_window” at 20:57; fallback sorts by start → FULL (10:00) wins over HALF (21:00).

Other merged-pattern days (same structure): 2026-10-18, 10-14, 10-13, …

### 4.5 Affected code paths

- `AttendanceService::preferredShiftType` / `remainingPlannedTypes` / `availableShiftTypes`
- `AttendanceService::clockIn` existing-row → new segment branch
- Unique key `(user_id, date, shift_type)` (by design enables segments)
- TL/employee views formatting `clock_in`/`clock_out` as `H:i` only

---

## 5. PHASE 0B — Overnight shift audit

| Profile pattern | Handled by `expectedEnd` addDay? | Stale-close safe if punch inside window? |
|---|---|---|
| 20:00→08:00 (Amir) | Yes | Yes (while before end) |
| 22:00→10:00 (Umer) | Yes | Yes |
| 11:00→01:00 (Eid) | Yes | Yes |
| 21:00→00:00 (Rahma HALF) | Yes | Yes |
| **00:00→12:00 (Reda)** | **No overnight** (end &gt; start) | **No** — night punch after noon end → fabricated close |

**Invariant gap:** overnight must be represented as datetime boundaries for the **shift occurrence**, not only HH:mm on `attendance.date`. Reda’s config is a daytime window that **steals** night work into stale-close.

Calendar `attendance.date` = clock-in calendar day is acceptable **if** expected end can extend past midnight **relative to that occurrence**. It fails when the configured window ends before the actual night punch.

---

## 6. PHASE 0C — Global root cause themes

| Theme | Finding |
|---|---|
| Date-only grouping | Sessions keyed by calendar `date` + type; overnight OK only if window wraps |
| `whereDate` | Used for assignment/attendance lookup by calendar date |
| Midnight rollover | Implemented via end≤start → addDay; **not** for 00:00→12:00 |
| First/last punch aggregation | Header mirrors first in / last out — multi-segment looks merged |
| Duplicate suppression | N/A (no device import) |
| Checkout = check-in fabrication | **Yes** via stale-close (`+planned` or `+1 minute`); missing out does **not** stay null after stale-close |
| Race / upsert | Low evidence; Reda/Amir not a shared-row race |
| Recalculation overwrite | Recalc updates derived fields; punch times come from segments |
| UI discrepancy | Time-only display worsens overnight/multi-segment confusion |

### Global counts (read-only snapshot)

| Pattern | Count |
|---|---|
| `is_auto_clock_out=1` | 38 |
| Exact 720-min auto-close | 16 (12 are Reda) |
| Span ≤ 2 minutes | 5 (mostly short real punches, not the Reda pattern) |
| Currently open punches | 9 |

Auto-close concentration: Reda 17, Kareem 5, Mohamed Eid 4, …

---

## 7. Domain invariants (proposed — Phase 1 preview, not yet implemented)

1. Punches (segments) must not be silently replaced by synthetic checkouts without an auditable “unresolved / force-closed” reason visible to HR.
2. Each session binds to employee + **shift occurrence** (type + occurrence start datetime).
3. Overnight = real datetimes; never treat end-before-punch as “credit planned length” without policy.
4. Multiple planned types same day → separate sessions; do not default to segment-append on a completed type.
5. Prefer excluding already-completed types from preferred selection (unless mid-shift break explicitly chosen).
6. Missing checkout remains null until real punch, supervisor close, or explicitly labelled policy close.
7. Preserve Task 1.6 grace + payroll immutability.

---

## 8. What we will **not** do next without TEST + approval

- Patch individual Reda/Rahma historical rows as the “fix”
- Deploy to LIVE
- Run `migrate:fresh` / wipe / truncate
- Silent punch fabrication beyond existing stale-close (already the bug)

---

## 9. Proposed smallest safe fix direction (design only)

1. **Data/config:** Correct Reda (and any similar) profile to a true overnight window matching duty (e.g. 23:00→11:00 or 20:00→08:00) — config change, not code, but validate stale-close after.
2. **Code:** Stale-close must not invent `clock_in+planned` when `expectedEnd ≤ clock_in`; leave open or mark `UNRESOLVED` / require supervisor close.
3. **Code:** `remainingPlannedTypes` / preference exclude types that already have a **closed** attendance that day (keep explicit mid-break re-entry as an intentional action).
4. **UI:** Show date on overnight out times; show per-segment rows for multi-punch.
5. Tests: matrix Phase 3 (deferred until TEST env).

---

## 10. Phase gate

| Deliverable | Status |
|---|---|
| `FORENSIC_ARCHITECTURE_MAP.md` | Done |
| `INCIDENT_ROOT_CAUSE_REPORT.md` | Done (this file) |
| Code changes | **Not started — BLOCKED on TEST identity** |
| Remaining docs (rules / regression / impact / release) | Deferred to later phases after owner review |

**Final Phase 0 verdict:** Root causes **proven with DB + code evidence**. Ready for owner review of fix design. **Not approved for implementation on this host.**

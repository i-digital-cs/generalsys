# Attendance calculation discrepancy report (before → after SSOT)

Environment: **TEST only**. No LIVE deploy. No rewrite of 17+1 fabricated auto-close suspects. No settled payroll mutation.

## Critical duplicates found

| Duplicate | Authoritative | Divergent consumer (before) | Effect |
|---|---|---|---|
| Counted late | `exactLateMinutes` | History summed `attendances.late_minutes` | Flexible-start rows store `0` while punch late > grace → history showed 0, payroll showed counted |
| Counted early | `exactEarlyMinutes` (new) | HoursComparison / History summed `early_departure_minutes` | Same class of drift post-effective-date if stored lagged recalculate |
| Duration balance inputs | Grace via raw late/early | History already used raw* (OK) | Kept; routed through engine `dayDurationBalance` |
| Clock reminder late/early | exact* | Stored columns | Reminder could disagree with payroll |

## Before / after (representative)

| Scenario | History late before | Payroll / exact late | After (all consumers) |
|---|---|---|---|
| Oct day, clock-in +16m, stored late 0 | 0 | 16 | **16** |
| Oct day, clock-in +15m | 0 | 0 | **0** |
| Sep overnight, stored late 0, punch 104 | 0 | 0 (pre-effective stored) | **0** (unchanged historical) |
| TL override `adjusted_late_minutes=20` | stored sum | 20 | **20** |

## Historical / payroll impact

| Item | Impact |
|---|---|
| Pre-2026-10-01 rows | exact* still returns stored → no silent reclassify |
| Fabricated auto-closes (17+1) | Not rewritten; provenance `AUTO_STALE` for display only |
| APPROVED/PAID payslips | Snapshot retained; live aggregate may differ until draft-only refresh |
| LIVE | Not deployed |

## Rule responsible for prior drift

Flexible clock-in leaves persisted `late_minutes=0` while `exactLateMinutes` recomputes punch lateness from the grace effective date. History/UI that trusted the column disagreed with payroll.

## Remaining exceptions / risks

1. `AttendanceTimeSummary::appliedLateMinutes` still reflects the **persisted** column for review “applied” labeling; cross-screen counted metrics use `AttendanceCalculationResult::exactLateMinutes`.
2. `shortfall_raw` / `shortfall_credited` remain physical hour gaps (by design).
3. Some Blade still labels “late minutes” without saying “counted” — naming polish optional; values now agree.
4. N+1: `exact*` may resolve assignment per row; large reports should continue eager-loading `user` / reuse collections (parity tests use small fixtures).

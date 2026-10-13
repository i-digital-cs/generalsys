# Task 4 — Team month summary optimization

**Date:** 2026-10-13  
**Authorization:** Owner-approved Task 4 only (evidence-based performance; preserve attendance/payroll/auth semantics).  
**Schema / indexes / historical mutation:** none.

---

## 1. Original bottleneck

`HoursComparisonService::teamMonthSummary` called `monthForUser` **once per visible employee**.

Each `monthForUser` issued ~4–8 queries (assignments, attendances+eager, vacation dates, rest dates, plus membership/auth overhead).

**Consumers:** `DashboardCalendarService::forActorTeam` (TL/Admin calendar / team hours board). No other callers.

---

## 2. N+1 root cause

**CONFIRMED.** Query count grew linearly with team size (~7 queries × N).

Repeating per employee:

| Data | Pre-fix access |
|---|---|
| Shift assignments (month window) | 1 query / employee |
| Attendances (+ segments/gaps) | 1+ eager / employee |
| Rest days in month | 1 / employee |
| Approved/forced vacation overlap | 1 / employee |

Shared config / grace policy math was already canonical; the defect was **data loading**, not a second policy implementation.

---

## 3. Dataset & engine

| Item | Value |
|---|---|
| Engine (measured) | **SQLite in-memory** (PHPUnit / one-off migrate:fresh) |
| MySQL isolated fixture DB | **NOT AVAILABLE** (CREATE DATABASE denied; shared `d14098_general` not used for benchmarks) |
| Host MySQL/MariaDB (info only) | MariaDB **11.4.13** — **not** used for latency measurements |
| Month | Aug 2026 (31 days) |
| Per employee | 19 PRESENT attendance days + 1 rest day |
| Team sizes | 1, 10, 50 (, 100 after) |

---

## 4. Before / after metrics (synthetic SQLite)

### Query count

| Employees | BEFORE queries | AFTER queries |
|---|---:|---:|
| 1 | 8 | **7** |
| 10 | 71 | **7** |
| 50 | 351 | **7** |
| 100 | (not measured before) | **7** |

**Query growth:** LINEAR → **BOUNDED** (constant batch set for this fixture shape).

### Wall time (same machine; illustrative, not Hostinger SLA)

| Employees | BEFORE ms | AFTER ms |
|---|---:|---:|
| 1 | 14.4 | 12.5 |
| 10 | 84.9 | 62.2 |
| 50 | 433.9 | 324.8 |
| 100 | — | 617.6 |

CPU still scales with `N × days_in_month` PHP loops (expected). Peak memory stayed in the ~42–55 MB range for these fixtures.

---

## 5. Architecture change

`teamMonthSummary`:

1. Resolve authorized team via existing `visibleEmployees` / `TeamMembershipService`.
2. One month window `[startOfMonth, endOfMonth]`.
3. Batch-load assignments, attendances (`segments`,`gaps`), rest days, vacations for the team ids.
4. Group by `user_id` → date.
5. For each employee, for each calendar day: reuse **`plannedForDay`** + **`actualForDay`** (same as `monthForUser`).
6. Return the same contract: `{ user, planned, actual, pct }`.

`monthForUser` unchanged (still the full day/totals/shift_breakdown path for single-user dashboards and payroll).

**No** duplicate grace implementation. **No** payroll formula change. **No** schema/index migration.

---

## 6. Business parity evidence

Tests in `tests/Feature/Performance/TeamMonthSummaryPerformanceTest.php`:

- Summary totals ≡ `monthForUser` totals (planned/actual/pct) for multi-employee teams.
- Unauthorized outsider excluded.
- Empty team → empty collection.
- Vacation + assignment overlay month (Oct 2026) parity.
- Pre-October (Sep 2026) parity.
- Query budget: ≤ 20 queries for N∈{1,10,50}; 10→50 growth not linear.

Also `QueryBudgetTest::test_team_month_summary_stays_under_bounded_budget`.

---

## 7. Remaining performance risks

1. **MySQL production latency still NOT MEASURED** — SQLite timings are not Hostinger wall-clock proof.
2. `monthForUser` (single-user / payroll / calendar focus) unchanged — still builds full day intervals.
3. Large teams still load month attendances once into memory (bounded by team×month).

**Closed:** Task 5 final totals-only CPU path for `teamMonthSummary` — see `TEAM_MONTH_SUMMARY_CPU_PROFILE.md` closure section. No further attendance performance phases.

---

## 8. Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

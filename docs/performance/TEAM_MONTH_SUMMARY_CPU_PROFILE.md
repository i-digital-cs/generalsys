# Task 5 — `teamMonthSummary` CPU profiling + final closure

**Date:** 2026-10-13  
**Status:** **CLOSED** — totals-only CPU optimization implemented (final attendance performance task).  
**Disclaimer:** Synthetic **SQLite :memory:** fixtures. **Not** Hostinger/MySQL production latency.

Related: `docs/performance/TEAM_MONTH_SUMMARY_OPTIMIZATION.md` (Task 4 query fix).

---

## Final closure (implementation)

**Change:** `teamMonthSummary` uses hours-only helpers (`plannedHoursForDay` / `actualHoursForDay`) mirroring `plannedForDay` / `actualForDay` branching and credited-hours rules, without intervals/labels/`__()`. Caches profile planned hours + default-type count per employee for the request. Precomputes month date strings. Drops unused segments/gaps eager loads (hours use `credited_hours` only). **`monthForUser` unchanged.**

| Metric | Before (Task 5 profile) | After (final) |
|---|---:|---:|
| Queries (N=10/50/100) | 7 / 7 / 7 | **5 / 5 / 5** |
| Wall ms N=10 | 62.2 | **16.2** |
| Wall ms N=50 | 324.8 | **59.1** |
| Wall ms N=100 | 617.6 | **122.2** |

Parity: `TeamMonthSummaryPerformanceTest` vs `monthForUser` totals. Full suite: **981 PASS / 1 SKIPPED**. Schema: none.

---

## 1. Methodology

| Item | Detail |
|---|---|
| Tooling | Manual section timers (`hrtime`), `DB::listen` for driver-reported SQL ms; no Xdebug/PCOV on host |
| DB | Isolated SQLite `:memory:` + `migrate:fresh` per size |
| Fixture | Team leader + N employees; Aug 2026; 19 PRESENT days + 1 rest day each; profile windows 10:00–18:00; **no** shift assignments (unless noted) |
| Sizes | N = 1, 10, 50, 100 |
| Separation | End-to-end wall vs SQL listen time; then post-load PHP hotspot buckets; microbenches of public `plannedForDay` / `actualForDay` |
| Non-goals | Production MySQL, speculative caches, implementation |

**Measured vs hypothesis:** Section A–F numbers are **measured**. Production MySQL share and exact Hostinger ms are **UNKNOWN**.

---

## 2. End-to-end time distribution (measured)

| N | Queries | Total ms | SQL ms (`DB::listen`) | PHP ms (wall − SQL) | PHP % | Peak MB | ms / employee-day (PHP) |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 7 | 12.5 | 0.5 | 12.0 | 96% | 42.5 | 0.39 |
| 10 | 7 | 61.4 | 1.5 | 59.9 | 98% | 44.5 | 0.19 |
| 50 | 7 | 312.2 | 6.4 | 305.8 | 98% | 48.5 | 0.20 |
| 100 | 7 | 601.9 | 10.5 | 591.4 | 98% | 54.5 | 0.19 |

**Dominant bottleneck (measured):** PHP computation scales ~linearly with N (`php_ms / n ≈ 6 ms` for N≥10). Database query **count** remains 7; pure SQL is **≤4%** of wall on SQLite.

Note: Eloquent **hydration** is not in `DB::listen` time; it sits in the “PHP” bucket (see attendance load below).

---

## 3. Hotspot decomposition — N=50 (measured)

Employee×day iterations = 50 × 31 = **1550**.

| Phase | ms | Notes |
|---|---:|---|
| Load attendances (+ segments/gaps hydrate) | 25.9 | Largest load cost |
| Load assignments | 0.3 | Empty in this fixture |
| Load rest / vacation | 1.1 / 0.2 | Batch OK |
| Per-user `groupBy` date | 13.2 | Collection regroup after batch |
| Carbon day cursor | 1.9 | Minor |
| `Collection::get` | 2.6 | Minor |
| **`plannedForDay` (1550 calls)** | **131.0** | ~43% of compute loop |
| **`actualForDay` (1550 calls)** | **127.0** | ~42% of compute loop |
| Full employee×day loop | 303.1 | Matches ~PHP share of end-to-end |

Fixture shape: **1500 / 1550** days use **profile fallback** (no assignment, not off). **950** days have attendance rows.

---

## 4. Microbenches (measured)

### `plannedForDay` (5000 calls)

| Path | µs / call |
|---|---:|
| Profile fallback (empty assignments) | 63.8 |
| Assignment plan | 61.3 |
| Rest-day plan | 3.1 |

Profile and assignment paths are similarly expensive here because both build **interval arrays** and call **`__()`** for labels — even though `teamMonthSummary` only keeps **hours**.

Translator alone (`__('clock.shifts.FULL_TIME')`): ~2.8 µs/call (small share of profile path, not the whole story).

### `actualForDay` (5000 calls)

| Path | µs / call |
|---|---:|
| Empty collection | 0.3 |
| One attendance (+ segments/gaps loaded) | **100.4** |

### Date iteration (50 × 31)

| Approach | ms |
|---|---:|
| Carbon parse bounds per employee | 17.0 |
| Shared bounds + `copy()` (current style) | 11.2 |
| Precomputed `Y-m-d` string list | **0.02** |

Carbon is **not** the dominant cost vs planned/actual, but precomputed dates are cheap insurance.

---

## 5. Database vs PHP (summary)

| Bucket | Finding |
|---|---|
| Pure SQL | **~2–4%** of wall (SQLite); grows slowly with result size |
| Eloquent hydrate / groupBy | Meaningful (≈26 ms attendances + ≈13 ms regroup at N=50) |
| Day policy math / interval build | **Dominant** (`plannedForDay` + `actualForDay` ≈ 85% of compute loop) |

MySQL production SQL share: **UNKNOWN** (no isolated MySQL benchmark authorized/available).

---

## 6. Memory (measured)

| N | Peak MB (process) | Approx Δ during summary call |
|---:|---:|---:|
| 1 | 42.5 | +2 |
| 10 | 44.5 | +2 |
| 50 | 48.5 | +4 |
| 100 | 54.5 | +6 |

No runaway allocation on these fixtures; cost is mainly **CPU**, not memory pressure.

---

## 7. Top 3 hotspots (evidence)

1. **`plannedForDay` × employees × days** — 131 ms / 1550 calls at N=50; mostly **profile fallback** rebuilding hours **and** translated intervals every day.  
2. **`actualForDay` × employees × days** — 127 ms / 1550; empty days cheap; **days with attendance** build rich interval metadata unused by summary totals.  
3. **Attendance Eloquent load + per-user date `groupBy`** — ~39 ms combined at N=50 (hydrate + regroup), secondary to day loops.

---

## 8. Proposed low-risk improvements (NOT implemented)

Preserve 7-query batching, `visibleEmployees` auth, Oct-2026 grace boundaries (summary does not re-run grace; it uses stored credited/planned rules via existing helpers), and payroll (unchanged `monthForUser`).

| # | Change | Est. benefit | Uncertainty | Regression risk |
|---|---|---|---|---|
| 1 | **Totals-only day helpers** (or flags) that compute **hours only**, skipping interval/`__()` construction, still calling the same branching as `plannedForDay` / credited actual | **30–50%** of PHP loop on this fixture | Medium — must parity-test vs `monthForUser` totals | Medium if branch drift |
| 2 | **Cache profile planned hours once per employee** for empty-assignment days | **~20–35%** of `plannedForDay` time when few assignments | Low–Med | Low if cache keyed by user profiles |
| 3 | **Precompute month `Y-m-d` list once**; avoid empty `collect()` alloc on misses | Small (few %) | Low | Very low |
| 4 | Speculative Redis/file cache of team month rows | Not recommended now | High staleness risk | High vs live punches |

**Do not** add a second attendance engine or schema.

---

## 9. Is further optimization justified?

**YES (conditionally)** — for TL/Admin dashboards with **tens of employees**, wall time is still ~linear in N and almost all PHP. Query work is already solved.

**Priority:** implement only after owner approval; ship with **strict parity tests** against current `monthForUser` totals (Task 4 tests already provide the template).

**Not justified yet:** micro-optimizing Carbon alone, or indexes (SQL not dominant on SQLite; MySQL unmeasured).

---

## 10. Business / DB impact of this task

```text
BUSINESS LOGIC CHANGES: NONE
DATABASE CHANGES: NONE
APPLICATION CODE CHANGES: NONE
```

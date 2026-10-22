# Shift configuration audit (TEST — proposals only)

**No employee profiles or assignments were modified.**

## Findings

### 1. Incorrect same-day window used for night attendance

| user_id | name_en | Current window | Evidence | Proposed correction (Admin UI) |
|---|---|---|---|---|
| **12** | MOHAMED REDA | FULL_TIME **00:00→12:00** (12h) | 50+ night clock-ins (HOUR≥20); 16 fabricated auto-closes | Set true overnight matching duty, e.g. **20:00→08:00** or **23:00→11:00** with `expected_hours=12` (confirm with ops) |

### 2. Correct overnight profiles (leave as-is)

| user_id | name_en | Window |
|---|---|---|
| 13 | MOHAMEDAMIR | 20:00→08:00 |
| 22 | Umer Farooq Muhammed Riasat | 22:00→10:00 |
| 11 | MOHAMED EID | 11:00→01:00 (14h) |

### 3. Multiple scheduled / default shifts

| user_id | name_en | Profiles | Notes |
|---|---|---|---|
| 2 | Rawan Adel Hassan | FT 10–18 + ONLINE_HALF 06–10 | OK; future assignments present |
| 15 | SAMAR RASHEDY | FT 10–18 + ONLINE_HALF 18–23 | OK |
| **21** | Rahma … | ONLINE_FULL 10–12 + ONLINE_HALF 21–00 | Code fix prefers unused type; **historical** multi-segment FULL evenings remain (see below) |

### 4. Ambiguous / conflicting boundaries

| Issue | Detail | Proposal |
|---|---|---|
| Doaa (7) assignment vs profile | Profile overnight 12:00→00:00; SCHEDULED assignment times **00:00→12:00** on related date | Review/cancel stale assignments for inactive users |
| Rahma historical evenings on FULL | Days with 2 segments on ONLINE_FULL (e.g. att 687, 651, 610, 600) | No auto reclassify; going-forward preference uses HALF. Optional future data project |
| Reda `work_start/end` mirrors 00:00–12:00 | Same misconfiguration as JSON profile | Update both profile JSON and work_* when correcting |

### 5. Ambiguous shift selection (mitigated in code)

- Prefer unused planned types after a type is closed.
- Mid-shift break still requires explicit same-type selection.
- No automatic assignment changes.

---

## Owner checklist (config, not deploy)

1. Correct Reda overnight window in Admin employee form.  
2. Audit inactive employees’ leftover SCHEDULED assignments.  
3. Confirm Rahma’s two profiles remain intentional.  
4. Do **not** bulk-edit historical attendance when changing profiles.

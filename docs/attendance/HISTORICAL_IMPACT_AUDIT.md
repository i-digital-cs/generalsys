# Historical impact audit (READ-ONLY on TEST snapshot)

**Command:** `php artisan attendance:detect-fabricated-auto-closes`  
**Result:** Checked 38 auto-close rows; **18 suspicious** (expected end ≤ clock-in).  
**Rows modified:** 0

## Confirmed vs suspicious

| Class | Definition | Count (TEST snapshot) |
|---|---|---|
| Confirmed fabrication pattern | `is_auto_clock_out=1` and profile/assignment `expectedEnd ≤ clock_in` | **18** |
| Legitimate auto-close | Auto-close with `expectedEnd > clock_in` (true overnight / day shift ended) | 20 of 38 |
| Same-timestamp ≤2 min | Short real punches | 5 (separate; not this repair) |

### Suspicious by employee (anonymized)

| user_id | name_en | Suspect rows |
|---|---|---|
| 12 | MOHAMED REDA | 16 |
| 7 | DOAA | 1 |
| (others in list) | — | see command output |

Incident row **att=680** (Reda 2026-10-20 23:03→11:03 auto) is in the suspect set.

## Rahma “merged” days

Not corrupted punches — evening recorded as **second segment on ONLINE_FULL**. Going forward preference prefers HALF. Historical multi-segment FULL rows remain as stored; optional future reclassification is a **separate** owner-approved data project (would split segments into HALF rows carefully).

## Payroll impact

- Suspect auto-closes often store `raw_actual_hours ≈ planned` (e.g. 12.00).
- If real work was longer (Amir baseline ~12.7h), credited hours may be understated; if shorter, overstated.
- **Do not auto-adjust APPROVED/PAID payroll.** Draft months may be refreshed after punch repair + owner review.

## Proposed historical repair plan (needs owner approval)

1. Full DB backup on LIVE before any write.
2. Dry-run detector on LIVE.
3. For each suspect row: HR confirms true out time (GPS/logs/supervisor) or leave as-is with UI badge.
4. Repair command (not yet shipped): only with `--force` + per-id allowlist; never silent bulk rewrite.
5. Re-run draft payroll refresh only for open months.

**No automatic repair executed in this engagement.**

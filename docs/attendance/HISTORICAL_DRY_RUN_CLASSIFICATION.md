# Historical dry-run classification (18 suspects)

**Command:** `php artisan attendance:detect-fabricated-auto-closes`  
**Executed:** 2026-10-22 on TEST (`d14098_general`)  
**Rows modified:** **0**  
**Rule:** Do **not** infer actual checkout from scheduled shift endings. Peer (Amir) outs are **reference only**, not repair values.

## Summary

| Class | Count |
|---|---|
| CONFIRMED FABRICATED | **17** |
| NEEDS MANUAL REVIEW | **1** (att=666 — multi-segment; morning out has GPS, overnight out does not) |
| FALSE POSITIVE | **0** |

Common evidence for CONFIRMED FABRICATED:

- `is_auto_clock_out = 1`
- Header / last-segment `clock_out_lat` = **NULL** (no employee GPS checkout)
- Current window resolution yields `expectedEnd ≤ clock_in` (misaligned)
- Often `span ≈ 720` minutes (`clock_in + planned 12h`)

---

## Record-by-record

### user=12 MOHAMED REDA — profile `FULL_TIME 00:00→12:00` (day window; night work)

| att | date | type | clock_in | stored clock_out | auto | force | raw/actual/planned | expected_end | out GPS | Classification | Payroll note | Recommended action |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 35 | 2026-08-19 | FULL_TIME | 22:45:06 | 2026-08-20 **06:00:00** | 1 | — | 7.23 / 7.23 / 12 | 2026-08-19 12:00 | **none** | **CONFIRMED FABRICATED** | Aug DRAFT | Leave; optional HR truth later. Do not use schedule as out. |
| 102 | 2026-08-26 | FULL_TIME | 21:10:52 | 2026-08-27 **06:00:00** | 1 | — | 8.82 / 8.82 / 12 | 2026-08-26 12:00 | **none** | **CONFIRMED FABRICATED** | Aug DRAFT | Same |
| 528 | 2026-10-06 | FULL_TIME | 23:02:22 | 2026-10-07 11:01:27 | 1 | — | 11.98 / 12 / 12 | 2026-10-06 12:00 | **none** | **CONFIRMED FABRICATED** | No Oct payslip row found for user 12 | Leave; fix profile going forward |
| 542 | 2026-10-07 | FULL_TIME | 23:05:46 | 2026-10-08 11:05:46 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 554 | 2026-10-08 | FULL_TIME | 23:08:19 | 2026-10-09 11:08:19 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 562 | 2026-10-09 | FULL_TIME | 23:06:40 | 2026-10-10 11:06:40 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 573 | 2026-10-10 | FULL_TIME | 23:01:34 | 2026-10-11 11:01:34 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 584 | 2026-10-11 | FULL_TIME | 22:10:56 | 2026-10-12 10:10:56 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 593 | 2026-10-12 | FULL_TIME | 23:10:37 | 2026-10-13 11:10:37 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 616 | 2026-10-14 | FULL_TIME | 23:20:07 | 2026-10-15 11:14:46 | 1 | — | 11.90 / 11.90 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 629 | 2026-10-15 | FULL_TIME | 22:59:11 | 2026-10-16 10:59:11 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 636 | 2026-10-16 | FULL_TIME | 23:04:17 | 2026-10-17 11:04:17 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 646 | 2026-10-17 | FULL_TIME | 23:01:02 | 2026-10-18 11:01:02 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| 657 | 2026-10-18 | FULL_TIME | 23:04:11 | 2026-10-19 11:04:11 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |
| **680** | **2026-10-20** | FULL_TIME | **23:03:15** | **2026-10-21 11:03:15** | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** (incident Case A) | — | Leave; UI will show auto badge after deploy |
| 691 | 2026-10-21 | FULL_TIME | 23:01:53 | 2026-10-22 11:01:53 | 1 | — | 12 / 12 / 12 | …12:00 | **none** | **CONFIRMED FABRICATED** | — | Leave |

**Peer reference (Amir user=13 same attendance.date clock_out)** — not evidence of Reda’s true out; for HR context only (e.g. att 680 peer out `2026-10-21 11:45:31` with GPS).

### att=666 (Reda) — multi-segment

| Field | Value |
|---|---|
| date / type | 2026-10-19 / FULL_TIME |
| Seg1 | 12:19:12 → 12:43:06 (**has out GPS** — real short segment) |
| Seg2 | 23:14:22 → 2026-10-20 11:13:24 (**no out GPS**, auto header) |
| raw / planned | 12.37 / 12.00 |
| Classification | **NEEDS MANUAL REVIEW** |
| Why | Morning checkout is employee-verified; overnight auto-close is fabricated. Do not invent seg2 out from schedule. |
| Action | HR review; optional split/repair only with explicit evidence |

### user=7 DOAA (INACTIVE) — att=412

| Field | Value |
|---|---|
| date / type | 2026-09-26 / ONLINE_FULL |
| clock_in / out | 12:12:10 → 2026-09-27 00:12:10 (exactly +12h) |
| auto / GPS | 1 / **none** |
| Profile now | ONLINE_FULL 12:00→00:00 (overnight) |
| Conflicting assignment | SCHEDULED row resolves as **00:00→12:00** on that date (timezone/date cast) |
| Classification | **CONFIRMED FABRICATED** |
| Payroll | Sep **APPROVED** net 4000 — **do not auto-adjust** |
| Action | Leave historical; no silent payroll change |

---

## Potential payroll impact (aggregate)

| Period | Users | Status | Guidance |
|---|---|---|---|
| 2026-08 | Reda DRAFT, Doaa DRAFT | Draft | May refresh after any future approved punch repair |
| 2026-09 | Reda DRAFT; Doaa **APPROVED** | Mixed | APPROVED immutable without owner payroll ticket |
| 2026-10 | No Reda payslip row on TEST | — | Going-forward code fix prevents new fabrications |

Inflated/deflated hours risk: fabricated outs often force `raw ≈ planned (12h)`. True duration unknown without GPS/supervisor evidence — **do not substitute schedule end**.

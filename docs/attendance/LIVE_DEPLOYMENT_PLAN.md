# LIVE deployment plan — attendance forensic repair (RC1)

**Status:** Ready for owner-approved LIVE promote — **not executed**.  
**Release branch:** `release/attendance-forensic-rc1`  
**LIVE host:** `u449614343@151.106.117.60` → `public_html/system/` · DB `u449614343_system`  
**TEST host:** `d14098` · `generalsys.idigital-cs.com` · DB `d14098_general`

---

## Pre-deployment backup checklist

- [ ] `mysqldump` LIVE DB to dated file on LIVE server (and copy off-box)
- [ ] Snapshot/tarball of LIVE `app/Services/Attendance/`, `resources/views/`, `lang/`, `app/Console/Commands/`, `docs/attendance/`, `tests/`
- [ ] Confirm no uncommitted LIVE hotfixes that would be overwritten
- [ ] Note current LIVE git commit / file mtimes if not git-deployed

## Code deployment steps

1. Maintenance banner optional (clock may be used 24/7 for overnight staff — prefer short window).
2. Upload **only** RC1 changed files (see commit manifest) — exclude `.env`, `backup.sql`, `error_log`, `vendor`, `node_modules`.
3. On LIVE: `php artisan optimize:clear` (config/route/view/cache as usual for Hostinger).
4. Do **not** run `migrate` for this RC (no migrations).
5. Do **not** run historical repair commands with writes.
6. Run read-only: `php artisan attendance:detect-fabricated-auto-closes` and archive output.

## Migration requirements

**None.**

## Cache / queue / workers

| Item | Action |
|---|---|
| `file` cache | `optimize:clear` / `cache:clear` |
| `database` queue | No new jobs required; existing `queue:work --stop-when-empty` via schedule if configured on LIVE |
| Scheduler | No new attendance cron; stale-close remains request-driven |
| OPcache | Restart PHP if Hostinger provides toggle; else wait for natural recycle |
| Sessions | `file` driver — no Redis; users stay logged in |

## Post-deployment smoke tests

1. **Overnight (Amir-like):** clock in after 20:00; next morning before 08:00 still open; manual out; `is_auto_clock_out=0`; punch-range shows date if cross-day.
2. **Misaligned night + day window:** open remains after `/clock` load next day; no fabricated out; employee can clock out manually.
3. **Dual profile (Rahma-like):** complete morning type; evening preferred = unused type; two attendance rows.
4. **Mid-shift break:** explicit same-type re-clock creates segment 2.
5. **UI:** auto-closed historical rows show dark auto badge — not as verified employee out.
6. **Grace:** Oct+ attendance ≤15 late counted 0; 16 counts 16 (spot-check one draft).
7. **Payroll:** open APPROVED/PAID slip — save blocked; DRAFT refresh still allowed.

## Rollback procedure

1. Restore backed-up PHP/Blade/lang/command files.
2. `php artisan optimize:clear`.
3. No DB rollback (no schema change; no historical writes from this RC).
4. Re-smoke clock in/out.

## Historical data remediation plan (separate from deploy)

1. Keep fabricated outs as stored for go-live (UI labels them auto).
2. Admin corrects Reda profile (see `SHIFT_CONFIGURATION_AUDIT.md`).
3. Optional later: owner-approved repair command with `--dry-run`, per-id allowlist, backup — **never** infer out from schedule.
4. APPROVED payroll (e.g. Doaa Sep) untouched unless separate payroll ticket.

## Known risks

- Historical 18 suspects remain until optional repair  
- Wrong profiles still mis-label late until Admin edits  
- LIVE and TEST code may drift — compare file hashes before promote

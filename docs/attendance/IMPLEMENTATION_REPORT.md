# Attendance forensic repair — implementation report (TEST)

**Date:** 2026-10-22  
**Environment:** Owner-authorized TEST at `/home/d14098/generalsys.idigital-cs.com`  
**LIVE (do not touch):** Hostinger `u449614343@151.106.117.60` → `public_html/system/` / DB `u449614343_system`

---

## Gate 0 — Isolation (verified)

| Check | Result |
|---|---|
| App directory | This host only; LIVE code on separate Hostinger account |
| Database | `d14098_general@127.0.0.1` — separate from LIVE `u449614343_system` |
| DB grants | User limited to `d14098_general` only |
| Queue / cron | No `generalsys` scheduler cron on this account |
| Mail | `MAIL_MAILER=log` |
| PHPUnit | sqlite `:memory:` (`phpunit.xml`) |
| Owner authorization | Explicit TEST designation |

Residual: DNS for `generalsys.idigital-cs.com` points here; treat as TEST copy with production-shaped data. **Do not deploy these commits to LIVE without the release plan.**

---

## Root causes fixed

### A. Mohamed Reda — fabricated auto-close

- **Before:** Misaligned daytime window (`00:00→12:00`) + night punch → stale-close set `clock_out = clock_in + planned` (or elapsed), marked auto.
- **After:** If `expectedEnd` is null or not after `clock_in`, stale-close **returns null** and leaves checkout missing. Real clock-out / supervisor close remains available.

### B. Rahma — evening reused morning type

- **Before:** `preferredShiftType` could return completed `ONLINE_FULL` for evening.
- **After:** Prefer `unusedPlannedTypes`; mid-shift same-type re-entry still via `availableShiftTypes` + explicit selection.

### C. Overnight

- True overnight (`end ≤ start`) unchanged (`addDay`).
- `00:00→12:00` remains same-day (not auto-overnight).
- Stale-close no longer invents out times for misaligned windows.

### D. UI

- `<x-attendance-punch-range>`: overnight date stamps, open / auto / supervisor badges.
- Wired into TL attendance history + employee dashboard; clock segment list shows cross-day times + flags.

---

## Files changed

| Path | Change |
|---|---|
| `app/Services/Attendance/AttendanceService.php` | Stale-close + preferred/unused types |
| `app/Console/Commands/DetectFabricatedAutoClosesCommand.php` | **New** read-only historical detector |
| `resources/views/components/attendance-punch-range.blade.php` | **New** |
| `resources/views/team-leader/attendance/index.blade.php` | Use punch-range |
| `resources/views/employee/dashboard.blade.php` | Use punch-range |
| `resources/views/employee/clock.blade.php` | Overnight segment display + flags |
| `lang/en/attendance.php` / `lang/ar/attendance.php` | Punch provenance strings |
| `tests/Unit/Attendance/ForensicAttendanceRegressionTest.php` | **New** matrix |
| `tests/Unit/AttendanceServiceTest.php` | Reda-pattern expectation updated |
| `tests/Feature/Admin/DualDefaultShiftsTest.php` | Preferred evening assertion |
| `docs/attendance/*` | Architecture, rules, reports |

---

## Migrations

**None required** for this repair (existing `is_auto_clock_out` / `force_closed_by` suffice). See `DATABASE_MIGRATION_REPORT.md`.

---

## Historical data

- **Not rewritten.** Detector found **18** suspicious misaligned auto-closes on TEST snapshot (mostly user 12).
- Repair of those rows needs **separate owner approval** (see `HISTORICAL_IMPACT_AUDIT.md` / deployment plan).

---

## Verdict (TEST implementation)

**CONDITIONAL → ready for LIVE deployment review after owner accepts residual historical-data plan.**

Code + tests on TEST: complete. LIVE deploy: **not performed**.

# Database migration report — attendance forensic repair

## Summary

| Item | Status |
|---|---|
| SCHEMA MIGRATIONS CREATED | **NO** |
| DATA MIGRATION OR BACKFILL | **NO** (detection command only) |
| PRODUCTION DEPLOYMENT PROCEDURE | Documented in `LIVE_DEPLOYMENT_PLAN.md` — **not executed** |
| HARDCODED ID SAFETY | NOT APPLICABLE |
| DATABASE MIGRATION POLICY | **PASS** (no schema change required) |

## Why no migration

Existing columns already support provenance:

- `attendance.is_auto_clock_out`
- `attendance.force_closed_by` / `force_closed_at`
- `attendance_segments` for multi-punch

Behavioral fix is service-layer only. UI uses existing flags.

## Historical data

Suspicious fabricated auto-closes must **not** be rewritten inside a normal `migrate`.

Detector (read-only):

```bash
php artisan attendance:detect-fabricated-auto-closes
php artisan attendance:detect-fabricated-auto-closes --user=12 --from=2026-10-01 --to=2026-10-22
```

Any future repair = separate owner-approved Artisan command with dry-run, backup, and payroll impact review.

## Rollback of code (no DB down)

Revert the AttendanceService / Blade / lang / test commits. No `migrate:rollback` needed for this change set.

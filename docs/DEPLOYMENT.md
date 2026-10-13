# Deployment & database safety (HR-Pro)

**Mandatory** for Task 1.5, Task 2, and all future work. Policy source of truth for agents: `AGENTS.md` §3.0. Hosting constraints: `MASTER_CONTEXT.md` §0.1 / `AGENTS.md` §2.

## 1. Application deploy (Hostinger shared)

1. Build assets **locally**: `npm run build`.
2. Upload application code + `vendor/` + `public/build` (never run `npm install` / `composer install` on the server unless the owner explicitly changes that rule).
3. Ensure `.env` is production-correct (never commit secrets).
4. Run schema migrations only after backup + authorization (below).
5. Single cron: `* * * * * php artisan schedule:run`.
6. Queue worker: `database` driver (Hostinger-safe process as already documented for the site).

## 2. Database schema deploy

```bash
php artisan migrate --force
```

**Do not** run against production without:

- Explicit deployment authorization.
- Fresh backup / recovery plan.
- Confirmation that migrations are forward-only (no editing old migration files that already ran).

Every schema change (tables, columns, indexes, FKs, defaults, nullability, renames) **must** ship as a **new** migration and an update to `docs/DATABASE_SCHEMA.md` in the same change.

## 3. Data migrations & backfills

| Kind | Mechanism |
|---|---|
| Small, deterministic, required on every env | Version-controlled data migration (idempotent) or reviewed migration step |
| Large, operational, or **payroll / money** sensitive | Dedicated Artisan command or queued job; document the extra command; **owner approval** before production |
| One-off manual SQL / GUI edit | **Forbidden** as the only implementation of a required change |

### ID-based corrections

Auto-increment IDs differ across local / staging / production. Prefer stable business identifiers. If an ID is used: verify logical identity per environment, validate before update, fail safe on mismatch, never update an unrelated row, keep the operation idempotent.

## 4. Pre-complete verification (DB-related tasks)

- [ ] `php artisan migrate:status` understood for the target
- [ ] Migrations run on a **clean scratch** DB
- [ ] Upgrade path tested on a DB that already has prior migrations
- [ ] Existing rows preserved; FK integrity OK
- [ ] Application compatible with post-migrate schema
- [ ] Separate backfill command documented (if any)
- [ ] Rollback / recovery notes documented
- [ ] End-of-step report includes the §3.0 acceptance gate from `AGENTS.md`

## 5. Acceptance gate (copy into task reports)

```text
DATABASE CHANGES: YES | NO
SCHEMA MIGRATIONS CREATED: YES | NO | NOT APPLICABLE
DATA MIGRATION OR BACKFILL: YES | NO | NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED | BLOCKED
HARDCODED ID SAFETY: VERIFIED | NOT APPLICABLE | FAILED
DATABASE MIGRATION POLICY: PASS | FAIL
```

No database-related task is COMPLETE unless required schema and data changes can be reproduced safely in the target environment.

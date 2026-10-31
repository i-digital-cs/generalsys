# GH-9 — Proposed LIVE Deployment Plan (NOT EXECUTED)

**Phase:** GH-9  
**Date:** 2026-10-31  
**Status:** **PROPOSAL ONLY** — do not execute without explicit owner authorization.  
**Environment note:** TEST = `/home/d14098/generalsys.idigital-cs.com` + DB `d14098_general`. LIVE identity is **not** inferred from `APP_ENV`.

---

## 0. Preconditions (must be green)

1. Owner approves `GH9_OPEN_DECISIONS.md` items marked launch blockers (esp. `C-GH-TAX-MIN`, payment flags).  
2. `GH9_RELEASE_ISOLATION_PLAN.md` executed → dedicated GH release artifact (tag/branch) **without** unintended Attendance commits.  
3. Pre-LIVE backup of LIVE application files + LIVE database completed and verified restorable.  
4. Scratch DB dry-run of forward migrations succeeds.  
5. Owner acknowledges: **DB migrations are not assumed reversible**.

---

## 1. Pre-deployment backup

| Step | Action | Notes |
|---|---|---|
| 1.1 | Dump LIVE MySQL (full) to off-host secure storage | Include routines if any |
| 1.2 | Snapshot/tar LIVE app tree (exclude `storage/logs` noise) | Keep `vendor` + `public/build` as deployed |
| 1.3 | Record checksums / timestamps | Recovery evidence |
| 1.4 | Verify backup by restoring to **scratch** host/DB | Do not overwrite LIVE |

---

## 2. Git release isolation

Follow `GH9_RELEASE_ISOLATION_PLAN.md`.

**Deliverable before code upload:** a commit/tag that contains GH-1…GH-8 (+ necessary shared wiring) and **excludes** unrelated Attendance RC commits unless those are explicitly approved for the same window.

Do **not** deploy `release/attendance-ssot-grace-payroll-rc-20261022` tip blindly to LIVE.

---

## 3. Migration inventory (forward-only)

Apply **in filename order** on LIVE (only if tables absent):

1. `2026_10_25_200000_create_gh_catalog_tables`  
2. `2026_10_25_210000_create_gh_customers_zones_contracts_tables`  
3. `2026_10_25_220000_create_gh_orders_tables`  
4. `2026_10_31_110000_create_gh_logistics_tables`  
5. `2026_10_31_120000_repair_gh_utc_timestamps` — review: data repair; on empty LIVE GH tables should be no-op / safe  
6. `2026_10_31_140000_create_gh_invoice_tables`  
7. `2026_10_31_150000_create_gh_payment_tables`  
8. `2026_10_31_160000_add_gh_reporting_indexes`

**Command (when authorized):** `php artisan migrate --force`  
**Never** `migrate:rollback` for GH recovery on LIVE.

### TEST bookkeeping anomaly note

TEST `migrations` ids/batches for GH-1–3 were repaired after GH-4 (see `MIGRATION_INTEGRITY_AUDIT.md`).  

**LIVE risk:** none if LIVE never had partial GH DDL. Fresh forward migrate creates correct batch order.  

**Do not** copy TEST `migrations` rows to LIVE.  

**Do not** edit migration history on LIVE.

---

## 4. Environment configuration (LIVE)

Set/confirm without guessing LIVE secrets in this audit:

| Key | Expected for GH launch |
|---|---|
| `GH_TIMEZONE` | `Asia/Dubai` |
| `GH_STANDARD_VAT_RATE` | Owner-approved (default 5.00) |
| `GH_MIN_ORDER_SURCHARGE_TAX_CLASS` | **Only after C-GH-TAX-MIN** (or leave null to keep ISSUE blocked on surcharge) |
| `GH_PICKUP_LEAD_HOURS` | 24 (unless owner changes) |
| Payment flags | Prefer cash-only launch: consider `online_initiation_enabled=false` until gateway |
| `APP_TIMEZONE` / app TZ | **Do not change** (`Africa/Cairo`) for Attendance/Payroll |
| Queue | `database` |
| Cache | `file` or `database` |

Config file defaults live in `config/general_house.php` (some flags hardcoded booleans today — changing them requires approved code/config change).

---

## 5. Scheduler / cron

Ensure LIVE crontab includes (path adjusted to LIVE app root):

```cron
* * * * * cd /PATH/TO/LIVE && /usr/local/bin/php artisan schedule:run >> /PATH/TO/LIVE/storage/logs/scheduler.log 2>&1
```

Confirms: `queue:work --stop-when-empty`, `gh:promote-pickup-eligible` every 5 minutes (`routes/console.php`).

---

## 6. Permissions & storage

| Path | Need |
|---|---|
| `storage/` | writable |
| `bootstrap/cache/` | writable |
| `storage/logs/` | writable |
| `public/build/` | present (built locally, uploaded) |

No Redis/Supervisor/Node on server.

---

## 7. Cache / optimize (shared hosting cautious)

After code + migrate (authorized):

- `php artisan config:cache` (if used on host)  
- `php artisan route:cache` (verify GH routes still resolve)  
- `php artisan view:clear` / cache as per existing LIVE practice  
- Do **not** run `npm`/`composer install` on Hostinger shared per AGENTS.md  

---

## 8. Smoke tests (post-deploy, non-destructive first)

1. Login as Manager → GH dashboard loads.  
2. Catalog list + create zone (or view-only if prod data freeze).  
3. Create test customer/order on LIVE **only if owner approves** test entities.  
4. Confirm DRIVER menu shows GH workspace; clock still available.  
5. Confirm TEAM_LEADER still **403** on payroll (regression).  
6. Scheduler log receives `schedule:run` within 2 minutes.  
7. `php artisan gh:promote-pickup-eligible` dry observation (no unexpected mass updates).  

---

## 9. Financial reconciliation (first week)

| Check | Method |
|---|---|
| Issued total vs line+VAT+adjustments | Spot-check 5 invoices vs engine |
| Confirmed payments ≤ invoice total | SQL aggregate |
| Pending excluded from AR | Outstanding report vs SUM(CONFIRMED) |
| No orphan DRAFT misuse | Count DRAFT older than N days |

---

## 10. Monitoring

- `storage/logs/laravel.log` + `scheduler.log`  
- Failed jobs table (database queue)  
- GH payment events for unexpected CANCEL volume  
- Error rate on `service/gh/*`  

---

## 11. Recovery procedure

### 11.1 Application-code rollback

- Redeploy previous known-good app tree/tag **without** running reverse migrations.  
- Clear caches.  
- **Leave new GH tables in place** if already migrated (empty or with data).  

### 11.2 Database rollback — **LIMITATIONS**

| Action | Allowed? |
|---|---|
| `migrate:rollback` of GH batches | **Not recommended**; TEST batch anomaly proves history can mislead |
| Drop GH tables | **Destructive** — only with owner approval + backup restore plan |
| Restore full DB backup | Preferred recovery for serious corruption |
| Partial row deletes | Avoid; breaks audit ledger |

**Explicit:** application-code rollback ≠ database rollback. Shipping code back does not undo DDL or payment rows.

---

## 12. Rollback decision tree

```
Issue detected
├─ Code bug only, schema unused/empty → redeploy prior tag; keep schema
├─ Bad data in GH tables → restore from backup to scratch; selective fix OR full restore (owner)
└─ Financial ledger corruption → stop collections; full DB restore from pre-deploy backup
```

---

## 13. What this plan does **not** do

- Does not deploy.  
- Does not change LIVE.  
- Does not claim TEST=`APP_ENV=production` is LIVE.  
- Does not approve tax classification.

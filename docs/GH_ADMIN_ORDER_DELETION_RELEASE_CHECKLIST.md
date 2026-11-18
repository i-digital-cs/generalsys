# GH Admin Order Deletion — LIVE release checklist

**Status:** prepared on TEST, **not executed**. LIVE release requires separate owner authorization (`docs/DEPLOYMENT.md` §2).
**Feature:** D-GH-023 — Manager soft delete of `LATER` / `PICKUP` orders with no confirmed pickup and no financial records.
**Schema:** one additive migration `2026_11_18_160000_add_soft_delete_to_gh_orders_table` (3 nullable columns + 1 FK on `gh_orders`). No data migration, no backfill, no financial table touched.
**Release commits (same branch, in order):** lifecycle fix `3d3dfae`, cleanup `6ce66af`, deletion feature (this commit).

## 0. Approved one-time exception — pre-commit edit of a migration that ran on TEST

`AGENTS.md` §3.0.1 forbids editing a migration that already ran in a shared environment. The owner approved **one** exception (2026-11-18):

- `2026_11_18_160000` first ran on TEST with a single `hasColumn('deleted_at')` guard. Before it was ever committed, pushed or run on LIVE, it was hardened to guard each column and the FK separately, so a re-run after a partial (non-transactional) MySQL DDL failure completes the missing steps instead of skipping them.
- The final schema is identical: TEST was verified read-only after the edit (same types, nullability, FK name `gh_orders_deleted_by_foreign`, `ON DELETE SET NULL`). TEST is not re-run.
- This does not relax the rule. Any future change to this migration after it reaches LIVE must be a new forward migration.

## 1. Pre-flight on LIVE (read-only)

- [ ] Release authorized by owner; window agreed (off-peak — adding the FK can rebuild `gh_orders` and briefly block writes to it).
- [ ] `php artisan migrate:status` — record every **pending** migration. Expected from this release: `2026_11_18_160000_add_soft_delete_to_gh_orders_table`. Any other pending migration must be identified and reviewed before continuing.
- [ ] `gh_orders` has **no** `deleted_at` / `deleted_by` / `delete_reason` columns yet (`SHOW COLUMNS FROM gh_orders LIKE 'delete%'`).
- [ ] `gh_orders.cancel_reason` exists (column placement anchor; from `2026_10_31_110000`).
- [ ] Record baseline (keep the output for §6):
  ```sql
  SELECT COUNT(*) FROM gh_orders;
  SELECT status, COUNT(*) FROM gh_orders GROUP BY status;
  SELECT COUNT(*), COALESCE(SUM(total_payable),0) FROM gh_invoices;
  SELECT COUNT(*), COALESCE(SUM(amount),0) FROM gh_payments;
  SELECT COUNT(*) FROM gh_order_events;
  SELECT COUNT(*) FROM gh_driver_cash_collections;
  ```
- [ ] No queue backlog that touches GH (`SELECT COUNT(*) FROM jobs`); GH has no queued jobs, payroll jobs are unaffected.

## 2. Backups (before any change)

- [ ] Full database backup (Hostinger hPanel backup or `mysqldump --single-transaction --routines --triggers` of the whole LIVE schema), stored off the web root.
- [ ] Additional table-level dump of `gh_orders` and `gh_order_events` (fast restore of the affected table).
- [ ] Code backup of the current LIVE release (current commit hash recorded) for code rollback.
- [ ] Verify each backup file is non-empty and readable before continuing.

## 3. Maintenance mode and deploy order

The new code applies the soft-delete scope to **every** `GhOrder` query (`WHERE gh_orders.deleted_at IS NULL`). Running the new code before the migration breaks all GH order pages with “unknown column”. The migration is additive, so the **old** code keeps working on the migrated schema.

1. [ ] `php artisan down --retry=60` (scheduled tasks, including `gh:promote-pickup-eligible`, do not run while down).
2. [ ] Upload code + `vendor/` per `docs/DEPLOYMENT.md` §1. No asset rebuild is required by this feature (Blade + existing Bootstrap modal only); upload `public/build` only if other commits in the release changed it.
3. [ ] `php artisan migrate --force` — **before** bringing the site up. Confirm output lists only the expected migration(s).
4. [ ] Clear/rebuild caches per site practice (`php artisan optimize:clear`, then `config:cache` / `route:cache` / `view:cache` if LIVE uses them).
5. [ ] Schema verification (§4) passes.
6. [ ] `php artisan up`.

If step 3 fails part-way: do **not** bring the site up; re-run `php artisan migrate --force` (each step is guarded and resumes). If it still fails, follow §5.

## 4. Schema verification (read-only, before `up`)

```sql
SELECT column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'gh_orders'
  AND column_name IN ('deleted_at','deleted_by','delete_reason');
-- expect: deleted_at timestamp YES NULL | deleted_by bigint(20) unsigned YES NULL | delete_reason varchar(500) YES NULL

SELECT constraint_name, referenced_table_name, delete_rule
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE() AND table_name = 'gh_orders'
  AND constraint_name = 'gh_orders_deleted_by_foreign';
-- expect: gh_orders_deleted_by_foreign | users | SET NULL

SELECT COUNT(*) FROM gh_orders WHERE deleted_at IS NOT NULL OR deleted_by IS NOT NULL OR delete_reason IS NOT NULL;
-- expect: 0 (no existing order becomes hidden)
```

- [ ] `php artisan migrate:status` shows `2026_11_18_160000` as **Ran**, nothing unexpected pending.
- [ ] Order count and status breakdown equal the §1 baseline.

## 5. Rollback options and limitations

| Option | When | Limitation |
|---|---|---|
| **Code rollback only** (keep columns) | Feature defect, schema fine | Old code ignores `deleted_at`: any order deleted after release **reappears** in boards, driver lists and the scheduler (a deleted `LATER` order would be auto-promoted). Before rolling back run `SELECT id, reference, status FROM gh_orders WHERE deleted_at IS NOT NULL` and get an owner decision (e.g. cancel those orders through the normal flow after rollback). |
| **Schema rollback** `php artisan migrate:rollback --step=1` | Only after code rollback, only if this migration is the **last** one applied | Drops the three columns and FK: who/when/why of deletions is lost from `gh_orders` (the `ORDER_DELETED` rows in `gh_order_events`, including the reason, remain). Never use plain `migrate:rollback` — it rolls back the whole batch, which may include other migrations from this release. |
| **Restore backup** | Last resort (corruption) | Loses every order, invoice, payment and event written since the backup. Requires owner approval (financial data). |

No rollback path modifies invoices, payments, VAT, credit notes, refunds or driver cash records — the feature never writes to them.

## 6. Post-deployment smoke tests

No destructive action on real orders. Deleting a real order needs an owner-designated disposable order.

- [ ] Manager login; GH order board loads in **Arabic (RTL)** and **English (LTR)**; tab counts match the §1 baseline.
- [ ] Open a `LATER` order with no invoice → **Delete order** button visible; open the modal → reason field and buttons fully visible on a phone and desktop. **Cancel** (do not submit).
- [ ] Open a `WORKING` order and an order with an invoice → no Delete button.
- [ ] Non-manager (driver / accountant) → no Delete button; driver workspace and pickup list load.
- [ ] Invoices, payments and driver cash custody pages load; re-run the §1 finance counts/sums → **identical**.
- [ ] Next cron cycle: `gh:promote-pickup-eligible` runs without error (check `storage/logs/laravel.log`).
- [ ] `storage/logs/laravel.log` has no new errors since `up`.
- [ ] Optional, owner-approved only: delete a designated disposable `LATER` order with a reason → redirect + success message; it disappears from lists; `/service/gh/orders/{id}` returns 404; one `ORDER_DELETED` event exists for it.

## 7. Evidence on TEST (2026-11-18)

- Full suite: 1549 passed, 1 skipped (pre-existing Gross MySQL concurrency test); Pint clean.
- Deletion coverage: `AdminOrderDeletionTest` (22), `AdminOrderDeletionConcurrencyTest` (parallel workers → exactly one deletion and one audit event), `OrderSoftDeleteMigrationSafetyTest` (5, including resume after partial failure).
- UI verified AR/RTL + EN/LTR at 375 / 768 / 1280 px on a local scratch database (TEST data untouched).

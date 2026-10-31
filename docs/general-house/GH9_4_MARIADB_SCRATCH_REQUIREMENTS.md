# GH-9.4 — MariaDB Scratch Database Requirements (DBA)

**Date:** 2026-10-31  
**STATUS:** Scratch DB **not available** → empty-schema MariaDB migrate remains **BLOCKED**

Do **not** grant elevated `CREATE DATABASE` to the long-lived application user for routine runtime.

---

## Current TEST identity

| Item | Value |
|---|---|
| Engine | MariaDB **11.4.13** |
| App database | `d14098_general` |
| App user | `d14098_general`@`localhost` |
| Grants | `ALL` on `d14098_general` only |
| Visible DBs | `d14098_general`, `information_schema` |

---

## Required scratch database

| Setting | Recommendation |
|---|---|
| Name | `d14098_gh_migrate_scratch` (or Hostinger-allowed equivalent) |
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Engine | MariaDB **11.4.x** (match intended LIVE) |
| Contents | **Empty** — no business data |
| Authorization | Written owner/DBA approval for wipe / `migrate:fresh` |

Optional second DB for additive upgrade: `d14098_gh_migrate_from_pre_gh` restored from a pre-GH dump.

---

## Minimum privileges (scratch only)

Grant to migrate runner (may be a dedicated `d14098_gh_migrate` user, or temporary grant on app user **limited to scratch schema**):

```sql
-- Example (run as privileged DBA / cPanel)
CREATE DATABASE IF NOT EXISTS d14098_gh_migrate_scratch
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON `d14098_gh_migrate_scratch`.* TO 'd14098_general'@'localhost';

FLUSH PRIVILEGES;
```

Do **not** grant `*.*` CREATE/DROP globally to the app user.

---

## Expected schema after fresh migrate

All Laravel migrations for the RC, including GH:

- `2026_10_25_200000_create_gh_catalog_tables`
- `2026_10_25_210000_create_gh_customers_zones_contracts_tables`
- `2026_10_25_220000_create_gh_orders_tables`
- `2026_10_31_110000_create_gh_logistics_tables`
- `2026_10_31_120000_repair_gh_utc_timestamps`
- `2026_10_31_140000_create_gh_invoice_tables`
- `2026_10_31_150000_create_gh_payment_tables`
- `2026_10_31_160000_add_gh_reporting_indexes`
- `2026_10_31_170000_gh92_zones_validation_only_launch`

Verify `gh_*` tables, FKs, unique indexes, `DECIMAL` money columns, timestamp columns, `gh_sequences`, reporting indexes.

---

## Migration commands (after scratch exists)

```bash
# From app root; use a disposable .env.scratch or env overrides — never point at LIVE
export DB_DATABASE=d14098_gh_migrate_scratch
php artisan migrate:fresh --force
php artisan migrate --force   # idempotency: should no-op
```

---

## Verification queries (examples)

```sql
SELECT VERSION();
SHOW TABLES LIKE 'gh_%';
SHOW CREATE TABLE gh_invoices\G
SHOW CREATE TABLE gh_payments\G
SHOW CREATE TABLE gh_zones\G
SHOW INDEX FROM gh_payments;
SELECT * FROM gh_sequences;
SELECT DEFAULT(min_order_policy) FROM gh_zones LIMIT 1;
```

Application smoke: boot against scratch; run `php artisan test --filter=GeneralHouse` with scratch DB if CI allows.

---

## Cleanup

```sql
DROP DATABASE IF EXISTS d14098_gh_migrate_scratch;
-- Optionally REVOKE scratch grants from d14098_general
```

---

## Until scratch is provided

Mark MariaDB empty migrate **BLOCKED**. Do not run `migrate:fresh` on `d14098_general`.

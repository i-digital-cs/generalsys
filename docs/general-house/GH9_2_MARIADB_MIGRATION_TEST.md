# GH-9.2 — MariaDB / MySQL Migration Test

**Date:** 2026-10-31  
**STATUS:** **BLOCKED** (cannot create disposable MariaDB database)

---

## Target engine

| Signal | Value |
|---|---|
| TEST / intended LIVE engine | **MariaDB 11.4.13** (`select version()`) |
| App DB user | `d14098_general` @ `127.0.0.1` |
| Accessible databases | `d14098_general`, `information_schema` only |

---

## Attempted disposable database

```text
CREATE DATABASE d14098_gh92_scratch ...
→ ERROR 1044 (42000): Access denied for user 'd14098_general'@'localhost'
  to database 'd14098_gh92_scratch'
```

No second MariaDB schema is available to this shared-hosting user.

**Did not** run `migrate:fresh` against shared TEST `d14098_general`.

---

## What was verified instead

| Check | Engine | Result |
|---|---|---|
| GH-9.1 fresh migrate (prior) | SQLite disposable | PASS (insufficient alone) |
| GH-9.2 forward migration on TEST | MariaDB 11.4 | PASS — `2026_10_31_170000_gh92_zones_validation_only_launch` Ran |
| Zone default ALTER | MariaDB | PASS (`DEFAULT 'VALIDATION_ONLY'`) |
| Shared TEST integrity after | MariaDB | PASS (zones=1, migration row present) |

---

## Why SQLite PASS is not enough

SQLite lacks MySQL ENUM/`MODIFY` semantics. GH-9.2 migration uses:

`ALTER TABLE gh_zones MODIFY min_order_policy ENUM(...) DEFAULT 'VALIDATION_ONLY'`

That path was verified **on TEST MariaDB** via the forward migration, but a **full empty-schema** MariaDB migrate of GH-1…GH-9.2 remains **BLOCKED** until a scratch database (or DBA grant) is provided.

---

## Unblock requirements (owner/ops)

1. Grant `CREATE` on a disposable DB (e.g. `d14098_gh92_scratch`), **or**  
2. Provide credentials to a scratch MariaDB instance matching LIVE version.  

Then re-run: create DB → `migrate:fresh` → FK/unique/decimal/sequence checks → drop DB.

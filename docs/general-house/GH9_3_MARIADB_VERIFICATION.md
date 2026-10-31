# GH-9.3 — MariaDB Migration Verification

**Date:** 2026-10-31  
**STATUS:** **BLOCKED**

---

## Gate 0 identity

| Signal | Value |
|---|---|
| App path | `/home/d14098/generalsys.idigital-cs.com` |
| TEST DB | `d14098_general` |
| Engine | **MariaDB 11.4.13** |
| App user | `d14098_general`@`localhost` |
| Grants | `ALL` on `d14098_general` only; **no** `CREATE DATABASE` |
| Visible DBs | `d14098_general`, `information_schema` |

---

## Disposable schema search (GH-9.3)

| Question | Answer |
|---|---|
| Isolated schema designated for destructive migrate tests? | **No** |
| Second writable database available? | **No** |
| Authorization to wipe any existing DB? | **No** — shared TEST holds business GH data |

**Did not** run `migrate:fresh` on shared TEST.

---

## Exact DBA / Hostinger action required

1. Create disposable schema (example: `d14098_gh_migrate_scratch`) via cPanel/DBA.  
2. Grant the app user privileges **only** on that scratch schema.  
3. Confirm in writing that wipe/`migrate:fresh` is allowed.  
4. Optional second scratch for additive upgrade from a pre-GH dump.  
5. Re-run GH-9.3 MariaDB empty + additive proofs; then drop scratch DBs.

Do **not** permanently elevate the production app user with global `CREATE`.

---

## What remains verified on MariaDB TEST (non-destructive)

| Check | Result |
|---|---|
| Forward GH migrations including `2026_10_31_170000_gh92_zones_validation_only_launch` | Ran previously |
| Decimal money columns / CONFIRMED payment math (bcmath) | PASS on live TEST rows |
| Invoice/payment sequences continuing (`GH-INV-000002`, `GH-PAY-000005`) | PASS |
| Empty-schema MariaDB migrate | **BLOCKED** |
| Additive migrate on disposable pre-GH copy | **BLOCKED** |

SQLite empty migrate is **not** accepted as MariaDB PASS.

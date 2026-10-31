# General House — Migration Integrity Audit (GH-4 Closure)

**Date:** 2026-10-31  
**Database:** `d14098_general` (TEST)  
**App path:** `/home/d14098/generalsys.idigital-cs.com`  
**Auditor:** automated closure pass (no further `migrations` table writes after this report)

## 1. Statement under investigation

> “Repaired missing GH-1/2/3 rows in migrations table.”

## 2. What was missing

At GH-4 deploy time (`2026_10_31_110000` applied), `migrate:status` showed:

| Migration file | Tables present? | Row in `migrations`? |
|---|---|---|
| `2026_10_25_200000_create_gh_catalog_tables` | Yes (`gh_service_categories`, …) | **No** |
| `2026_10_25_210000_create_gh_customers_zones_contracts_tables` | Yes (`gh_customers`, …) | **No** |
| `2026_10_25_220000_create_gh_orders_tables` | Yes (`gh_orders`, …) | **No** |
| `2026_10_31_110000_create_gh_logistics_tables` | Yes (after migrate) | **Yes** (id 69, batch 15) |

## 3. Why they were missing (evidence-based)

**Known facts**

1. GH-1/GH-2/GH-3 were applied earlier via `php artisan migrate --force --path=…` on this same TEST DB (deploy reports recorded Ran / batch numbers at the time).
2. On 2026-10-31, before repair, only the GH-4 logistics migration row existed among GH migrations, while all GH-1–3 tables still existed.
3. No migration `down()` was run for GH-1–3 (tables intact; FKs intact).

**Most probable cause (not fully proven)**

- The `migrations` bookkeeping rows for GH-1–3 were removed or lost (manual delete, partial restore of `migrations` only, or tooling) **after** the DDL had already been applied.
- Table existence alone does **not** prove the bookkeeping row survived; this audit treats that mismatch as a real integrity incident.

**Ruled out**

- Failed migrate leaving half-applied DDL without a row: tables are complete with expected indexes/FKs.
- Fresh install without migrate: catalog/customer/order objects would be missing.

## 4. Records inserted by the repair

Executed 2026-10-31 (idempotent insert only if table exists and row missing):

| id | migration | batch assigned | Prerequisite table checked |
|---|---|---|---|
| 70 | `2026_10_25_200000_create_gh_catalog_tables` | **14** | `gh_service_categories` |
| 71 | `2026_10_25_210000_create_gh_customers_zones_contracts_tables` | **14** | `gh_customers` |
| 72 | `2026_10_25_220000_create_gh_orders_tables` | **14** | `gh_orders` |

Notes:

- Batch `14` was chosen as `max(batch)-1` while GH-4 already occupied batch `15` (id 69).
- **Anomaly:** numeric `id` order is 69 (GH-4) then 70–72 (GH-1–3). Batch numbers therefore do not reflect historical apply order.
- **No approval sought to renumber batches** — left as-is pending owner decision. Further history edits are **blocked** until approved.

## 5. Schema comparison vs migration definitions

Method: `SHOW FULL COLUMNS` / `SHOW INDEX` / `information_schema` FKs vs migration PHP + `docs/DATABASE_SCHEMA.md` §§24–27.

### 5.1 GH-1 catalog — PASS

- Tables present; `uq_gh_offering_product_treatment (product_id, treatment_id)` unique.
- Status enums, DECIMAL prices, indexes match migration intent.

### 5.2 GH-2 customers/zones/contracts — PASS

- `uq_gh_address_default_customer (default_for_customer_id)` unique.
- `uq_gh_contract_reference`, `uq_gh_contract_offering (contract_id, offering_id)` unique.
- FKs RESTRICT as designed.

### 5.3 GH-3 orders — PASS (with intentional GH-4 overlay)

Compared to `2026_10_25_220000_create_gh_orders_tables.php`:

| Item | Migration (GH-3) | Live TEST | Verdict |
|---|---|---|---|
| Core columns / enums / indexes | As defined | Present | Match |
| `timezone` default | `Africa/Cairo` | **`Asia/Dubai`** | **Intentional** via GH-4 `ALTER` (new inserts only; 0 historical rows) |
| Extra logistics columns | N/A | `pickup_confirmed_by`, `delivered_*`, `cancelled_*`, … | **Intentional** GH-4 additive |
| `gh_sequences.orders` seed | Inserted at GH-3 | Present | Match |
| `uq_gh_order_reference` | Unique | Unique | Match |

### 5.4 GH-4 logistics — PASS

- `gh_order_received_lines`, `gh_order_attempts` match migration.
- FK cascade/restrict behavior matches migration.

## 6. Discrepancies summary

| ID | Severity | Description | Action |
|---|---|---|---|
| D1 | Medium (bookkeeping) | GH-1/2/3 `migrations` rows were missing then re-inserted | Documented; no further inserts |
| D2 | Low (cosmetic) | Repaired rows batch=14 / ids after GH-4 batch=15 | Leave unless owner approves renumber |
| D3 | None (intended) | `gh_orders.timezone` default Asia/Dubai vs GH-3 Cairo | Documented in TIMEZONE.md |

## 7. Recommendations (require owner approval)

1. Optionally insert a **forward** bookkeeping-only note migration documenting the repair (no DDL).
2. Optionally correct batch numbers in a controlled, reviewed SQL change — **do not** re-run GH-1–3 `up()`.
3. Protect TEST: avoid restoring `migrations` alone from older dumps.

## 8. Conclusion

Physical GH schema on TEST matches GH-1–4 migration definitions (with documented GH-4 timezone default change).  
Bookkeeping was repaired once; **no additional migration-history modification performed in this closure pass.**

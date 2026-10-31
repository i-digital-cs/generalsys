# GH-9.1 — Fresh Migration Verification

**Date:** 2026-10-31  
**Method:** Disposable sqlite database — **not** shared TEST MySQL  
**Codebase:** Isolated RC worktree `/tmp/gh91-rc` (`release/general-house-gh1-gh8`)  
**Command:** `DB_CONNECTION=sqlite DB_DATABASE=/tmp/gh91_fresh.sqlite php artisan migrate:fresh --force`

---

## 1. Why not TEST bookkeeping

TEST DB `d14098_general` has the known GH-1–3 batch anomaly (batch 14 ids after GH-4 batch 15).  
Fresh verification **must not** copy TEST `migrations` rows.  
This run starts empty → Laravel records migrations in natural order.

---

## 2. GH migrations applied (fresh)

| Order | Migration | Batch (fresh sqlite) |
|---|---|---|
| 1 | `2026_10_25_200000_create_gh_catalog_tables` | 1 |
| 2 | `2026_10_25_210000_create_gh_customers_zones_contracts_tables` | 1 |
| 3 | `2026_10_25_220000_create_gh_orders_tables` | 1 |
| 4 | `2026_10_31_110000_create_gh_logistics_tables` | 1 |
| 5 | `2026_10_31_120000_repair_gh_utc_timestamps` | 1 |
| 6 | `2026_10_31_140000_create_gh_invoice_tables` | 1 |
| 7 | `2026_10_31_150000_create_gh_payment_tables` | 1 |
| 8 | `2026_10_31_160000_add_gh_reporting_indexes` | 1 |

**Contrast with TEST anomaly:** fresh install has monotonic migration ids and a single coherent batch — **no** GH-1–3 repair needed.

---

## 3. Schema checks

| Check | Result |
|---|---|
| `gh_orders` columns present | 30 cols |
| `gh_invoices` | 43 cols |
| `gh_payments` | 20 cols |
| `gh_sequences` | present (order/invoice/payment sequences) |
| `gh_zones` | present |
| `gh_orders.timezone` default | `Asia/Dubai` |
| FKs / uniques | Created by migrations (sqlite enforces when enabled; MySQL LIVE will use InnoDB FKs as defined) |
| UTC repair migration | Ran no-op/safe on empty tables |

---

## 4. Forward-only policy (unchanged)

- LIVE: `php artisan migrate --force` only  
- Do **not** `migrate:rollback` GH batches for recovery  
- Application rollback ≠ database rollback  

---

## 5. Shared TEST

No destructive migrate against `d14098_general` in this phase.

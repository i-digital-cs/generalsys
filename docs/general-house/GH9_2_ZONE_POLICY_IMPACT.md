# GH-9.2 — Zone Policy Impact (Dry-Run + Applied)

**Database:** `d14098_general` (TEST)  
**Backup before change:** `storage/backups/d14098_general_pre_gh92_zones_20261031_113553.sql`

---

## Pre-change inventory

| Object | Count / value |
|---|---|
| Zones | **1** — `GH4-TEST Dubai Zone` (`id=1`) |
| Zone policy before | `SURCHARGE`, amount `25.00` |
| Orders | 12 |
| Invoices | 1 ISSUED (`min_order_adjustment_amount=0.00`) |
| Drafts with surcharge > 0 | **0** |
| Issued with surcharge > 0 | **0** |
| Payments | 2 CASH CONFIRMED |
| Schema default before | `ENUM` default `SURCHARGE` |

---

## Zones requiring update

| Zone ID | Name | Action |
|---|---|---|
| 1 | GH4-TEST Dubai Zone | `SURCHARGE` → `VALIDATION_ONLY` |

No other zones.

---

## Migration applied

| Migration | Effect |
|---|---|
| `2026_10_31_170000_gh92_zones_validation_only_launch` | Update rows `SURCHARGE`→`VALIDATION_ONLY`; ALTER default to `VALIDATION_ONLY` |

**Not changed:** issued invoice snapshots, payments, orders, contracts, customer prices.

**Draft review:** none invalid under new policy (no draft with surcharge amount).

---

## Post-change verification

| Check | Result |
|---|---|
| Zone 1 policy | `VALIDATION_ONLY` |
| Column default | `VALIDATION_ONLY` |
| Issued invoice totals | Unchanged |

---

## New zones

Factory + `GhMinOrderPolicy::DEFAULT` + config `min_order.default_policy` → `VALIDATION_ONLY`.

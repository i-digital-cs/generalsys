# RISK-GROSS-001 — Root cause & corrective design

**Date:** 2026-10-13  
**Status:** **IMPLEMENTED** (Task 3 conservation + Task 3.1 lock hardening).  
**Details:** `RISK_GROSS_001_IMPLEMENTATION_REPORT.md`.

---

## 1. Root cause (CONFIRMED)

**Method:** `StockService::repairMoveOnHandToWithCourier`  
**Caller:** Only `RepairGrossReturnedWithCourierCommand`.

**Legacy defect:** unconditional `WITH_COURIER += P` when `ON_HAND < P` → phantom stock; no movement row.

---

## 2. Inventory accounting (approved)

| Movement | Source → Dest | Balance Δ | Ledger |
|---|---|---|---|
| `applySaleOut` (hand to courier) | ON_HAND → WITH_COURIER | − / + same | `SALE_OUT` |
| **Repair (fixed)** | ON_HAND → WITH_COURIER | − / + or reject | `SALE_OUT` + note `repair-returned-with-courier` |
| Client return / refuse | — | none | `SALE_RETURN_CLIENT` |
| Receive / loss / deliver | as SSOT | conserved | existing types |

---

## 3. Owner decisions (locked)

1. Insufficient ON_HAND: skip + warn.  
2. Success: write `SALE_OUT` via conserved transfer.  
3. Historical scan: TEST only (plan ready; not executed on production host).  
4. Historical correction: not authorized.  
5. Task 3.1: serialize with existing row locks; **no** schema without separate approval.

---

## 4. Concurrency design (Task 3.1)

**Problem:** `lockForUpdate` on a non-existent movement row locks nothing → two first writers can both insert when `ON_HAND >= 2P`.

**Solution:** lock order:

1. `gross_shipment_allocations.id` (production ref)  
2. `gross_stock_balances.item_id`  
3. Recheck repair `SALE_OUT`  
4. Transfer + insert  

**Guarantee:** allocation-row-lock exact-once for production path.  
**Not claimed:** uniqueness-constraint EXACTLY-ONCE (index not added).  
**MySQL fork proof:** not available without isolated `MYSQL_TEST_DATABASE`.

Optional unique index: documented in implementation report — **do not migrate without approval**.

---

## 5. Tests

| File | Role |
|---|---|
| `RiskGross001RepairClampTest` | Conservation, ledger, rollback, overdraw |
| `GrossStockIntegrityTest` | Insufficient/zero/sufficient + handover |
| `RepairGrossReturnedWithCourierCommandTest` | Dry-run / `--apply` / skip exit |
| `RiskGross001ConcurrencyTest` | Post-lock recheck, retry, lock order; MySQL parallel skipped if no scratch DB |

---

## 6. Status

**Code correction:** DONE.  
**First-write race (allocation path):** ELIMINATED via row locks + recheck.  
**Historical impact:** UNKNOWN until owner-approved TEST scan.  
**Production deploy:** not part of this task.

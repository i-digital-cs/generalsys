# Task 2 Phase 5 — Exit report (Gross, inventory & invoice architecture)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 5 only.  
**Phase 6:** Not started (requires explicit owner approval).

---

## 1. Stock write-path matrix (canonical)

| Trigger | Orchestrator | StockService | Balance effect | Notes |
|---|---|---|---|---|
| Manual adjust IN/OUT | `StockController` | `adjustIn` / `adjustOut` | ON_HAND ± | PIECE/CARTON via `UnitConversion` + item `pieces_per_carton` |
| Purchase confirm | `PurchaseInvoiceService` | `applyPurchaseIn` | ON_HAND + | Idempotent confirm |
| Purchase admin update/delete | same | `reversePurchaseIn` / re-apply | − then + | Locked invoice |
| Purchase return | `PurchaseReturnService` | `applyPurchaseReturn` | ON_HAND − | |
| Sales **confirm** | `SalesInvoiceFulfillmentService` | **none** | — | PREPARING only |
| Hand to courier | `SalesInvoiceFulfillmentService` | `applySaleOut` | ON_HAND −, WITH_COURIER + | Stock leaves warehouse here |
| Client return / refuse | `AllocationService` / sales refuse | `recordClientReturn` | **none** | Audit only |
| Receive returned | `AllocationService` | `applyReturn` | WITH_COURIER −, ON_HAND + | |
| Loss | `AllocationService` | `applyLoss` | WITH_COURIER −, LOST + | |
| Deliver | `markDelivered` | `applyDelivery` | WITH_COURIER − | COD NONE\|COLLECTED |
| Admin sales unwind | `SalesInvoiceService` | reverse/restore | restore ON_HAND | |
| Repair returned-with-courier | Artisan command | **`repairMoveOnHandToWithCourier`** | clamp ON_HAND→WITH_COURIER | No movement row (legacy) |

**Competing writers eliminated for runtime + this repair command.** `GrossDataResetService` truncate remains ops wipe (not a ledger API).

---

## 2. Unit conversion ownership

| Rule | Owner |
|---|---|
| PIECE = base stock unit (integer pieces) | `UnitConversion` + `gross_stock_balances` |
| CARTON factor | `UnitConversion::factorFor(unit, piecesPerCarton)` |
| Catalog item `pieces_per_carton` | Forced to **1** on create (`ItemService` / `ItemConversionImmutabilityTest`) |
| Transaction carton size | Line `conversion_factor_snapshot` at sync / SALE_OUT args — **not** a global product carton |
| Invalid factor / qty / unit | `GrossException` via `UnitConversion` |

Do not invent a new catalog carton size model without owner approval.

---

## 3. Invoice lifecycle map

### Sales

```text
DRAFT → confirm → CONFIRMED+PREPARING → packing → READY_WAREHOUSE
      → handToCourier → WITH_COURIER (+ SALE_OUT + COD flags)
      → deliver / refuse / cancel (rules unchanged)
```

Currency immutable after leave DRAFT (Phase 1 preserved).

### Purchase

```text
DRAFT → confirm → CONFIRMED (PURCHASE_IN)
DRAFT → cancel → CANCELLED
CONFIRMED → cancel forbidden; admin update/delete + returns only
```

Currency immutable after confirm (characterized in Phase 5).

---

## 4. Before / after architecture

| Area | Before | After |
|---|---|---|
| Fulfillment | Inside `SalesInvoiceService` (~987 LOC god) | **`SalesInvoiceFulfillmentService`** (215) + façade delegates |
| COD collection | Same god service | **`SalesInvoiceCodCollectionService`** (149) + façade |
| `SalesInvoiceService` | 987 LOC | **746 LOC** orchestration / refuse / unwind / CRUD |
| Repair returned-with-courier | Direct `GrossStockBalance` mutate | **`StockService::repairMoveOnHandToWithCourier`** (same clamp, no movement) |
| Controller | Already thin HTTP → service | Unchanged public API (façade) |

Public methods on `SalesInvoiceService` preserved for controllers/tests.

---

## 5. Tests added

| File | Cases |
|---|---|
| `tests/Feature/Gross/GrossStockIntegrityTest.php` | Catalog piece-per-carton=1; line-factor SALE_OUT; zero carton factor reject; purchase currency lock; repair clamp ± movement count; duplicate confirm/handToCourier idempotent |

Existing Gross suite **217 PASS**. Full app **947 PASS** (940 + 7).

---

## 6. Concurrency & idempotency findings

| Scenario | Behavior |
|---|---|
| Duplicate sales confirm | No-op if already CONFIRMED/DELIVERED |
| Duplicate handToCourier | No-op if already WITH_COURIER |
| Duplicate purchase confirm | No second PURCHASE_IN |
| Duplicate COD collect | No-op if COLLECTED |
| Concurrent oversell | `lockForUpdate` on balance + insufficient stock |
| Repair clamp when on_hand < pending | ON_HAND unchanged; WITH_COURIER still +pending (legacy; documented) |

---

## 7. Authorization findings

Existing Gross policies + page ACL (Phase 1) preserved. No permission broadening. Sales/accountant/manager mutate rules unchanged (admin mutate tests green).

---

## 8. Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

No historical invoice correction; no automatic stock reconciliation in migrate.

---

## 9. Remaining risks

| Risk | Notes |
|---|---|
| Repair clamp can inflate WITH_COURIER without ON_HAND debit | Preserved on purpose; changing it needs owner decision + movements |
| `applyRefuseReturn` StockService method unused | Dead API; refuse uses receive path — do not wire without product change |
| `SalesInvoiceController` still large (HTTP surface) | Further thinning optional; not required for stock SSOT |
| Catalog `pieces_per_carton=1` vs CARTON unit on forms | Line snapshot is SSOT for sales stock; document drift in GROSS_MODULE §6 (refuse restock) still stale — prefer code/tests |
| Nested DB transactions (outer invoice + StockService) | Pre-existing; left intact |

---

## 10. Files changed

| Path | Change |
|---|---|
| `app/Services/Gross/SalesInvoiceFulfillmentService.php` | **New** |
| `app/Services/Gross/SalesInvoiceCodCollectionService.php` | **New** |
| `app/Services/Gross/SalesInvoiceService.php` | Façade + remaining lifecycle |
| `app/Services/Gross/StockService.php` | `repairMoveOnHandToWithCourier` |
| `app/Console/Commands/RepairGrossReturnedWithCourierCommand.php` | Uses StockService |
| `tests/Feature/Gross/GrossStockIntegrityTest.php` | **New** |
| `docs/refactoring/PHASE_5_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` / `REFACTORING_MASTER_PLAN.md` / duplication matrix | Progress |

---

## 11. Regression results

| Suite | Result |
|---|---|
| Gross filter | **217 PASS** |
| Protected attendance/payroll/HR | **110 PASS** |
| Full `php artisan test` | **947 PASS / 0 FAIL** |
| Pint `--dirty` | PASS |
| `php -l` new files | PASS |

---

## 12. Verdict

Phase 5 **PASS**. Ready for Phase 6 only after **explicit owner approval**.

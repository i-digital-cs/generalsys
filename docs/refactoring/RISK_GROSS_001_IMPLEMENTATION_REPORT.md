# RISK-GROSS-001 — Implementation Report

**Date:** 2026-10-13  
**Tasks:** Task 3 (conservation fix) + Task 3.1 (concurrency / idempotency hardening)  
**Authorization:** Owner-approved Option A + SALE_OUT ledger + dry-run default + allocation/balance lock serialization; TEST-only historical scan design; **no** historical mutation / **no** schema / **no** `--apply` on shared data / **no** APP_ENV change.

---

## 1. Root cause

`StockService::repairMoveOnHandToWithCourier` previously:

```text
if ON_HAND >= P: ON_HAND -= P
WITH_COURIER += P   // unconditional
no gross_stock_movements row
```

When `ON_HAND < P`, total tracked pieces increased (phantom WITH_COURIER). Sole runtime caller: `gross:repair-returned-with-courier`.

---

## 2. Approved owner decisions (applied)

| # | Decision | Implementation |
|---|---|---|
| 1 | Insufficient ON_HAND → skip row, warn, continue | Command pre-check + catch `GrossException::insufficientStock()` |
| 2 | Successful repair → ledger via canonical mechanism | Inline conserved transfer + `TYPE_SALE_OUT` + note `repair-returned-with-courier` |
| 3 | Historical inspection → TEST only | Scan **designed**, not executed (`APP_ENV=production` host) |
| 4 | Historical correction → **not** authorized | No repair `--apply` on shared data; no backfill/migration |
| 5 | Task 3.1 concurrency | Lock existing allocation + balance rows; recheck after locks; no schema |

---

## 3. First-write race (Task 3.1)

### 3.1 Confirmed interleaving (pre-hardening)

Empty `SELECT … FOR UPDATE` on `gross_stock_movements` locks **no row** when the repair movement does not yet exist. With `ON_HAND >= 2P`:

```text
T1 BEGIN
T2 BEGIN
T1 SELECT repair SALE_OUT FOR UPDATE → empty (locks nothing)
T2 SELECT repair SALE_OUT FOR UPDATE → empty (locks nothing)
T1 lock balance; ON_HAND-=P; WITH_COURIER+=P; INSERT SALE_OUT; COMMIT
T2 lock balance; ON_HAND-=P; WITH_COURIER+=P; INSERT SALE_OUT #2; COMMIT
→ two movements, double transfer
```

When `ON_HAND == P`, the second writer fails with insufficient stock (no duplicate), but does not return the first movement as an idempotent hit.

### 3.2 Mitigation (no schema)

Production repair refs are always `GrossShipmentAllocation` + id (existing authoritative row).

**Lock order (must stay consistent):**

1. `gross_shipment_allocations` WHERE `id = ref_id` → `lockForUpdate()`
2. `gross_stock_balances` WHERE `item_id = …` → `lockForUpdate()` (via `ensureBalanceRow` then lock)
3. Recheck existing repair `SALE_OUT` (`type` + `ref_type` + `ref_id` + note)
4. If none: require `ON_HAND >= P`; debit/credit; insert `SALE_OUT`

Command apply path locks the allocation first, rechecks eligibility under that lock, then calls the service (which re-locks allocation in the same transaction), then updates allocation counters.

**Guarantee claimed:** For allocation-backed repairs, concurrent first writers for the **same allocation id** are serialized on the allocation primary key; the loser rechecks and either returns the existing movement (service) or skips as `no_longer_eligible` (command). This is **row-lock exact-once for the production path**, not a uniqueness-constraint exact-once.

**Not claimed:** DB unique-index exact-once; MySQL parallel fork proof on this host.

### 3.3 Optional unique index (NOT implemented — needs separate approval)

```sql
-- Defense in depth only; check for pre-existing duplicates first.
ALTER TABLE gross_stock_movements
  ADD UNIQUE INDEX uq_gross_repair_sale_out
    (type, ref_type, ref_id, notes(64));
-- Or a generated/stored “repair key” column if prefix unique on notes is undesirable.
```

**Compatibility checks before any migration:**

1. `SELECT type, ref_type, ref_id, notes, COUNT(*) … HAVING COUNT(*) > 1` for repair notes.
2. Confirm nullable `ref_*` / free-text `notes` behavior on MySQL/MariaDB.
3. Ensure normal hand-to-courier `SALE_OUT` rows (null/other notes) are unaffected.
4. Rollback: drop index only (no data rewrite).

---

## 4. Exact code changes

### `StockService::repairMoveOnHandToWithCourier`

- Validates `$pieces >= 1`.
- Lock allocation (when `refType === GrossShipmentAllocation::class`) → lock balance → recheck repair movement → conserved transfer + `SALE_OUT`.
- Constant: `StockService::REPAIR_RETURNED_WITH_COURIER_NOTE`.
- Does **not** call `applySaleOut` for repair (avoids nested “check then write” without recheck); shares the same conservation math and movement type.

### `RepairGrossReturnedWithCourierCommand`

- Dry-run default; `--apply` required; `--force` skips confirm.
- Apply: lock allocation → `stillEligible()` → repair service → update counters → `assertInvariant()`.
- Counters + non-zero exit on skip/fail preserved.

**No schema change. No new movement type.**

---

## 5. Stock conservation proof

```text
ON_HAND_after      = ON_HAND_before - P
WITH_COURIER_after = WITH_COURIER_before + P
Δ ON_HAND + Δ WITH_COURIER = 0
```

Insufficient / zero ON_HAND / failed ledger write: no committed balance or movement changes.

---

## 6. Ledger behavior

| Event | Movement type | Notes | Ref |
|---|---|---|---|
| Successful repair | `SALE_OUT` | `repair-returned-with-courier` | allocation class + id |
| Insufficient / invalid | none | — | — |
| Duplicate same allocation ref | returns existing row | no second row | — |

---

## 7. Dry-run and apply semantics

```text
php artisan gross:repair-returned-with-courier          # dry-run (default)
php artisan gross:repair-returned-with-courier --apply  # mutate (confirm unless --force)
```

Task 3 / 3.1 **did not** run `--apply` against existing/shared data.

---

## 8. Idempotency & concurrency guarantees

| Layer | Guarantee |
|---|---|
| Command eligibility | Post-apply selectors clear; concurrent loser → `no_longer_eligible` skip |
| Service same allocation ref | After allocation+balance locks, existing repair `SALE_OUT` returned |
| Concurrent same allocation first write | Serialized on allocation PK + balance; **one** transfer |
| Concurrent overdraw | Balance `lockForUpdate` + sufficiency check |
| Unique DB constraint | **Not present** (schema not approved) |
| MySQL parallel fork verification | **NOT AVAILABLE** on this host (no isolated `MYSQL_TEST_DATABASE`; CREATE DATABASE denied; production DB forbidden) |

**Do not claim uniqueness-constraint EXACTLY-ONCE.** Claim: **allocation-row-lock exact-once** for the production repair path.

---

## 9. Historical read-only scan plan (READY — not executed)

### 9.1 Safety

- Run only where `APP_ENV` ∈ {`testing`,`local`} **or** owner-named TEST DB credentials.
- Refuse to run if `APP_ENV=production` (do not change APP_ENV to bypass).
- Mechanism: Artisan command e.g. `gross:audit-risk-gross-001` with **no** `--apply`, using `DB::select` / query builder **reads only**; no `update`/`insert`/`delete`; no model `save()`.
- Prove no writes: code review + optional SQL `GENERAL_LOG` / read-only DB user.

### 9.2 Tables / joins

- `gross_stock_balances` (on_hand, with_courier, lost)
- `gross_stock_movements` (types, qty_pieces, notes, ref_*)
- `gross_shipment_allocations` + `gross_sales_invoice_lines` + `gross_sales_invoices`

### 9.3 Queries (illustrative)

1. **Repair-note ledger presence** (post-fix only; legacy repairs wrote no movement):

```sql
SELECT id, item_id, qty_pieces, ref_type, ref_id, created_at
FROM gross_stock_movements
WHERE type = 'SALE_OUT' AND notes = 'repair-returned-with-courier';
```

2. **Balance vs movement reconstruction (heuristic, not proof of corruption):**

```sql
-- Expected WITH_COURIER ≈ SALE_OUT − SALE_DELIVERED − SALE_RETURN − REFUSE_RETURN − LOSS_CONFIRM
-- (per item). Differences need manual classification.
```

3. **Allocation under-courier vs balance** (eligibility-shaped, read-only):

```sql
SELECT a.id AS allocation_id, a.qty_returned_pieces, a.qty_returned_received_pieces,
       a.qty_with_courier_pieces, b.qty_on_hand_pieces, b.qty_with_courier_pieces AS bal_courier
FROM gross_shipment_allocations a
JOIN gross_sales_invoice_lines l ON l.id = a.sales_invoice_line_id
JOIN gross_stock_balances b ON b.item_id = l.item_id
WHERE a.qty_returned_pieces > 0;
```

### 9.4 Distinguishing real vs phantom

| Signal | Meaning |
|---|---|
| Balance WITH_COURIER ≫ sum of open allocation with_courier | Suspect inflation **or** multi-invoice SKU aggregation — investigate, do not auto-fix |
| Movement reconstruction gap without repair-note rows | Likely **pre-fix** silent clamp (no ledger) — cannot prove piece-level phantom from ledger alone |
| Repair-note SALE_OUT exists | Post-fix conserved repair; treat as legitimate transfer |
| Normal SALE_OUT without repair note | Hand-to-courier — legitimate |

**Do not assume every ledger/balance difference is corruption.**

### 9.5 Owner execution plan

1. Provision / point at TEST clone of Gross tables.  
2. Deploy audit command (read-only).  
3. Run; export CSV of allocation_id / item_id / heuristic flags only (no PII/money).  
4. Owner reviews; separate approval for any correction.

**Executed in Task 3.1:** NO.

---

## 10. Test results

```text
php artisan test --filter='RiskGross001ConcurrencyTest|RiskGross001RepairClampTest|GrossStockIntegrityTest|RepairGrossReturnedWithCourierCommandTest'
→ 31 PASS, 1 SKIPPED (mysql parallel — MYSQL_TEST_DATABASE unavailable)
```

Full regression (2026-10-13 Task 3.1):

```text
php artisan test → 975 PASS / 1 SKIPPED / 0 FAIL
vendor/bin/pint --dirty → passed
```

---

## 11. Remaining risks

1. Pre-fix phantom WITH_COURIER (if any) still unhealed — historical correction unauthorized.  
2. MySQL parallel fork not verified on this host (isolated DB unavailable).  
3. No unique index — defense in depth pending separate approval.  
4. Non-allocation synthetic refs (tests only) serialize on balance alone for same item.  
5. Production deploy still out of scope unless owner authorizes.

---

## 12. Files touched (Task 3 + 3.1)

- `app/Services/Gross/StockService.php`
- `app/Console/Commands/RepairGrossReturnedWithCourierCommand.php`
- `tests/Feature/Gross/RiskGross001RepairClampTest.php`
- `tests/Feature/Gross/GrossStockIntegrityTest.php`
- `tests/Feature/Gross/RepairGrossReturnedWithCourierCommandTest.php`
- `tests/Feature/Gross/RiskGross001ConcurrencyTest.php` (Task 3.1)
- `docs/refactoring/RISK_GROSS_001_CORRECTIVE_DESIGN.md`
- `docs/refactoring/RISK_GROSS_001_IMPLEMENTATION_REPORT.md`
- `docs/GROSS_MODULE.md`
- `docs/TASKS.md`

# GH — Invoice Corrections & Driver Cash Collection / Settlement

**Environment:** TEST only (`/home/d14098/generalsys.idigital-cs.com`)  
**Date:** 2026-11-15  
**LIVE / commit / push / production data:** not performed

---

## Verdicts

| Workstream | Status |
|------------|--------|
| **Part A — Invoice corrections after COMPLETED** | **CONDITIONAL** |
| **Parts B–D — Driver cash collection, custody, settlement** | **PASS** |
| **Part E — Financial integration safeguards** | **PASS** |
| **Part F — Tests** | **PASS** (`GeneralHouse` **332 passed / 1906 assertions**) |

---

## Existing architecture findings

| Area | Finding |
|------|---------|
| Issued invoices | Immutable (`GhInvoiceService::updateDraft` + policy `isDraft()`). COMPLETED does **not** block draft create/edit/issue. |
| Decreases after issue | GH-10 credit notes (qty-share + VAT share); refunds for cash return. |
| Increases after issue | **No** debit note / void-reissue / supplemental invoice. |
| Driver delivery CASH (before) | Auto-applied **full** `remaining_due`; no amount field; no custody. |
| Custody / settlement | Explicitly deferred in GH6 gate — **missing** until this change. |
| Balance SSOT | `GhPaymentBalanceService` (`G−C` vs confirmed payments − refunds − executed FC). |

---

## Part A — Invoice corrections (CONDITIONAL)

### Implemented (safe, existing architecture)

1. **COMPLETED + no invoice / DRAFT** — create/edit/issue already allowed; order show now offers create-draft whenever pickup exists and no issued stamp.
2. **COMPLETED + ISSUED (decreases)** — invoice show surfaces a **correction panel** linking to credit-note create; copy states issued immutability + VAT share rules.
3. **Manager authorization** — unchanged policy gates (`GhInvoicePolicy`, `GhCreditNotePolicy`).
4. **Concurrency** — existing draft `draft_version` + CN/payment `lockForUpdate` / idempotency keys retained.

### Blocked — exact proposal (owner approval required)

**Gap:** Owner wants add/remove items and change prices/totals on **issued** invoices (including increases). That requires **new audited documents**, not silent rewrite.

| Option | Schema / services | Balance impact | Stop reason |
|--------|-------------------|----------------|-------------|
| **A. Void + reissue** | Add `VOID` status; `gh_invoice_corrections` linking original→replacement; relax `UNIQUE(order_id)` to one active DRAFT/ISSUED | Exclude VOID from `G`; payments stay on original or migrate via explicit transfer rows | Owner must pick VAT/period reporting rules |
| **B. Debit / supplemental note** | `gh_debit_notes` (+ lines/events); SSOT adds debit into net due | Payments apply to combined net | New document type + UI + KPIs |
| **C. External journal only** | No app mutation | Ops SOP outside GH | Product still cannot “correct” in-app |

**Do not implement A/B until owner selects one.** No fictitious postings invented in this change.

**Files (Part A):**  
`resources/views/gh/invoices/partials/correction-panel.blade.php`, `resources/views/gh/invoices/show.blade.php`, `resources/views/gh/orders/show.blade.php`, `lang/{en,ar}/gh.php` (`invoice_corrections.*`), `tests/Feature/GeneralHouse/InvoiceShowUiTest.php`.

---

## Parts B–D — Driver cash (PASS)

### Behavior

1. **CASH at delivery** asks: «كم المبلغ الذي استلمته نقدًا؟» (`gh.driver.cash_collected_prompt`) with AED numeric field.
2. Amount **> 0** required; **partial** allowed when ≤ invoice remaining due.
3. Invoice balance shown only when an issued unpaid invoice exists — **never fabricated**.
4. Records **actual** collected amount on:
   - `gh_payments` (customer AR) when issued unpaid invoice exists — amount = cash collected (≤ remaining).
   - `gh_driver_cash_collections` (custody) always for CASH — full cash amount.
5. Audit: driver, order, timestamp, method, collection reference (`GH-DCC-######`), optional payment link.
6. Delivery confirm **idempotent**; collection key `gh-dcc-order-{id}`; payment key `gh-driver-delivery-pay-{invoice_id}`.
7. Invoice marked PAID only when balance SSOT reaches zero — partial cash leaves PARTIAL.
8. **ONLINE** — existing operator-recorded payment of remaining due; **no** custody.
9. **No invoice + CASH** — custody only; no fictitious invoice payment. Later apply-to-invoice needs explicit reconciliation (owner decision — not invented here).

### Custody ledger

| Table | Role |
|-------|------|
| `gh_driver_cash_collections` | +custody |
| `gh_driver_cash_settlements` | −custody (manager handoff) |
| `gh_driver_cash_balances` | Materialized outstanding with `lockForUpdate` |
| `gh_driver_cash_events` | Audit |

Settlements are **not** revenue and do **not** create `gh_payments`.

### Manager settlement

- Routes: `gh.driver-cash.index`, `gh.driver-cash.show`, `gh.driver-cash.settle`
- Amount ≤ outstanding; atomic + row lock; idempotency key; manager identity; optional receipt/notes
- Driver **cannot** view/settle (403); self-settle blocked in service
- Over-settlement rejected; duplicate key idempotent

### Example

```text
Invoice remaining 100.00 AED
Driver CASH collects 40.00
  → Payment CONFIRMED 40.00 (PARTIAL)
  → Custody +40.00 (outstanding 40.00)
Manager settles 40.00
  → Settlement GH-DCS-… ; outstanding 0.00
  → Still no second customer payment / no revenue KPI bump
```

---

## Part E — Integration preserved

- Issued invoice immutability + VAT snapshots
- Partial payment rules / overpayment blocked
- No-invoice operational delivery
- COMPLETED reopen/recomplete: no new collection when `delivered_at` set
- Role gates: managers for custody; drivers for delivery collect only
- Dashboard cash KPIs still from **confirmed invoice payments**, not settlements

---

## Files / migrations

**Migration:** `database/migrations/2026_11_15_180000_create_gh_driver_cash_custody_tables.php` (applied on TEST)

**Services / models:**  
`GhDriverCashCustodyService`, `GhDriverCashNumberService`, `GhDriverDeliveryPaymentService` (rewritten),  
`GhDriverCashCollection|Settlement|Balance|Event`

**HTTP:** `ConfirmGhDeliveryRequest`, `SettleGhDriverCashRequest`, `DriverCashCustodyController`, routes, `GhServiceMenu`, `config/module_menu.php`

**UI:** `gh/driver/delivery.blade.php`, `gh/driver-cash/*`, invoice correction panel

**Docs:** `docs/DATABASE_SCHEMA.md` (custody section), this report

---

## Tests & results

```text
php artisan migrate --force
  → 2026_11_15_180000_create_gh_driver_cash_custody_tables DONE

php artisan test --filter='DriverDeliveryPaymentTest|DriverCashCustodyTest'
  → 13 passed

php artisan test --filter=GeneralHouse
  → Tests: 332 passed (1906 assertions)
```

Covered: cash amount prompt path, partial cash, ONLINE no custody, no-invoice cash, duplicate delivery, outstanding, partial/full settlement, over-settlement, driver forbidden, reopen/recomplete no double collection, invoice correction UI copy.

---

## Remaining owner decisions

1. **Issued upward correction path:** void-reissue vs debit note vs external journal (Part A blocked).
2. **Unlinked custody → later invoice:** explicit reconciliation document/service (not built).
3. Enable **payment_correction_execute** for wrong CONFIRMED payments (separate GH-10 gate).
4. LIVE deploy of custody migration + UI (not done).

---

## End-of-step

```text
Task ID: GH-driver-cash-custody-and-invoice-corrections
Invoice corrections: CONDITIONAL (decreases+drafts PASS; issued increases BLOCKED pending owner)
Driver cash settlement: PASS
Commands: migrate; DriverDeliveryPaymentTest; DriverCashCustodyTest; GeneralHouse
Automated tests: PASS — 332 passed (1906 assertions)
UI/UX: PASS (AR+EN keys; delivery cash field; custody screens; correction panel)
DATABASE CHANGES: YES
SCHEMA MIGRATIONS CREATED: YES
DATA MIGRATION OR BACKFILL: NO
PRODUCTION DEPLOYMENT PROCEDURE: BLOCKED (TEST only)
```

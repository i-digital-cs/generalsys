# GH-9.3 — Financial Reconciliation (TEST)

**Date:** 2026-10-31  
**Arithmetic:** PHP `bcmath` / string decimals (not float)

---

## Issued invoices

| Invoice | Policy snapshot | Total payable | Confirmed | Pending | Remaining | Payment status |
|---|---|---|---|---|---|---|
| GH-INV-000001 | `SURCHARGE` *(historical snapshot)* | 111.30 | 111.30 | 0.00 | 0.00 | PAID |
| GH-INV-000002 | `VALIDATION_ONLY` | 42.00 | 42.00 | 0.00 | 0.00 | PAID |

**INV-000001 payments:** 50.00 + 50.00 + 11.30 = 111.30 (CONFIRMED only).  
**INV-000002 payments:** 20.00 + 22.00 = 42.00 (CONFIRMED only).

`min_order_adjustment_amount` = **0.00** on both.  
`gh_invoice_adjustments` for INV-000002 = **[]** (no surcharge).

Historical INV-000001 keeps policy label `SURCHARGE` in snapshot columns but **no surcharge amount** and totals unchanged except payment status/balance fields as payments posted.

---

## Ledger totals

| Metric | Value |
|---|---|
| Sum ISSUED `total_payable` | 153.30 |
| Sum CONFIRMED payments | 153.30 |
| Sum PENDING payments | 0.00 |
| Outstanding (issued − confirmed) | 0.00 |
| Reconcile identity | **PASS** (`confirmed == issued − outstanding`) |

Pending payments are excluded from balance (pending total shown separately as 0.00).

---

## Launch policy checks exercised

| Check | Result |
|---|---|
| Overpayment rejected | PASS (browser toast on INV-000001) |
| Online initiation UI absent | PASS |
| Bank transfer disabled note | PASS |
| Driver cannot open payments/invoices | Forbidden |
| VALIDATION_ONLY issue above min (40 + VAT → 42) | PASS |
| No surcharge created | PASS |

---

## Order lifecycle money independence

ORD-000013: WORKING → DELIVERY on issue; delivery COMPLETED independently of payment (both PAID before completion in this UAT). Delivery-versus-payment independence preserved.

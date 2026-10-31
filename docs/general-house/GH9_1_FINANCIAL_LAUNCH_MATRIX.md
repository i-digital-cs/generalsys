# GH-9.1 — Financial Launch Matrix (PROPOSED — NOT ACTIVATED)

**Date:** 2026-10-31  
**Rule:** Do not change production/TEST runtime flags without explicit owner approval.  
**Current TEST config** remains as shipped (online initiation still **true**).

---

## 1. Proposed vs current payment flags

| Capability | Current TEST | Proposed LIVE launch | Activated in GH-9.1? |
|---|---|---|---|
| CASH | enabled | **enabled** | Already on |
| BANK TRANSFER | disabled | **disabled** | N/A |
| ONLINE initiation | **enabled** | **disabled** | **No** — awaiting approval |
| ONLINE gateway confirm | disabled | **disabled** | N/A |
| DRIVER COLLECTION | disabled | **disabled** | N/A |
| REFUNDS | disabled | **disabled** | N/A |
| CREDIT NOTES / voids | not implemented | **disabled** | N/A |
| OVERPAYMENT | disabled | **disabled** | N/A |
| PARTIAL payments | enabled | enabled (recommended) | Already on |

Owner must reply **approve / reject** the “online initiation → disabled” change before any config edit.

---

## 2. C-GH-TAX-MIN — decision table (still unresolved)

Tax class remains **null**. No accountant classification assigned.

### Current TEST data

| Object | Value |
|---|---|
| Zones | 1 zone: `GH4-TEST Dubai Zone`, `min_order_amount=25.00`, `min_order_policy=SURCHARGE` (schema default) |
| Issued invoices | 1 row; `min_order_adjustment_amount=0.00` (no surcharge applied on that invoice) |
| Migration default | `min_order_policy` enum default **`SURCHARGE`** |

### Can launch operate with VALIDATION_ONLY?

| Question | Answer |
|---|---|
| Do zones default to SURCHARGE? | **Yes** (migration default) |
| Silent zone policy change in GH-9.1? | **No** — not modified |
| Can orders be created without surcharge? | **Yes** |
| Can invoices ISSUE when surcharge amount = 0? | **Yes** |
| Can invoices ISSUE when surcharge amount > 0 with tax null? | **No** — `min_order_tax_unresolved` blocks ISSUE (fail-closed) |
| Does authorized min-order override creating taxable adjustment still block? | **Yes** if adjustment > 0 and tax class unresolved |
| Existing ISSUED invoice affected by keeping tax null? | No change to snapshot; adjustment was 0 |

### Owner options (unchanged from GH-9)

| Option | Launch effect |
|---|---|
| E — keep tax null | Safe; surcharge invoices cannot ISSUE |
| D — VALIDATION_ONLY for launch zones | Avoids taxable surcharge; **requires explicit zone updates** (not done here) |
| A/B/C — set tax class | Requires accountant approval first |

**Recommended engineering default until approval:** keep null (E). Optionally owner-approved zone policy → VALIDATION_ONLY **without** setting a tax class.

---

## 3. Payment state integrity (verified)

| Rule | Status | Evidence |
|---|---|---|
| CONFIRMED-only collections in balance | PASS | `GhPaymentBalanceService` |
| Derived `payment_status` | PASS | `syncInvoicePaymentStatus` |
| Overpayment rejected | PASS | PaymentTest + service guard |
| Partial consistency | PASS | PARTIAL when 0 < confirmed < total |
| Pending excluded | PASS | Online pending test |
| No direct HTTP payment_status mutation | PASS | empty `$fillable` + MassAssignmentHardeningTest |
| Concurrent guard | PASS | `lockForUpdate` + re-check after confirm |

---

## 4. Owner decisions required

1. Approve proposed payment matrix (esp. disable online initiation)?  
2. C-GH-TAX-MIN: E / D / A–C (+ accountant if A–C)?  
3. If D: authorize zone policy updates on LIVE/TEST?  
4. Accept no refunds/credit notes at launch?

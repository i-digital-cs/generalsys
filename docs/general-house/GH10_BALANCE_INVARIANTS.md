# GH-10 — Balance Invariants & Equations

**SSOT:** `GhPaymentBalanceService` (extended). Controllers/Blade consume only.

All amounts are `DECIMAL(12,2)` / `GhMoney` (bcmath, ROUND_HALF_UP).

---

## Per-invoice symbols

| Symbol | Definition |
|---|---|
| `G` | Issued invoice `total_payable` (immutable snapshot) |
| `D` | Sum of **ISSUED** debit-adjustment `total_debit` for the invoice (Phase 4.1; never mutates `G`) |
| `C` | Sum of **ISSUED** credit-note `total_credit` for the invoice |
| `N` | `max(0, G + D − C)` = **net invoiced obligation** |
| `P` | Sum of **CONFIRMED** payment `amount` (immutable rows; never deleted) |
| `R` | Sum of **PAID_OUT** cash-refund `amount` for the invoice |
| `V` | Sum of **EXECUTED** payment-recording correction reversal amounts (if enabled; else `0`) |
| `CashNet` | `max(0, P − R − V)` |

---

## Derived balances

```text
remaining_due      = max(0, N − CashNet)
customer_credit    = max(0, CashNet − N)
refundable         = customer_credit          # only PAID_OUT reduces this
net_cash_collected = CashNet                  # reporting
```

Payment status (invoice row, synced):

```text
PAID    ⇔ remaining_due = 0
PARTIAL ⇔ CashNet > 0 ∧ remaining_due > 0
UNPAID  ⇔ CashNet = 0 ∧ remaining_due > 0
```

Zero-net invoice (`N = 0`) with no cash → PAID.

---

## Mandatory invariants

1. `C ≤ G + D` (cumulative issued credits never exceed gross issued + issued debits).  
2. `N ≥ 0`; `D ≥ 0` (debit documents are positive-only; reductions use credit notes).  
3. `R + pending/approved-unpaid refunds ≤ refundable_at_request` (no over-refund).  
4. Unapproved / unexecuted refunds **do not** change `R` or `CashNet`.  
5. Pending credit notes **do not** change `C`; draft/cancelled debit adjustments **do not** change `D`.  
6. Original `gh_invoices` money columns and CONFIRMED `gh_payments` rows are never updated for corrections (including debit issue).  
7. Concurrent issue/payout uses row locks + idempotency keys.  
8. Refunds are **not** stored as ordinary `gh_payments` CONFIRMED rows.  
9. Issuing a debit adjustment does **not** create a customer payment.

---

## Reporting (invoice / period)

| Label | Formula |
|---|---|
| Gross issued | `Σ G` |
| Issued debit adjustments | `Σ D` |
| Issued credits | `Σ C` |
| Net invoiced | `Σ N` (= `Σ max(0, G + D − C)`) |
| Confirmed collections | `Σ P` |
| Executed refunds | `Σ R` |
| Net cash collected | `Σ (P − R − V)` |
| Outstanding receivables | `Σ remaining_due` |
| Customer credit liabilities | `Σ customer_credit` |

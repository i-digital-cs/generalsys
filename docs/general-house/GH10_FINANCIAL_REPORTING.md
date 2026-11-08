# GH-10 — Financial Reporting Formulas

SSOT balances: `GhPaymentBalanceService` / `GH10_BALANCE_INVARIANTS.md`.

## Label preservation

| Existing KPI label | Meaning (unchanged) |
|---|---|
| Issued invoice value | Gross `Σ G` (issued `total_payable`) |
| Confirmed cash / collections | `Σ P` CONFIRMED payments in period |
| Outstanding receivables | `Σ remaining_due` via balance SSOT |

Do **not** silently redefine these labels to net-of-credits figures.

## Additive GH-10 KPIs (dashboard)

| KPI key | Formula |
|---|---|
| `issued_credit_notes_value` | `Σ C` for credit notes ISSUED in period |
| `net_invoiced_value` | `max(0, ΣG − ΣC)` for period gross vs credits |
| `cash_refunds_paid_out` | `Σ R` PAID_OUT in period |
| `net_cash_collected` | `max(0, ΣP − ΣR)` for period (V=0 while execute gated) |
| `customer_credit_liabilities` | `Σ customer_credit` on issued snapshot |

## Outstanding / invoice reports

`GhReportQueryService::remainingForInvoice` now returns `remaining_due` from the balance SSOT (includes issued credits and paid-out refunds).

B2B grouped SQL still uses gross − confirmed for historical continuity; treat that export as **legacy gross outstanding** until a forward migration of the grouped query is approved. Documented open decision below.

## Open decisions

1. Period netting for credits vs invoices issued in different months (current: credits by `issued_at`, invoices by `issued_at` independently).
2. Whether B2B grouped report must switch to SSOT remaining_due (recommended; not yet rewritten to avoid silent KPI drift).
3. Whether EXECUTED payment reversals (`V`) appear as a separate dashboard tile when execute is enabled.

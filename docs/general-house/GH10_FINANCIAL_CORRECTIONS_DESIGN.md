# GH-10 — Financial Corrections Design (Option B)

**Environment:** TEST only (`d14098_general`). No LIVE deploy. No git push/merge.

**Owner authorization:** Option B — in-app credit notes, cash refunds, financial corrections before commercial launch.

---

## Goals

1. Correct issued invoices **without mutating** issued invoice money snapshots.
2. Return cash **without mutating** CONFIRMED payment rows.
3. Separate **payment recording errors** from customer refunds and invoice credits.
4. Keep every mutation auditable (actor, reason, before/after status, UTC timestamps).
5. Extend payment balance SSOT so reporting and UI reconcile.

---

## Architecture

```text
Controller (thin) → Domain Service → Model / events
Balances: GhPaymentBalanceService (SSOT)
VAT/line math for credits: proportional share of original invoice-line snapshots
  (tax_class / tax_amount / taxable_base) — not a second invoice engine
```

Domains:

| Domain | Service | Document refs |
|---|---|---|
| Credit notes | `GhCreditNoteService` | `GH-CN-######` |
| Cash refunds | `GhRefundService` | `GH-RF-######` |
| Payment recording corrections | `GhFinancialCorrectionService` | `GH-FC-######` |

Launch flags: `config/general_house.php` → `corrections.*` via `GhLaunchConfig`.

---

## Lifecycles

### Credit note

`DRAFT → PENDING_APPROVAL → APPROVED → ISSUED`  
Terminal: `REJECTED`, `CANCELLED`  
Ledger effect only on **ISSUED**.

### Cash refund

`REQUESTED → PENDING_APPROVAL → APPROVED → PAID_OUT`  
Terminal: `REJECTED`, `CANCELLED`  
Ledger effect only on **PAID_OUT**.

### Payment recording correction

`REQUESTED → PENDING_APPROVAL → APPROVED → EXECUTED`  
**EXECUTE blocked** until `payment_correction_execute_enabled=true` (owner accounting policy open).

---

## Separation of duties

When flags are true (TEST defaults):

- Approver ≠ submitter/requester (`require_distinct_approver`).
- Payout/execute actor ≠ approver (`require_distinct_payout_actor`).

Drivers never receive correction privileges (`GhCatalogAccess::mayManage` + Gate filtering in `GhServiceMenu`).

---

## Immutability

- Issued `gh_invoices` totals / lines: never rewritten by GH-10.
- CONFIRMED `gh_payments`: never deleted or status-flipped by refunds/credits.
- Corrections append independent `gh_*` documents + event rows.

---

## Related docs

- `GH10_CREDIT_NOTE_RULES.md`
- `GH10_REFUND_RULES.md`
- `GH10_BALANCE_INVARIANTS.md`
- `GH10_FINANCIAL_REPORTING.md`
- `GH10_SECURITY_REVIEW.md`
- `GH10_TEST_EVIDENCE.md`

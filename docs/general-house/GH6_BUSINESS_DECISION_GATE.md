# GH-6 — Business Decision Gate (2026-10-31)

Cross-ref: `FINANCIAL_RULES.md` §8, `DECISION_LOG.md` D-GH-008/009, `DATABASE_DESIGN.md` §8, `DESIGN_CHANGE_SUMMARY.md`.

## Explicitly approved (implement)

| Topic | Source | GH-6 behavior |
|---|---|---|
| GH payment ledger only | D-GH-009 | `gh_payments` — never Gross treasury |
| Methods: Cash + Online | FINANCIAL_RULES §8 | `CASH`, `ONLINE` only |
| Partial payments + outstanding | FINANCIAL_RULES §8 | Multiple CONFIRMED payments per invoice |
| Online initiation ≠ success | D-GH-008 | ONLINE rows stay `PENDING` until verified confirmation |
| Cash traceable to collector | FINANCIAL_RULES §8 | `collector_user_id` + confirmer required |
| Idempotency | DATABASE_DESIGN §8 | Unique `idempotency_key` (+ unique `reference`) |
| Payment ⊥ logistics | STATE_MACHINE §5 | Confirming payment does not COMPLETE order; delivery confirm does not mark PAID |
| Unpaid delivery | GH-5 + independence | COMPLETED while UNPAID remains allowed |
| AED only | D-GH-006 / C-GH-MULTI-CUR deferred | Currency fixed AED |
| Refunds / reversals | D-GH-012 / C-GH-CORR | **Not implemented** |

## Unresolved / disabled (do not invent)

| Topic | Status | GH-6 handling |
|---|---|---|
| Bank transfer + receipt review | Not in approved methods | Method rejected; no UI |
| Online gateway provider / webhooks / capture | PROPOSED stub only | No confirm path; no fake PAID |
| Driver delivery payment (one-step) | Owner-approved | Driver selects CASH or ONLINE and confirms delivery → payment **CONFIRMED** immediately for outstanding balance |
| ONLINE at delivery | Operator-recorded | CONFIRMED without gateway callback; metadata `verification_source=OPERATOR_RECORDED` (not provider-verified) |
| Settlement / driver cash reconciliation | Deferred | Not in this phase |
| Manager direct cash | Still available | Managers may still record CONFIRMED cash on the invoice panel |
| Overpayment / OVERPAID ledger | Example status only; rules OPEN | Amounts that would exceed remaining balance are **rejected** |
| Payment reversals of CONFIRMED | C-GH-CORR | CONFIRMED immutable; no reverse API |
| Accountant ACL for payments | “confirm” in BRD | Deferred — Manager-only (same as GH-5 invoice admin) |
| `PENDING_REVIEW` / `REJECTED` | For bank review | Enum reserved; unused until bank approved |

## Owner confirmations still open

- **C-GH-CORR** — refund / reverse rules  
- **C-GH-TAX-MIN** — unrelated to payments (invoice surcharge)  
- Future: bank transfer SOP, gateway vendor, driver collection SOP  

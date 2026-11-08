# GH-10 — Cash Refund Rules

## Nature

A cash refund is a **customer cash payout** against **customer credit**, not an ordinary invoice payment and not a bookkeeping reversal of a CONFIRMED payment.

## Refundable amount

```text
customer_credit = max(0, CashNet − N)
refundable      = customer_credit
available       = refundable − Σ(amounts in REQUESTED|PENDING_APPROVAL|APPROVED)
```

Typical path after a credit note against a paid invoice: credit lowers `N`, creating customer credit, then refund pays cash out.

Refunds without prior credit (or overpayment) are rejected when `available = 0`.

## Lifecycle

| Status | Ledger effect |
|---|---|
| REQUESTED / PENDING_APPROVAL / APPROVED | Reserves capacity only |
| PAID_OUT | Increments `R` (refunds_paid_out); reduces CashNet |
| REJECTED / CANCELLED | Releases reservation |

## Controls

1. Authorization required at each step (policy + service asserts).
2. Amount ≤ available at request, submit, approve, and payout (row locks).
3. Duplicate payout is idempotent (already PAID_OUT returns same row).
4. Concurrent over-refund blocked via invoice lock + available recalculation.
5. Original payment history preserved.
6. Drivers cannot request/approve/pay out.

## Separation of duties

- Approver ≠ requester when `require_distinct_approver`.
- Payout actor ≠ approver when `require_distinct_payout_actor`.

**Owner note:** TEST expects ≥2 distinct managers for approve + payout. If only one manager exists in an environment, payout remains blocked by SoD — do not weaken the flag without an explicit control decision.

## References

- Refund number `GH-RF-######` assigned on **PAID_OUT**.
- Optional `credit_note_id` must be an **ISSUED** credit note on the same invoice.

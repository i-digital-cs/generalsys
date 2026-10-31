# GH-9.2 — Financial Verification

**Date:** 2026-10-31

---

## Cash / partial / overpay / concurrency

| Scenario | Result | Evidence |
|---|---|---|
| Full cash | PASS | `PaymentTest` |
| Partial then remaining | PASS | `PaymentTest`, `Gh92HttpLaunchUatTest` |
| Overpayment rejected | PASS | `PaymentTest` |
| Idempotent retry | PASS | `PaymentTest` |
| Concurrent overpay guard | PASS (lock + re-check) | `GhPaymentService` |
| Issued totals immutable | PASS | HTTP cash test asserts total unchanged |
| CONFIRMED-only balance | PASS | Balance SSOT + pending legacy test |
| Delivery independent of payment | PASS | `PaymentTest` delivery while unpaid |

---

## Online / bank / driver

| Scenario | Result |
|---|---|
| Online initiation service | Rejected (`payment_online_disabled`) |
| Online HTTP | **403** (policy) |
| Online UI | Absent on invoice show (browser AR + HTTP) |
| Gateway confirm | Still blocked |
| Bank transfer | Blocked |
| Driver cash | Forbidden |
| Legacy PENDING online | Does not reduce balance; left in place |

---

## Minimum order VALIDATION_ONLY

| Scenario | Result |
|---|---|
| Above minimum | PASS — no block, adj 0 |
| Exact minimum | PASS |
| Below minimum | PASS — `min_order_validation`, adj 0 |
| Waiver | PASS — issue without surcharge |
| SURCHARGE zone with surcharge disabled | Treated as VALIDATION_ONLY, no adjustment |
| Surcharge + unresolved tax (capability on) | Fail-closed tax unresolved |
| Historical issued invoice | Unchanged (adj was 0) |

---

## Refunds / credit notes (not implemented)

| Situation | Documented operational limitation |
|---|---|
| Wrong confirmed cash | No in-app reverse; manager escalates offline; ledger stays as audit evidence |
| Wrong issued invoice | No void/credit note; do not edit ISSUED rows |
| Customer refund request | Out of system until C-GH-CORR approved |
| Who resolves | Owner/manager offline process (to be named by owner) |
| Evidence | `gh_payments` + `gh_payment_events` retained |
| Block limited commercial launch? | **Owner decision** — see GO/NO-GO |

---

## Reconciliation (TEST spot)

- Issued invoice `GH-INV-000001` remains ISSUED; cash form shows remaining.  
- Reports use CONFIRMED only (dashboard/outstanding tests).  

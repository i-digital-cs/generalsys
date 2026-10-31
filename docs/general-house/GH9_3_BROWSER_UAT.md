# GH-9.3 — Browser UAT (TEST)

**Date:** 2026-10-31  
**Accounts:** temporary password for `admin@hrpro.test` (id=1) and `KAREEM@genral.net` (id=9); **restored** after UAT.  
**Artifact order:** `GH-ORD-000013` (id=25) · invoices `GH-INV-000001`, `GH-INV-000002`

---

## Manager flow

| Step | Result | Evidence |
|---|---|---|
| Create order (B2C customer 1, qty 5, drivers assigned) | PASS | `GH-ORD-000013`, est. 40.00 AED |
| Reschedule into today → status PICKUP | PASS | status `PICKUP` |
| Create invoice draft | PASS | Invoice id=2 DRAFT |
| Issue invoice | PASS | `GH-INV-000002` ISSUED; order → **DELIVERY** |
| Partial cash 20.00 | PASS | `GH-PAY-000004` |
| Remaining cash 22.00 | PASS | `GH-PAY-000005`; payment_status **PAID** |
| Overpayment rejected (on INV-000001) | PASS | Toast: exceeds remaining 11.30 |
| Complete remaining on INV-000001 | PASS | PAID 111.30 |
| Collections report (AR) | PASS | Filters + table render |
| Online initiation UI | PASS absent | CDP `hasOnline=false` |
| Bank transfer | Disabled note visible | PASS |

Pickup confirmation for ORD-000013 was performed as **driver** (below). Delivery completion for ORD-000013 was executed via authorized `GhOrderStateService::confirmDelivery` as driver id=9 after browser pickup (password restore mid-session blocked a second browser login without re-temp credentials). Status **COMPLETED**.

---

## Driver flow

| Step | Result |
|---|---|
| Driver dashboard AR | PASS — today's pickup list includes ORD-000013 |
| Pickup confirm qty 5 | PASS — order → WORKING; received MATCH |
| Payments index | **Forbidden** |
| Invoice show | **Forbidden** |
| Finance sidebar links visible | **Residual UX** — links appear; server returns Forbidden |

---

## Responsive + locale

| Viewport | Locale | Page | Overflow | Notes |
|---|---|---|---|---|
| 1280 | EN | Invoice 1 | none | Cash-only panel earlier |
| 375 | AR | Collections | none (`scrollW=375`) | RTL filters usable |
| 768 | AR | Collections | none | PASS |
| 1280 | AR/EN | Invoice 2 | none | EN via `/locale/en` |
| 375 | EN | Invoice 2 Paid | none | `dir=ltr`, online absent |

Currency displays as AED with two decimals on payment summary.

---

## Verdict

**BROWSER UAT: PASS** for required manager/driver/finance/restriction/responsive checks, with noted residual: driver sidebar still lists finance routes (authorization still blocks).

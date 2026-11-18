# General House — State Machine (Phase 0, revised)

**Status:** GH-4 **implemented** on TEST (transitions via `GhOrderStateService` only).  
**Alignment:** Owner CONDITIONAL APPROVAL 2026-10-25 · GH-4 authorization 2026-10-31.

## 1. Primary logistics lifecycle (APPROVED)

```
LATER → PICKUP → WORKING → DELIVERY → COMPLETED
```

| Transition | Trigger | Notes |
|---|---|---|
| → `PICKUP` | Scheduler / eligibility job | Eligible from **00:00 Asia/Dubai on the scheduled pickup date** (D-GH-022). Manager may promote earlier with reason + audit. |
| → `WORKING` | Physical **pickup confirmation** | Authorized actor (typically pickup driver or elevated role) |
| → `DELIVERY` | Scheduler (`gh:promote-pickup-eligible`) or Manager **mark delivery ready** | Requires `WORKING` + confirmed pickup. Automatic: needs `scheduled_delivery_at`; same day as actual pickup → `picked_up_at` + 2h, future day → 08:00 Asia/Dubai on the delivery date; skipped while `auto_promote_held`. Manual: confirmed pickup only. **Invoice issuance is not required and does not change order status** (issuance allowed in `WORKING` / `DELIVERY` / `COMPLETED`). Delivery driver optional. |
| → `COMPLETED` | **Delivery confirmation** | Authorized actor (typically delivery driver or elevated role) |

Payment status is **independent** of this chain (see §5).

## 2. Pickup and delivery drivers (APPROVED revision)

- **Pickup** and **delivery** drivers are independently assignable.
- Assignment history is retained (`gh_order_assignments`); each row has `responsibility` = `PICKUP` | `DELIVERY`.
- Reassignment: close current row (`unassigned_at`), open new current row; emit `DRIVER_REASSIGNED` event.
- Drivers are existing `users` with DRIVER (or equivalent) permissions — no duplicate driver identity store.

**Admin deletion (not a status):** a Manager may soft-delete an order only while it is `LATER`, or `PICKUP` with no confirmed physical pickup, and only if it has no linked financial records (D-GH-023). The row stays in `gh_orders` with `deleted_at`, `deleted_by`, `delete_reason`; an `ORDER_DELETED` event is written. Deleted orders are excluded from boards, drivers, the scheduler and reports. `CANCELLED` is a separate status and cannot be deleted.

## 3. Operational events (prefer over extra primary states) (APPROVED)

Failed attempts and side effects are **events / attempt records**, not primary status values:

| Event / attempt | Effect on primary status |
|---|---|
| Failed pickup attempt | Stay in `PICKUP` (or prior eligible); record `gh_order_attempts` |
| Failed delivery attempt | Stay in `DELIVERY`; record attempt |
| Driver reassignment | Status unchanged unless policy says otherwise |
| Reschedule pickup/delivery windows | May return toward `LATER` / re-enter eligibility — see §4 |

Each attempt stores `occurred_at` (field reality) and `recorded_at` (system write time).

## 4. Cancellation & rescheduling

| Action | Design stance |
|---|---|
| Cancel | Terminal or soft-terminal side path; **must not** leave unpaid financial obligations ambiguous |
| Reschedule | Update windows; may reset eligibility; history preserved |

**REQUIRES OWNER CONFIRMATION (`C-GH-CANCEL`):** Exact cancel rules when invoice is DRAFT vs ISSUED; refunds; whether cancel is a status or only an event + flag.

**PROPOSED (pending confirmation):**  
- Pre-issue: cancel allowed with reason; order marked cancelled; no payment due.  
- Post-issue: cancel requires explicit correction path (void/credit) — not silent delete.

## 5. Payment status (independent)

Invoice / payment ledger statuses (e.g. `UNPAID`, `PARTIAL`, `PAID`, `OVERPAID`) do **not** move the logistics primary state.

Online payment **initiation** ≠ successful payment.

## 6. Invoice lifecycle (APPROVED for v1 scope)

```
DRAFT → ISSUED
         ↘ VOID (correction — rules TBD before implement)
ISSUED → CREDIT_NOTE / REFUND documents (separate artifacts — rules TBD)
```

- One **primary** invoice per order in v1; DB + transaction prevent duplicate ISSUE.
- Issued invoices are **immutable** (no silent edit).
- Correction mechanisms designed in schema/docs; **not implemented** until owner approves detailed financial/tax rules (`C-GH-CORR`).

## 7. Authorization matrix (summary)

| Transition | Typical actors |
|---|---|
| Eligibility → PICKUP | System job |
| Confirm pickup → WORKING | Pickup driver / Manager / Admin |
| WORKING → DELIVERY | System job (schedule timing) / Manager (mark delivery ready) |
| Issue invoice (no status change) | Authorized invoice issuer (Admin/Manager/cashier per ACL) |
| Confirm delivery → COMPLETED | Delivery driver / Manager / Admin |
| Assign/reassign drivers | Manager / Admin (policy TBD for self) |
| Cancel / override min-order | Elevated roles only |

Exact Policy classes follow Gross/HR patterns in Phase 1+.

## 8. Idempotency

- Replaying the same confirmation with the same business key must not double-transition.
- ISSUE, payment record, and status transitions use locks / unique constraints / idempotency keys.

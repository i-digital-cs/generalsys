# General House — Reporting KPI Definitions (GH-8)

**Timezone:** Business calendar uses `Asia/Dubai` (`config('general_house.timezone')`).  
Period boundaries are converted to **UTC** for SQL filters.  
`config('app.timezone')` is **not** changed.

**Financial SSOT:** Issued invoice `total_payable` + `GhPaymentBalanceService` (only `CONFIRMED` payments).  
Never use order `estimated_subtotal`. Never count `PENDING` / cancelled / rejected payments as collected.  
Invoice issuance ≠ payment confirmation.

---

## Metric kinds

| Kind | Meaning |
|------|---------|
| **FLOW** | Events inside the selected Dubai date range (inclusive calendar days → UTC `[start, endExclusive)`). |
| **SNAPSHOT** | Current operational / financial state; **not** filtered by creation date of the period. Optional dimension filters (zone, customer, driver) still apply. |
| **AS_OF** | Snapshot evaluated using balances as of “now” (TEST/LIVE clock). Historical as-of reconstruction is out of scope for GH-8. |
| **UNAVAILABLE** | Schema lacks a reliable deadline/timestamp; UI shows “—” and this doc explains why. |

---

## Operational KPIs

| KPI | Kind | Definition |
|-----|------|------------|
| Total orders | FLOW | Count of orders with `created_at` in period. |
| New orders | FLOW | Same as total orders (created in period). |
| Awaiting pickup | SNAPSHOT | `status = PICKUP`. |
| In WORKING | SNAPSHOT | `status = WORKING`. |
| In DELIVERY | SNAPSHOT | `status = DELIVERY`. |
| Completed orders | FLOW | `status` transitions completed: `delivered_at` in period. |
| Cancelled orders | FLOW | `cancelled_at` in period. |
| Overdue pickups | SNAPSHOT | `status IN (LATER, PICKUP)` and `pickup_ends_at < UTC now`. |
| Overdue deliveries | UNAVAILABLE | No delivery due window in schema; do not invent deadlines. Use pending deliveries snapshot instead. |
| Failed pickup attempts | FLOW | `gh_order_attempts` where `attempt_type=PICKUP`, `outcome=FAILED`, `occurred_at` in period. |
| Failed delivery attempts | FLOW | Same for `DELIVERY`. |

## Financial KPIs

| KPI | Kind | Definition |
|-----|------|------------|
| Issued invoice value | FLOW | `SUM(total_payable)` for `status=ISSUED` and `issued_at` in period. |
| Confirmed cash collections | FLOW | `SUM(amount)` payments `status=CONFIRMED`, `method=CASH`, `confirmed_at` in period. |
| Total confirmed collections | FLOW | `SUM(amount)` all methods with `status=CONFIRMED`, `confirmed_at` in period. |
| Outstanding receivables | AS_OF | For issued invoices with `payment_status ≠ PAID`: `SUM(total_payable − confirmed)`. Confirmed = SSOT sum of CONFIRMED payments. |
| Partially paid invoices | SNAPSHOT | Issued invoices with `payment_status=PARTIAL`. |
| Unpaid invoices | SNAPSHOT | Issued invoices with `payment_status=UNPAID`. |
| Paid invoices | SNAPSHOT | Issued invoices with `payment_status=PAID`. |
| Average invoice value | FLOW | Issued invoice value ÷ count of issued invoices in period (0 if none). |

## Driver KPIs

| KPI | Kind | Definition |
|-----|------|------------|
| Assigned pickups | SNAPSHOT | Orders with `pickup_driver_id` set and `status=PICKUP` (optionally filtered to one driver). |
| Assigned deliveries | SNAPSHOT | `delivery_driver_id` set and `status=DELIVERY`. |
| Completed pickups | FLOW | `picked_up_at` in period (pickup confirmed). |
| Completed deliveries | FLOW | `delivered_at` in period. |
| Failed attempts | FLOW | Failed attempts in period (pickup + delivery), optionally scoped by order’s assigned driver. |
| Pending tasks by driver | SNAPSHOT | Per-driver counts of PICKUP + DELIVERY assignments. |

## Timing (operational reports)

| Metric | Reliability |
|--------|-------------|
| Pickup timing | `picked_up_at − pickup_starts_at` for pickups completed in period (minutes). |
| Delivery timing | `delivered_at − invoice_issued_at` when both present (hours). |
| Overdue deliveries | **Unavailable** — no delivery SLA column. |

## Terminology

- **Issued invoice value** — face value of invoices issued; not cash collected.
- **Confirmed collection** — payment row `CONFIRMED` only.
- **Outstanding / remaining** — `total_payable − confirmed`; never negative in reports (floored at 0.00).
- **Days outstanding** — Dubai calendar days from `issued_at` date to today (issued unpaid/partial only).

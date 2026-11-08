# Driver Movement Log

## Rule (owner)

Exactly **two** movement types — never combined:

| Type | AR | EN |
|------|----|----|
| `PICKUP` | استلام | Pickup |
| `DELIVERY` | تسليم | Delivery |

One order may produce **two independent movements**. Same driver → two cards. Different drivers → each sees only their own.

## Implementation (TEST)

- Read model: `GhDriverMovementLogService` projects movements from `gh_orders` + `gh_order_attempts` + `gh_order_assignments`.
- UI: `gh.driver.history` (سجل الحركات) — one card per movement; filters by type / status / date.
- Dashboard “recent completed” and “failed followups” also use separate movement cards.
- No change to pickup/delivery confirmation, payments, or invoices.

## Sort

Chronological by scheduled time; when equal, **PICKUP before DELIVERY**. Never merge by `order_id`.

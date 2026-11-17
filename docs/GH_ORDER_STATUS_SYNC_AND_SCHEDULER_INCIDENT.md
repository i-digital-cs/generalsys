# GH Order Status Sync & Auto-Delivery Investigation

**Environment:** GeneralSys TEST only  
**Date:** 2026-11-16  
**LIVE / commit / push:** not performed  

---

## Affected order

| Field | Value |
|-------|--------|
| ID | `1` |
| Reference | `GH-ORD-000001` |
| **Persisted `status`** | **`WORKING`** |
| `picked_up_at` | `2026-11-22 15:54:00` UTC (future relative to investigation day) |
| `scheduled_delivery_at` | **`NULL`** |
| `auto_promote_held` | `false` |
| Pickup / delivery drivers | both user `9` |
| Invoice | none |

### Event trail (authoritative)

1. `ORDER_CREATED` → initial status `PICKUP`  
2. Pickup/delivery driver assigned  
3. `PICKUP_CONFIRMED` PICKUP → WORKING (`occurred_at` payload `2026-11-22T19:54:00+04:00`)

No `DELIVERY_READY` event exists.

---

## Displayed status (before fix)

| Surface | What was shown | Source |
|---------|----------------|--------|
| Driver board / task | **WORKING** | `gh_orders.status` via `presentCard()` / status badge |
| Admin board status badge | **WORKING** | same SSOT |
| Admin/driver card **schedule kind** | labeled **“Pickup” / “Delivery”** | `schedule_kind` metadata — **not** order status |
| Admin show eligibility cell | echoed order status or short badges | could be confused with logistics copy |

**Conclusion:** There was **no split persisted status** between admin and driver APIs. Both boards already serialized `status = WORKING` from the same row. The owner-visible “PICKUP / DELIVERY” mismatch was primarily **schedule-kind labels** using the same vocabulary as operational statuses (Arabic “استلام/توصيل”), plus expectation that WORKING should auto-advance without a delivery appointment.

---

## Auto-delivery rules vs this order

Approved rules (implemented in `GhDeliveryEligibilityService`):

| Rule | This order |
|------|------------|
| Future-day → DELIVERY at 08:00 Asia/Dubai on delivery date | N/A — no `scheduled_delivery_at` |
| Same-day → +2h after actual pickup | N/A — no appointment |
| Invoice must not block | N/A |
| No confirmed pickup → no promote | Pickup exists |
| **No scheduled delivery → stay WORKING** | **Applies — correctly not promoted** |
| Manual hold | Not held |

`php artisan gh:promote-pickup-eligible` on TEST: `Promoted 0 … to DELIVERY` — expected.

---

## Scheduler execution evidence

| Check | Result |
|-------|--------|
| Command registered | `gh:promote-pickup-eligible` in `routes/console.php` every 5 minutes |
| Host cron | `* * * * * cd …/generalsys.idigital-cs.com && /usr/local/bin/php artisan schedule:run >> storage/logs/scheduler.log` |
| `schedule:list` | Shows task next-due |
| `scheduler.log` | Repeated successful runs (e.g. 2026-11-16 07:10 … 08:45 DONE ~300ms) |
| Live promote command | Runs cleanly; 0 promotions for ineligible WORKING order |

Scheduler **is running**; it is not the cause of this order remaining WORKING.

---

## Root causes (confirmed)

1. **UI vocabulary collision (display):** Board cards used `kind_pickup` / `kind_delivery` (“Pickup” / “Delivery”) next to the real status badge. For WORKING orders this looked like a second status.  
2. **Business expectation vs rules:** Order has no `scheduled_delivery_at`, so auto WORKING→DELIVERY is correctly blocked.  
3. **Eligibility math bug (code):** Same-day vs future-day used `pickup_starts_at` calendar day instead of **actual `picked_up_at`** day — wrong for late pickups.  
4. **Future operational stamp allowed:** Pickup `occurred_at` could be days in the future (`2026-11-22`), creating confusing timelines (not a dual-status bug, but unsafe).

**Not found:** Separate admin/driver status columns, cache of status, or failed transition leaving divergent SSOT.

---

## Fixes implemented (TEST)

| Fix | Files |
|-----|-------|
| WORKING cards use `schedule_kind=working` + non-status labels (“In laundry” / “Pickup window” / “Delivery window”) | `GhOrderBoardService`, `order-card.blade.php`, `lang/{en,ar}/gh.php` |
| Same-day/future-day based on **actual pickup** local date | `GhDeliveryEligibilityService` |
| Reject pickup `occurred_at` > now+15m | `GhOrderStateService::parseOccurredAt` |
| Admin show: WORKING eligibility hints + SSOT note | `orders/show.blade.php` + langs |
| Focused regression tests | `OrderStatusSyncAndSchedulerTest.php`; updated `InvoiceIndependentDeliveryTest` late-pickup cases |
| Stabilize unrelated flaky movement-log sort fixture | `DriverMovementLogTest` |

**No** status rewrite on `GH-ORD-000001`. **No** fabricated delivery timestamps.

---

## Data repair proposal (owner approval required)

Do **not** force `status=DELIVERY` without a schedule decision.

**Option A (recommended):** Manager sets `scheduled_delivery_at` on edit (or Mark ready for delivery). Scheduler/reevaluation then applies timing rules.  
**Option B:** Manager manual WORKING→DELIVERY (authorized) if delivery should open without appointment.  
**Option C:** Correct `picked_up_at` only if the Nov 22 stamp was a data-entry error — provide the true Asia/Dubai confirmation time for an auditable repair migration (not done).

---

## Tests

```text
php artisan test --filter='OrderStatusSyncAndSchedulerTest|InvoiceIndependentDeliveryTest'
→ PASS (20)

php artisan test --filter=GeneralHouse
→ PASS — 353 passed (2033 assertions), 16.50s
vendor/bin/pint --dirty → passed
```

Covered: admin/driver status agreement, same-day +2h using actual pickup day, future-day 08:00, no-invoice, missing pickup/schedule, hold/resume + idempotent scheduler, future occurred_at rejected, driver delivery list after promote.

---

## Remaining issues

| Item | Status |
|------|--------|
| `GH-ORD-000001` still WORKING until schedule/manual ready | Expected by rules |
| Authenticated browser screenshots of AR board labels | Not captured (login) |
| LIVE | Untouched |

### Verdict

| Area | Result |
|------|--------|
| Persisted SSOT consistency | **PASS** (already aligned; both UIs read `gh_orders.status`) |
| Misleading schedule/status labels | **PASS** (fixed) |
| Scheduler running | **PASS** (evidence in logs) |
| Auto-delivery eligibility math | **PASS** (actual-pickup-day fix) |
| Order data repair | **CONDITIONAL** — awaiting owner choice A/B/C |

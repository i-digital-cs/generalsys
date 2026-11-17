# GH Mandatory Delivery Schedule & Optional Driver Assignment

**Environment:** GeneralSys TEST only (`https://generalsys.idigital-cs.com/`)  
**Date:** 2026-11-16  
**LIVE / commit / push:** not performed  
**Verdict:** **PASS** (authenticated browser UI walkthrough: not captured — CONDITIONAL for visual only)

Related prior incident: `docs/GH_ORDER_STATUS_SYNC_AND_SCHEDULER_INCIDENT.md`

---

## Owner rules implemented

1. Delivery date **and** time mandatory on create (and full pre-pickup edit).
2. Delivery driver remains optional (`NULL` / Unassigned).
3. Auto WORKING → DELIVERY runs without a delivery driver.
4. Invoice does not block operational promotion.
5. No 10:00 AM fallback invented.
6. Confirmed pickup + manager auto-promote hold preserved.

---

## Exact files changed

| Area | Files |
|------|--------|
| Validation | `app/Http/Requests/GeneralHouse/StoreGhOrderRequest.php`, `UpdateGhOrderRequest.php` |
| Create/update service | `app/Services/GeneralHouse/Orders/GhOrderService.php` |
| No silent driver copy | `app/Services/GeneralHouse/Orders/GhOrderStateService.php`, `GhOrderManualTransitionService.php` |
| Admin warning | `app/Services/GeneralHouse/Orders/GhOrderWarningService.php` |
| Board card flags / edit CTA | `app/Services/GeneralHouse/Orders/GhOrderBoardService.php` |
| UI | `resources/views/gh/orders/form.blade.php`, `show.blade.php`, `resources/views/gh/partials/order-card.blade.php` |
| Lang EN/AR | `lang/en/gh.php`, `lang/ar/gh.php` |
| Tests | `tests/Feature/GeneralHouse/MandatoryDeliveryScheduleTest.php` (+ updates to `OrdersTest`, `ScheduledPickupTest`, `InvoiceIndependentDeliveryTest`, `OrderManualTransitionTest`, `PostSaveRedirectTest`, `TimezoneIntegrityTest`, `InvoiceTest`, `PackagePickupStaffCountingTest`) |
| Report | this file |

**Migrations:** none (no schema change).

---

## Validation rules

### Create (`StoreGhOrderRequest` + `GhOrderService::create`)

- Require `scheduled_delivery_at` **or** both `scheduled_delivery_date` + `scheduled_delivery_time`.
- Incomplete date/time → bilingual error:
  - EN: `Please select a delivery date and time before creating the order.`
  - AR: `يرجى تحديد تاريخ ووقت التوصيل قبل إنشاء الطلب.`
- Parsed as Asia/Dubai wall clock → UTC storage via existing `GhDeliveryEligibilityService::scheduledDeliveryFromLocal`.
- Must not be before the pickup calendar day.
- **No silent default** delivery datetime.
- Service `parseScheduledDelivery(..., required: true)` rejects empty on create/full edit.

### Update

- Full pre-pickup edit: delivery schedule still mandatory.
- Restricted post-pickup: schedule may be set on legacy NULL rows; notes-only updates do not invent a schedule.

---

## Driver assignment behavior

| Action | Result |
|--------|--------|
| Create with omitted / empty delivery driver | `delivery_driver_id = NULL` |
| Create with explicit driver | Assigned + audited |
| Full/restricted update with empty `delivery_driver_id` | Clears assignment (Unassigned) |
| Full/restricted update **omitting** the key | Leaves existing assignment unchanged |
| Pickup confirm / auto promote / manual WORKING→DELIVERY | **Does not** copy pickup → delivery |

`ensureDefaultDeliveryDriver()` is now a **no-op** (call sites kept for compatibility).

---

## Scheduler eligibility (unchanged math, driver not required)

| Case | Rule |
|------|------|
| Future-day delivery | 08:00 Asia/Dubai on scheduled delivery date (after actual pickup) |
| Same-day | `picked_up_at` + 2 hours |
| No delivery driver | Still promotes |
| No invoice | Still promotes |
| No `picked_up_at` | Blocked |
| No `scheduled_delivery_at` | Blocked (legacy rows) |
| `auto_promote_held` | Blocked until Resume |
| Repeated runs | Idempotent (one `DELIVERY_READY`) |

Admin DELIVERY queue includes unassigned orders; badge **يحتاج تعيين سائق توصيل** / `Needs delivery driver assignment`.

### Scheduler evidence (TEST)

| Check | Result |
|-------|--------|
| `schedule:list` | `gh:promote-pickup-eligible` every 5 minutes |
| `storage/logs/scheduler.log` | Cron `schedule:run` succeeding (e.g. 13:51–13:54 queue workers) |
| Live command | `Promoted 0 order(s) to PICKUP; 0 order(s) to DELIVERY.` |

---

## Existing orders missing delivery schedules

| ID | Reference | Status | `delivery_driver_id` |
|----|-----------|--------|----------------------|
| 1 | `GH-ORD-000001` | `DELIVERY` | `9` |

- **Not** auto-rewritten. Status left as-is.
- Managers can still set `scheduled_delivery_at` via restricted edit; scheduler will evaluate if returned to WORKING and eligible.
- No financial rows touched.

---

## Tests and results

```text
php artisan test --filter=GeneralHouse
→ PASS — 365 passed (2094 assertions), 16.31s

vendor/bin/pint --dirty → passed

npm run build → passed (vite 6.4.3, ~4.2s)
```

Focused coverage includes: missing date/time validation (EN+AR), create without driver, explicit Unassigned clear, post-create assign, future-day 08:00, same-day +2h, promote without driver, hold/resume, idempotent scheduler, legacy NULL until scheduled, admin/driver status agreement, redirect to orders index.

---

## Browser verification evidence

| Check | Result |
|-------|--------|
| Scheduler / promote command | Verified on TEST CLI |
| Create form HTML (required delivery, Unassigned option) | Code + Blade updated |
| Authenticated AR/EN/mobile click-through | **Not performed** (no manager session in this run) |

---

## Remaining blockers / follow-ups

1. Owner may still want a real delivery appointment on `GH-ORD-000001` (currently DELIVERY with NULL schedule) — assign via edit if operationally needed; do not invent dates without approval.
2. Authenticated UI screenshot pass (AR/EN/375) recommended on TEST after login.
3. LIVE deploy not in scope.

### Acceptance summary

| Criterion | Result |
|-----------|--------|
| Create requires delivery date+time | **PASS** |
| Delivery driver optional / Unassigned sticks | **PASS** |
| Auto DELIVERY without driver or invoice | **PASS** |
| Management delivery queue shows needs-driver | **PASS** (tests + warning service) |
| No silent financial/history rewrite | **PASS** |
| Overall | **PASS** |

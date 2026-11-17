# GH Orders — Invoice-Independent Delivery, Scheduling, Audit Trail & Order List UX

**Environment:** TEST (`/home/d14098/generalsys.idigital-cs.com`)  
**Branch tip at implementation:** `release/generalsys-full-20261108`  
**Date:** 2026-11-15  
**LIVE:** not modified · **Deploy/commit/push:** not performed

---

## 0. Correction (2026-11-15) — Scheduled delivery is OPTIONAL

Owner correction applied on TEST only:

| Rule | Behavior after correction |
|------|---------------------------|
| Scheduled pickup | Still required on create/edit |
| Scheduled delivery | **Optional nullable appointment** (date **and** time together, or empty) |
| Missing delivery | **No** same-day assumption; **no** auto WORKING→DELIVERY via +2h / 08:00 |
| After confirmed pickup without delivery | Order stays WORKING; staff may schedule later or mark ready manually |
| When delivery is set | Same-day: +2h after actual pickup; future day: 08:00 Asia/Dubai on that date |
| Incomplete appointment | Date without time (or time without date) → validation error; never invent a time |
| Display | Lists / show / board: **Not scheduled / غير محدد** when null |
| Recalc on change | Restricted update may set/change appointment → `schedule_reevaluation` promote once (idempotent) |
| Manual | Manager `mark-delivery-ready` works without a scheduled delivery (pickup still mandatory) |
| Invoice | Still never mandatory for DELIVERY |

### Correction files
- `GhDeliveryEligibilityService` — NULL `scheduled_delivery_at` ⇒ not auto-eligible; `isEligibleForManualDelivery`
- `GhOrderService::parseScheduledDelivery` → `?Carbon`; `updateRestricted` accepts appointment + reeval
- Form: optional `datetime-local` (create + restricted edit)
- Logistics: WORKING hint + Mark ready for delivery button
- Route: `gh.orders.mark-delivery-ready`
- Migration: `2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at` (clears prior incorrect same-day backfill on TEST)
- Factory default: `scheduled_delivery_at = null`
- Lang EN/AR: `delivery_not_scheduled`, optional hints, manual flash/errors
- Tests expanded in `InvoiceIndependentDeliveryTest`

---

## 1. Existing behavior & blockers (before original change)

| Area | Prior behavior |
|------|----------------|
| WORKING → DELIVERY | Sole path: `GhOrderStateService::markDeliveryAfterInvoice()` required an ISSUED `gh_invoices` row |
| `confirmDelivery` | Rejected WORKING with “requires invoice state” |
| Scheduled delivery | No `scheduled_delivery_at`; board used `invoice_issued_at` as delivery schedule proxy |
| Scheduler | `gh:promote-pickup-eligible` every 5 minutes — LATER→PICKUP only |
| Order list default | Tab **Pickup**; sort by pickup window |
| Delivery driver | Optional on create; not auto-copied from pickup on confirm |

---

## 2. Files changed (high level)

### Schema / model
- `database/migrations/2026_11_15_135901_add_scheduled_delivery_at_to_gh_orders_table.php`
- `database/migrations/2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at.php` **(correction)**
- `app/Models/GeneralHouse/GhOrder.php` — cast `scheduled_delivery_at`
- `docs/DATABASE_SCHEMA.md` — optional appointment column
- `database/factories/GeneralHouse/GhOrderFactory.php` — null by default

### Domain services
- `app/Services/GeneralHouse/Orders/GhDeliveryEligibilityService.php`
- `app/Services/GeneralHouse/Orders/GhOrderStateService.php` — invoice decouple, promote, manual vs automatic eligibility
- `app/Services/GeneralHouse/Orders/GhOrderService.php` — nullable parse; restricted later scheduling
- `app/Services/GeneralHouse/Orders/GhOrderBoardService.php` — All tab; Not scheduled display
- Invoice services — draft/issue allowed post-pickup without forcing DELIVERY

### Console / schedule
- `gh:promote-pickup-eligible` every 5 minutes — LATER→PICKUP **and** WORKING→DELIVERY when appointment + timing allow

### HTTP / UI / i18n
- Form optional delivery datetime; show/list “Not scheduled”
- Logistics WORKING panel + mark-delivery-ready
- `lang/en/gh.php`, `lang/ar/gh.php`

### Tests
- `tests/Feature/GeneralHouse/InvoiceIndependentDeliveryTest.php` (pickup-only, later schedule, manual, auto paths)

---

## 3. Database migration

```text
php artisan migrate --force
→ 2026_11_15_135901_add_scheduled_delivery_at_to_gh_orders_table … DONE (earlier)
→ 2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at … DONE (correction)
```

- Column: `gh_orders.scheduled_delivery_at` (nullable TIMESTAMP, UTC)
- Index: `idx_gh_order_delivery_promote (status, picked_up_at, scheduled_delivery_at)`
- Correction data step: clears previously assumed same-day backfill so NULL means pickup-only

No financial tables altered. No historical invoice rows rewritten.

```text
DATABASE CHANGES: YES
SCHEMA MIGRATIONS CREATED: YES (forward correction migration only; column already existed)
DATA MIGRATION OR BACKFILL: YES (clear incorrect assumed delivery appointments)
PRODUCTION DEPLOYMENT PROCEDURE: BLOCKED (TEST only; no LIVE)
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

---

## 4. Lifecycle rules implemented

1. **Pickup confirmation mandatory** — only path into WORKING; idempotent.
2. **Scheduled delivery optional** — empty → stay WORKING after pickup; no auto promote.
3. **Same calendar day** (when appointment set): after `picked_up_at` wait **2 hours** → DELIVERY. Invoice not required.
4. **Future delivery day** (when appointment set): **08:00 Asia/Dubai** on appointment local date → DELIVERY if pickup confirmed.
5. **Later scheduling** — restricted edit may set/change appointment; eligibility recalculated once (`schedule_reevaluation`).
6. **Manual mark ready** — managers may WORKING→DELIVERY after pickup without an appointment.
7. **Invoice issuance** — records `INVOICE_ISSUED`; does not force DELIVERY; may reevaluate if appointment timing already allows.
8. **Delivery completion** — DELIVERY→COMPLETED; unpaid OK.
9. **Delivery driver** — null delivery on promote/pickup → copy pickup driver; never overwrite manual assignment.
10. **Cancelled/Completed** — never promoted.

---

## 5. Scheduler / cron verification (TEST)

| Check | Result |
|-------|--------|
| Crontab entry for GeneralSys TEST | `* * * * * cd /home/d14098/generalsys.idigital-cs.com && /usr/local/bin/php artisan schedule:run …` **present** |
| `php artisan schedule:list` | `gh:promote-pickup-eligible` every 5 minutes **registered** |
| Command behavior | Promotes LATER→PICKUP **and** WORKING→DELIVERY **only when** `scheduled_delivery_at` is set and timing passes |

---

## 6. Driver assignment behavior

| Moment | Behavior |
|--------|----------|
| Create/update with explicit delivery driver | Stored as assigned |
| Pickup confirm, delivery null | Auto-assign pickup driver |
| Scheduler / schedule reevaluation promote | Ensure-default; never clears/changes non-null delivery driver |
| Manual reassignment UI | Unchanged |

---

## 7. Invoice handling

| Topic | Behavior |
|-------|----------|
| Operational gates | Removed for DELIVERY transition |
| Late invoice | Allowed on WORKING / DELIVERY / COMPLETED after pickup |
| Visibility | List cards + order show: NONE / DRAFT / ISSUED |
| Auto-create on deliverable | **Not** introduced |

---

## 8. Order listing

| Rule | Implementation |
|------|----------------|
| Default tab | **All** |
| Default sort | `created_at DESC`, `id DESC` |
| Search | Reference, customer name, phone (`q`) |
| Delivery display | Appointment datetime, or **Not scheduled / غير محدد** |
| Card fields | Created, pickup, delivery appointment (or not scheduled), invoice badge, auto/manual DELIVERY badge |

Driver workspace still defaults to **Pickup** tab.

---

## 9. Audit events

| Event | When |
|-------|------|
| `DELIVERY_READY` | WORKING→DELIVERY; `trigger=automatic\|manual`; `source=scheduler\|manual\|pickup_reevaluation\|invoice_reevaluation\|schedule_reevaluation` |
| `INVOICE_ISSUED` | Invoice issue (does not imply DELIVERY) |

Repeated promote calls are idempotent (no duplicate `DELIVERY_READY`).

---

## 10. Automated tests & results (correction)

```text
php artisan migrate --force
→ 2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at … DONE

php artisan test --filter=InvoiceIndependentDeliveryTest
→ 12 passed (73 assertions)

php artisan test --filter='PaymentTest|DriverDeliveryPaymentTest|DriverWorkspaceTest'
→ 36 passed (203 assertions)

vendor/bin/pint --dirty → passed
php -l on touched services/controllers → clean
```

Correction coverage: pickup-only (no auto DELIVERY), later scheduling + single reevaluation, manual without schedule, same-day +2h, future-day 08:00, late pickup reeval, date-without-time rejected, null eligibility math, Not scheduled on list, invoice-independent paths preserved.

---

## 11. Unresolved / follow-ups

1. KPI docs may still mention `delivered_at − invoice_issued_at` timing; reporting formulas were not redesigned.
2. Cancel remains blocked once `invoice_issued_at` is set or status is DELIVERY/COMPLETED (financial safety preserved).
3. LIVE deploy of this correction is **not** authorized in this pass.

---

## 12. Final status

**PASS**

Optional scheduled delivery (nullable appointment, no same-day invent, no auto-promote when NULL, later scheduling, manual readiness, Not scheduled display) is implemented on TEST with green tests. Not production-ready until owner authorizes LIVE deploy.

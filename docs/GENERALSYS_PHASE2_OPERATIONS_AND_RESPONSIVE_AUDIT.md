# GeneralSys Phase 2 — Driver Status UX + Mobile Responsive Fixes

**Environment:** TEST (`/home/d14098/generalsys.idigital-cs.com`)  
**Date:** 2026-11-15  
**LIVE:** not modified · **Commit/push:** not performed  
**Scope note:** Optional delivery correction (prior PASS) was **not** reimplemented.

---

## Verdict

**PASS**

Driver order status colors + mobile responsive fixes remain PASS.  
**Smart order warnings** (missing invoice, completed unpaid/partial, overdue pickup/delivery) are implemented on TEST with green focused tests. No LIVE deploy, commit, push, or financial DB writes.

---

## 1. Root causes

### Task 1 — Status colors
- Driver pickup/delivery pages and task cards used Bootstrap `text-bg-primary` for **every** order status.
- Board cards only styled `WORKING` / `COMPLETED` specially; other statuses shared a default yellow badge.
- No shared status-color token for driver + board.

### Task 2 — Mobile responsiveness
| Symptom | Root cause |
|---------|------------|
| Numbers wrapping vertically in KPI cards | `.stat-value` used `overflow-wrap: anywhere` + `word-break: break-word` **and** forced `.text-nowrap { white-space: normal !important }`, defeating `<x-money class="text-nowrap">` |
| Overlapping icon/value/sparkline | Narrow `col-6` + fixed sparkline width (~3.25rem) + icon on ≤430px |
| Crowded mobile header | Large 2.75rem icon buttons + tall module tabs + generous padding on ≤575px |
| Board meta crowding / overflow | 4-column meta grid too tight under ~430px |
| Horizontal overflow risk | Missing `overflow-x: clip` on body at phone widths |

Business logic, order transitions, and financial records were **not** changed.

---

## 2. Status color mapping

| Status | Color | CSS class | EN label | AR label (lang) |
|--------|-------|-----------|----------|-----------------|
| LATER | Gray | `gh-status-badge--later` | Later | `gh.order_status.LATER` |
| PICKUP | Blue | `gh-status-badge--pickup` | Pickup | `gh.order_status.PICKUP` |
| WORKING | Amber | `gh-status-badge--working` | Working | `gh.order_status.WORKING` |
| DELIVERY | Purple | `gh-status-badge--delivery` | Delivery | `gh.order_status.DELIVERY` |
| COMPLETED | Green | `gh-status-badge--completed` | Completed | `gh.order_status.COMPLETED` |
| CANCELLED | Red | `gh-status-badge--cancelled` | Cancelled | `gh.order_status.CANCELLED` |

Soft fills + dark text for WCAG-friendly contrast on light UI; dark-theme variants included under `[data-bs-theme='dark']`.

Legend asset: `docs/assets/gh-status-color-legend.svg`

---

## 3. Files changed

### Status UX
- `resources/views/gh/partials/order-status-badge.blade.php` **(new)**
- `resources/views/gh/partials/order-card.blade.php`
- `resources/views/gh/driver/partials/task-card.blade.php`
- `resources/views/gh/driver/pickup.blade.php`
- `resources/views/gh/driver/delivery.blade.php`
- `resources/scss/_general-house.scss` — `.gh-status-badge*` + mobile board meta
- `tests/Feature/GeneralHouse/DriverWorkspaceTest.php` — badge assertions

### Responsive / shared KPI + header
- `resources/scss/_hr-pro.scss` — `.stat-card` number integrity; ≤430px KPI; ≤575px header compact; `overflow-x: clip`
- `public/build/*` — `npm run build` (Hostinger: assets built locally)

### Docs
- `docs/GENERALSYS_PHASE2_OPERATIONS_AND_RESPONSIVE_AUDIT.md` (this file)
- `docs/assets/gh-status-color-legend.svg`

---

## 4. Viewports tested

Automated / CSS media coverage targets:

| CSS px | Notes |
|--------|--------|
| 320 | Header compact + KPI sparkline hidden + board meta reflow |
| 360 | Same band (≤430) |
| 375 | Browser device metrics on login (RTL AR); header CSS applied |
| 390 | ≤430 KPI rules |
| 414 | ≤430 / ≤575 rules |
| 430 | Upper bound of phone KPI tightening |
| ≥576 / ≥1024 | Desktop header/stat rules preserved |

Cards/Tables (`ListViewMode`) preference untouched.

**Browser:** login page at 375×812 RTL (unauthenticated). Authenticated GH/Gross dashboard screenshots **not** captured this pass (no session credentials in agent). Owner can verify logged-in dashboards after refresh (built CSS already on TEST `public/build`).

---

## 5. Before / after (behavioral)

### Before
- All driver status badges looked “primary blue”.
- AED amounts in `x-stat-card` could stack digits vertically on phone.
- Header icon row felt oversized on small phones.

### After
- Status badges use the Gray/Blue/Amber/Purple/Green/Red map with AR+EN labels via `__('gh.order_status.*')`.
- Money/numeric KPI values stay single-line (`nowrap` + ellipsis if extreme).
- Sparklines hidden ≤430px; icons/padding reduced; header chrome tightened ≤575px.
- Board card meta stacks address/type on ≤430px to reduce clipping.

---

## 6. Test results

```text
npm run build → OK (app CSS includes gh-status-badge--*)

php artisan test --filter='DriverWorkspaceTest|DriverDeliveryPaymentTest|Gh94DriverServiceMenuTest'
→ 33 passed (226 assertions)

  includes: test_driver_views_render_status_color_badges
            (dashboard / pickup / delivery CSS classes + AR label render + partial matrix)
```

No order-state or invoice/payment logic changes in this task.

---

## 7. Remaining issues / follow-ups

1. **Authenticated visual QA** — owner should spot-check GH dashboard + Gross sales stats at 320–430 in AR/EN after login (agent could not authenticate).
2. Login page itself is a separate layout (not `hp-app-header`); header crowding fix applies to authenticated chrome.
3. Very long Arabic KPI titles still truncate via `.stat-label.text-truncate` (by design); subtitle may wrap.
4. Movement-log badges (`READY` / `FAILED`) remain Bootstrap semantic colors — those are **movement** states, not order statuses.

---

## 8. Smart order warnings (added same day)

### Behavior

| Warning | Tone | When | Audience |
|---------|------|------|----------|
| Please create an invoice / يرجى إنشاء فاتورة | Orange | No issued invoice (`invoice_issued_at` null and invoice not ISSUED) | Admin/Manager |
| Completed — Unpaid / مكتمل — غير مدفوع | Red | COMPLETED + ISSUED invoice + `remaining_due > 0` (fully unpaid) via `GhPaymentBalanceService` | Admin/Manager |
| Completed — Partially paid / مكتمل — مدفوع جزئياً | Red | COMPLETED + ISSUED + PARTIAL remaining | Admin/Manager |
| Overdue for pickup / متأخر عن الاستلام | Red | LATER/PICKUP, `pickup_starts_at` past, pickup not confirmed | Admin + Driver |
| Overdue for delivery / متأخر عن التسليم | Red | `scheduled_delivery_at` set and past, delivery not confirmed, not COMPLETED/CANCELLED | Admin + Driver |

Rules preserved:
- Status color badges unchanged; warnings are **independent** siblings.
- No invoice ⇒ orange missing-invoice only; **never** invent unpaid balance.
- No delivery appointment ⇒ no overdue-delivery flag.
- CANCELLED ⇒ no warnings.
- Does not block logistics; does not auto-issue invoices; does not alter ledger/payment rows.
- Asia/Dubai business comparisons via existing eligibility `now()` / UTC-stored timestamps.

### Files
- `app/Services/GeneralHouse/Orders/GhOrderWarningService.php` **(new)**
- `app/Services/GeneralHouse/Orders/GhOrderBoardService.php` — card `warnings` payload
- `app/Http/Controllers/GeneralHouse/OrderController.php` / `DriverWorkspaceController.php`
- `resources/views/gh/partials/order-warning-badges.blade.php` **(new)**
- Board card, order show, driver pickup/delivery
- `resources/scss/_general-house.scss` — `.gh-warning-badge--orange|red`
- `lang/en/gh.php`, `lang/ar/gh.php` — `gh.warnings.*`
- `tests/Feature/GeneralHouse/OrderWarningBadgesTest.php` **(new)**

### Warning test evidence

```text
php artisan test --filter=OrderWarningBadgesTest
→ 11 passed (40 assertions)

php artisan test --filter='OrderWarningBadgesTest|DriverWorkspaceTest|InvoiceIndependentDeliveryTest|PaymentTest'
→ 60 passed (331 assertions)

npm run build → OK (includes gh-warning-badge--)
vendor/bin/pint --dirty → passed
```

Covered: missing invoice, issued invoice, completed unpaid/partial/paid, unbilled completed (not unpaid), overdue pickup, overdue delivery, future appointment, missing delivery appointment, cancelled, multiple simultaneous warnings, driver vs admin audience, employee 403, Arabic labels.

---

## 9. Final status

**PASS** — TEST only; no LIVE deploy, commit, or push. Status-color / responsive work not reimplemented.

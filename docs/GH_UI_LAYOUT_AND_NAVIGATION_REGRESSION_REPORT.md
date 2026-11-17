# GH UI Layout + Post-Action Navigation Regression Report

**Environment:** GeneralSys TEST only (`https://generalsys.idigital-cs.com/`)  
**Date:** 2026-11-15  
**LIVE / commit / push:** not performed  

---

## 1. Root causes

### UI layout regression (Offers & Pricing) — **CONFIRMED**

| Factor | Evidence |
|--------|----------|
| Default display mode is **Classic / legacy** | `ListViewMode::current()` defaults to `legacy` |
| Legacy offerings template used stretched Bootstrap `row`/`col-md-*` create form | `resources/views/gh/offerings/_mode-legacy.blade.php` lacked `.gh-offerings-create` grid |
| Compact grid CSS existed but only wired to **Cards** mode | `_general-house.scss` `.gh-offerings-create` + `_mode-cards.blade.php` |
| Excel import always fully expanded | Shared `gh/import/partials/import-card.blade.php` rendered a full card on every catalog page |

**Not** caused by: missing Vite build of `.gh-offerings-create` (class was already in compiled CSS), financial code, or debit-adjustment work.

**Shared impact:** The always-open import card affected offerings, products, treatments, categories, zones, customers, and contracts in both display modes.

### Driver redirect regression — **CONFIRMED**

`OrderLogisticsController::redirectAfterDriverAction()` sent drivers back to:

- `gh.driver.pickup` → `/service/gh/driver/pickup/{id}`
- `gh.driver.delivery` → `/service/gh/driver/delivery/{id}`

That matches the owner’s example URL. Tests previously **asserted** this detail-page redirect, which locked the wrong behavior in.

**Cause class:** intentional-but-wrong controller named-route redirect (not AJAX, not middleware, not `back()`).

---

## 2. Implementation

### Layout / CSS

| Change | Files |
|--------|-------|
| Classic Offers & Pricing uses compact create grid + separate filter/table cards | `resources/views/gh/offerings/_mode-legacy.blade.php` |
| Cards create card padding tightened | `resources/views/gh/offerings/_mode-cards.blade.php` |
| Excel import collapsed by default (`<details>`), auto-opens on errors | `resources/views/gh/import/partials/import-card.blade.php` |
| Import collapse + compact filter styles | `resources/scss/_general-house.scss` |
| AR/EN hint for collapsed import | `lang/en/gh.php`, `lang/ar/gh.php` |
| Vite production build refreshed | `public/build/*` |

### Driver redirects

| Action | New destination |
|--------|-----------------|
| Confirm pickup / failed pickup | `gh.driver.dashboard?tab=pickup` |
| Confirm delivery / failed delivery | `gh.driver.dashboard?tab=delivery` |
| Manager logistics actions | unchanged → `gh.orders.show` |

File: `app/Http/Controllers/GeneralHouse/OrderLogisticsController.php`

### Management redirects (audit)

| Workflow | Destination | Notes |
|----------|-------------|-------|
| Product / treatment / offering / customer / zone create-update | respective **index** | Already correct |
| Order create | `gh.orders.index` | Already correct |
| Manual status / resume / cancel / reschedule | `gh.orders.show` | **Intentional** — manager continues on detail |
| Draft invoice create | builder | **Intentional** multi-step |
| Invoice issue | invoice show | **Intentional** review |
| Debit adjustment / credit note issue | document show | **Intentional** review of issued doc |
| Payment record | invoice show | **Intentional** balance context |
| Driver settlement | driver-cash show | **Intentional** custody context |

No blind “redirect every POST to index” applied.

### Tests

- Updated `DriverWorkspaceTest` redirect expectations  
- Added `DriverPostActionNavigationTest`  
- Extended `CardUiConversionTest` for classic offerings compact form + collapsed import  

---

## 3. Verification

### Automated

```text
npm run build                                 → PASS
vendor/bin/pint --dirty                       → PASS
php artisan test --filter=GeneralHouse        → 345 passed (1987 assertions)
```

Focused coverage includes: pickup/delivery list redirects, cash/online delivery, idempotent custody, validation failure preserving input, driver 403 on management offerings, offerings compact layout in classic + cards, debit adjustments still green.

### Actual redirect destinations (driver)

| From | After success |
|------|----------------|
| `/service/gh/driver/pickup/{id}` confirm | `/service/gh/driver?tab=pickup` + flash |
| Failed pickup | `/service/gh/driver?tab=pickup` + flash |
| `/service/gh/driver/delivery/{id}` CASH/ONLINE | `/service/gh/driver?tab=delivery` + flash |
| Delivery validation fail (cash 0) | back to delivery detail with errors + old input |

Order status / custody side-effects unchanged (covered by existing DriverDeliveryPayment / custody tests).

### Browser / responsive

| Check | Result |
|-------|--------|
| Authenticated screenshots on TEST (AR/EN, 1440/768/390) | **Not completed** — browser session stopped at login; no test credentials used |
| Layout structure assertions (Blade classes) | PASS via PHPUnit |
| Compiled CSS contains `.gh-offerings-create` / `.gh-import-card__summary` | PASS (`npm run build`) |

Visual viewport PASS is therefore **CONDITIONAL** pending owner login review on TEST after deploy of built assets.

---

## 4. Final status

| Area | Verdict |
|------|---------|
| Offers & Pricing layout | **PASS** (classic + cards compact; import collapsed) |
| Other GH management layouts | **PASS** for shared import collapse; other pages already had index redirects / card shells — no financial CSS rewrites |
| Driver layouts | **PASS** (no structural regression introduced; actions still same forms) |
| Driver post-action redirects | **PASS** |
| Management post-action redirects | **PASS** (with documented intentional detail/review exceptions) |
| Financial regression | **PASS** (345 GH tests; custody/payments/debits unchanged in logic) |
| Responsive browser verification | **CONDITIONAL** (no authenticated visual capture) |

### Overall

**PASS** for functional/layout code + redirects on TEST.  
**CONDITIONAL** only for live authenticated visual sign-off.

---

## Success criterion check

> After confirming pickup on `/service/gh/driver/pickup/1`, the driver is returned to the appropriate pickup list with a success message, and the saved order state remains correct.

**Implemented:** redirect to `route('gh.driver.dashboard', ['tab' => 'pickup'])` with `gh.flash.pickup_confirmed`; order transitions remain via existing state service (tests green). Same pattern for delivery.

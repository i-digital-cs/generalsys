# General House — Global Table Alignment & RTL/LTR UI Audit

**Date:** 2026-11-18  
**Scope:** TEST only (`/home/d14098/generalsys.idigital-cs.com`)  
**Constraint compliance:** No LIVE access · no commit/push/deploy · no financial calculation/schema/historical-record changes · GH-scoped CSS only (no global `_hr-pro.scss` / other-module changes)

Scratch evidence root: `/tmp/gh-table-audit/`  
Before shots: `/tmp/gh-table-audit/before/shots/`  
After shots: `/tmp/gh-table-audit/after/shots/`  
Paired report shots: `/tmp/gh-table-audit/report-shots/`  
Raw results: `before/results.json`, `after/results.json`  
Box measurements: `after/measurements.json`

---

## A. Root cause

**Primary (column “shift” in Arabic):**  
Global `.num-ltr` sets `direction: ltr`. Many GH tables put that class on the `<td>` / `<th>` itself. In an RTL page, `direction: ltr` on the cell flips the meaning of `text-align: start` to the **physical left**, while headers (without `direction: ltr`) keep `start` = **physical right**. Cell **boxes** still aligned (0 `box` issues); the **content anchor** inside the cell sat on the opposite edge from the header — looking like columns were horizontally offset.

BEFORE audit classification (936/936 AR anchor issues):

| Count | Pattern |
|------:|---------|
| 784 | `cellDir=ltr` + `num-ltr` on td · `headAlign=right` · head anchor right / cell anchor left |
| 152 | `cellDir=ltr` + `num-ltr` on td · `headAlign=start` · same left-vs-right content anchors |

EN LTR had **0** anchor issues (start already = left; `direction:ltr` on the cell does not flip it).

**Secondary (mid-reference wrapping):**  
Reference / number cells lacked reliable `white-space: nowrap`, so tokens like `GH-PAY-000007` wrapped mid-string (368 `refwrap` before). Order-history JSON payload used `text-break` and wrapped invoice numbers inside payload JSON.

**Tertiary (page-level horizontal overflow on narrow widths):**  
1. Flex ancestors default `min-width: auto`, so wide tables could expand the page.  
2. Absolute `.visually-hidden` labels on row action buttons used a containing block **outside** `.table-responsive`, escaping `overflow-x: auto` and inflating `documentElement.scrollWidth` (offerings/products/categories).  
3. Invoice print page had no responsive wrapper and no GH CSS (CDN Bootstrap only).

**Unrelated scratch 500 (fixed while auditing):**  
Driver board `boardForDriver` did not eager-load `pickupDriver` / `deliveryDriver`; under `APP_ENV=local` (`Model::preventLazyLoading`) `/service/gh/driver` returned 500. ACTIONS tab also called `loadMissing` on a `Support\Collection`.

**Not the cause:** sticky headers, `table-layout: fixed`, colspan mismatches, unequal forced column widths, or independent calculation in Blade.

---

## B. Affected pages

Audit covered **49 GH routes × 2 locales × 6 widths = 588 loads**, **552 table measurements**, **22 224 → 24 816 cells**.

Pages with BEFORE issues (representative):

| Area | Pages | BEFORE symptoms |
|------|-------|-----------------|
| Payments / CN / contracts / customers | `payments`, `credit_notes`, `contracts`, `contract_show`, `customers` | anchor + refwrap |
| Driver cash | `dc_outstanding`, `dc_collections`, `dc_ledger`, `dc_settlements`, `dc_settle_create`, `dc_adjustments`, `settlement_show`, `settlement_reversed` | anchor |
| Invoices / orders | `invoice_show`, `invoice_show_cn`, `order_show`, `order_show_cn`, `order_history` | anchor; history payload refwrap |
| Reports | `rpt_invoices`, `rpt_collections`, `rpt_outstanding`, `rpt_driver_cash` | heavy refwrap + anchor |
| Catalog | `offerings`, `products`, `categories`, `treatments`, `zones` | anchor; mobile page overflow |
| Print | `invoice_print` | page overflow @375/430 |
| Driver | `driver_dashboard` | HTTP 500 (lazy load) |

AFTER: **all of the above clean** (0 issues, 0 overflow, 200 OK including driver dashboard).

---

## C. Changes (exact files + reusable fix)

### Reusable GH-scoped table fix — `resources/scss/_general-house.scss`

Under `[data-app-module='service']` only:

1. **`td.num-ltr` / `th.num-ltr`:** `direction: inherit` + keep `unicode-bidi: isolate` + `white-space: nowrap` (restores header/body start-edge match; digits/refs still paint LTR).
2. **`a.num-ltr` / `a.hp-value` in tables:** `white-space: nowrap` (contract / invoice reference links).
3. **`.table-responsive`:** `position: relative` (contain absolute `.visually-hidden`), `min-width: 0`, `max-width: 100%`.
4. **`main.flex-grow-1`, `.card`, `.card-body`:** `min-width: 0` so wide tables scroll inside the container instead of expanding the page.

No changes to `_hr-pro.scss` or non-GH modules.

### Blade / print

| File | Change |
|------|--------|
| `resources/views/gh/invoices/print.blade.php` | Local mirror of num-ltr cell rules; wrap table in `.table-responsive`; move `num-ltr` off `<h1>` onto inner span |
| `resources/views/gh/orders/history.blade.php` | Payload JSON in `overflow-auto` + `text-nowrap` (no mid-token wrap) |

### Driver board (audit blocker, not financial logic)

| File | Change |
|------|--------|
| `app/Services/GeneralHouse/Orders/GhOrderBoardService.php` | Eager-load `pickupDriver`/`deliveryDriver`; ACTIONS path wraps Support collection in `EloquentCollection` before `loadMissing` |
| `tests/Feature/GeneralHouse/DriverWorkspaceTest.php` | Regression: all driver tabs under `Model::preventLazyLoading()` |

### Assets

`npm run build` → `public/build/assets/app-B049-pZJ.css` (LTR) + `app-BBBFSh8b.css` (RTL) + updated `manifest.json`.

---

## D. Browser evidence

### Aggregate (Playwright headless Chromium)

| Metric | BEFORE | AFTER |
|--------|-------:|------:|
| Page loads | 588 | 588 |
| Anchor mismatches | **936** | **0** |
| Ref wraps | **368** | **0** |
| Box / colcount / clip | 0 | 0 |
| Page overflow rows | 12 | **0** |
| Non-200 (driver_dashboard) | 12 | **0** |

### Content-edge measurement (AR payments @1280, after)

Every header/body cell pair: `dLeft = 0`, `dRight = 0`, `contentEdgeDelta = 0`.  
`td.num-ltr` computed `direction` = **`rtl`** (inherited), not `ltr`.

Example AFTER payments AR col 0: head `مرجع الدفع` / cell `GH-PAY-000007` — box delta 0 px, content edge delta 0 px.

### Screenshots (paired)

| Pair | Path |
|------|------|
| Payments AR 1280 before | `/tmp/gh-table-audit/report-shots/before-ar-1280-payments.png` |
| Payments AR 1280 after | `/tmp/gh-table-audit/report-shots/after-ar-1280-payments.png` |
| DC outstanding AR 1280 before/after | `report-shots/before-ar-1280-dc_outstanding.png` / `after-…` |
| Offerings AR 375 before/after | `report-shots/before-ar-375-offerings.png` / `after-…` |
| Payments EN 1280 before/after | `report-shots/before-en-1280-payments.png` / `after-…` |

Full matrix shots: 196 before + 196 after under `*/shots/`.

PASS is based on measured anchor/box/refwrap/overflow counters — **not** HTTP 200 alone.

---

## E. Responsive results (AR/EN × 6 widths)

Widths: **375, 430, 768, 1024, 1280, 1440**. Locales: **ar (RTL)** + **en (LTR)**. 49 pages each.

| Locale@width | Issues | Overflow pages | Bad status | Result |
|--------------|-------:|---------------:|-----------:|--------|
| ar@375 … ar@1440 | 0 | 0 | 0 | PASS ×6 |
| en@375 … en@1440 | 0 | 0 | 0 | PASS ×6 |

**Matrix: 12/12 PASS.**

Wide tables scroll inside `.table-responsive`; page-level `overflowX = 0` at all tested widths after the containment fix.

---

## F. Regression results

```text
Commands:
  npm run build
  php -l (touched PHP/Blade)
  vendor/bin/pint --dirty
  php artisan test --filter='DriverWorkspaceTest::test_driver_dashboard_tabs_eager_load_drivers_under_lazy_guard|DriverWorkspaceTest::test_driver_dashboard_shows_only_assigned_tasks|DriverWorkspaceTest::test_arabic_dashboard_renders|OrderBoardV2Test'
  php artisan test --filter='GeneralHouse'
  Playwright: node /tmp/gh-table-audit/audit.js after && node summarize.js after
  Playwright: node /tmp/gh-table-audit/measure.js

Automated tests:
  Driver workspace + OrderBoardV2: 12 passed
  GeneralHouse pack: 448 passed, 4 skipped, 1 failed
    FAIL DriverMovementLogTest::filter_by_type_and_chronological_sort
    → Pre-existing (reproduced with this change stashed); unrelated to table CSS / eager-load fix
  Browser table audit AFTER: 0 issues, 0 page overflow, 0 non-200

UI/UX (AR+EN, RTL+LTR, 375/430/768/1024/1280/1440): PASS (measured)

DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: BLOCKED (TEST only; not committed/deployed)
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS (no DB changes)
```

Financial totals, custody math, settlement allocation, payment status rules, routes (except rendering), and non-GH modules: **unchanged**.

---

## G. Remaining issues

1. **`DriverMovementLogTest` chronological assertion** — pre-existing FAIL (PICKUP vs DELIVERY order when timestamps tie). Out of scope for this UI audit; not introduced by these changes.
2. **Prefer inner `<span class="num-ltr">` for new GH markup** — CSS now safely repairs `td.num-ltr`; new code should still prefer the span pattern documented in `_general-house.scss`.
3. **Order history English hard-coded headers** (`occurred_at` / `recorded_at`) — pre-existing i18n debt; not an alignment defect.
4. **Not committed / not deployed** — owner must request commit + TEST deploy when ready (`public/build` CSS must ship with the SCSS change).

---

## End-of-step report

```text
Task ID: GH GLOBAL TABLE ALIGNMENT & RTL/LTR UI AUDIT
Commands: npm run build; playwright audit before/after; measure.js; php artisan test --filter=GeneralHouse; pint; php -l
Automated tests: PASS for this change’s regression (driver board + board V2); GH pack 448 pass / 1 pre-existing fail
UI/UX (AR+EN, RTL+LTR, 375/430/768/1024/1280/1440): PASS
Next task allowed: yes (owner) — commit/deploy only on request
DATABASE CHANGES: NO
```

# GH OPPO A85 Mobile Dashboard Responsive Fix

**Environment:** GeneralSys TEST only (`https://generalsys.idigital-cs.com/`)  
**Date:** 2026-11-17  
**LIVE / commit / push:** not performed  

---

## Final status (split)

| Layer | Result |
|-------|--------|
| **Code fix** | **PASS** |
| **Browser visual verification (emulated 320–412 / RTL+LTR)** | **PASS** |
| **Real OPPO A85 device** | **NOT VERIFIED** (no physical device in this run) |

Automated tests + `npm run build` alone are **not** claimed as UI PASS.

---

## 1. Confirmed root cause

Two cooperating defects produced “one digit per line” KPI values on narrow Android viewports:

1. **Grid:** GH dashboard KPI cells used Bootstrap `col-6` on all phone widths → two columns ≈ **~150–170 CSS px** per card on a ~360px OPPO-class phone (after main padding).
2. **Flex collapse:** `.stat-card-inner` is a horizontal flex row with a fixed icon and `.stat-body { flex: 1 1 0; min-width: 0 }`. That allows the body to shrink toward **~0**.
3. **Breakable value text:** Operational KPIs rendered plain text inside `.stat-value` **without** `white-space: nowrap` / `.num-ltr`. When the flex item width fell below one glyph (~8–12px), the engine wrapped **each digit onto its own line**.

Financial slots that already wrapped `<x-money>` in `.num-ltr` were partially protected by a prior patch, but the card width problem remained and plain `:value` KPIs stayed vulnerable.

Viewport meta was already correct (`width=device-width, initial-scale=1`). This was **not** a missing Vite build of an earlier incomplete fix alone — the previous CSS only set `overflow-wrap: normal` on `.stat-value` and nowrap on **children**, while leaving body `min-width: 0` and `col-6` intact.

---

## 2. Why previous responsive fixes failed

| Prior change | Why insufficient |
|--------------|------------------|
| `overflow-wrap: normal` on `.stat-value` | Without `white-space: nowrap`, a ~1ch-wide flex child still wraps character-by-character. |
| nowrap only on `.stat-value .num-ltr` | Operational KPIs used `:value="…"` **without** `.num-ltr`. |
| `@media (max-width: 430px)` icon shrink / hide sparkline | Did not widen columns; cards stayed `col-6`. |
| `min-width: 0` on columns | Helps overflow, but **enables** the digit-column collapse when combined with breakable text. |

---

## 3. Exact files changed

| File | Change |
|------|--------|
| `resources/scss/_hr-pro.scss` | Stat-card: `white-space: nowrap`, `word-break: keep-all`, body min-width floor, container queries, `.hp-kpi-grid` forces 1-col ≤575px; header chrome de-crowding |
| `resources/views/components/stat-card.blade.php` | Always wrap value/slot in `<span class="num-ltr text-nowrap">` |
| `resources/views/gh/dashboard/_mode-cards.blade.php` | `col-12 col-sm-6 col-md-4 col-xl-2` + `hp-kpi-grid` |
| `resources/views/gh/dashboard/_mode-legacy.blade.php` | same |
| `resources/views/gh/reports/pickup-performance.blade.php` | `col-12 col-sm-6 col-md-4` + `hp-kpi-grid` |
| `resources/views/gh/reports/delivery-performance.blade.php` | same |
| `resources/views/gh/reports/partials/filters.blade.php` | Filter fields `col-12 col-sm-6 col-md-2` |
| `resources/views/layouts/partials/navbar.blade.php` | Hide list-view toggle on phones; expose Tables/Cards in user menu |

**Migrations / backend / financial logic:** none.

---

## 4. Actual mobile grid/card dimensions (authenticated TEST browser + CDP)

Asset served after rebuild: `build/assets/app-DPeD3G6a.css` (RTL) / `app-Df8LU8jw.css` (LTR).

| Viewport | Sample `.stat-card` width | `.stat-value` `white-space` | Client rects for `12,345.67 AED` |
|----------|---------------------------|-----------------------------|----------------------------------|
| 320px | **264px** (1 column) | `nowrap` | **1** (single line) |
| 360px | **304px** (1 column) | `nowrap` | **1** |
| 360px EN LTR | **304px** | `nowrap` | **1** |

List-view toggle `display: none` at 360px (moved into profile menu). Header chrome measured ≈ full content width with hamburger + brand + notifications + profile.

---

## 5. Before / after screenshots

| Evidence | Notes |
|----------|--------|
| Owner OPPO screenshot (pre-fix) | Digits vertical; ultra-narrow cards |
| Emulated AR 360px after fix | Single-column KPIs; `12,345.67 AED` horizontal (`page-2026-11-17T12-24-39-078Z.png`) |
| Emulated EN 360px after fix | Same single-column behavior (`page-2026-11-17T12-26-03-322Z.png`) |

Real-device after photo: pending owner.

---

## 6. Responsive test matrix

| Width | KPI layout | Digits horizontal | Notes |
|-------|------------|-------------------|-------|
| 320 | 1-col | PASS (CDP) | |
| 360 | 1-col | PASS (CDP + screenshot) | OPPO A85-class CSS width |
| 375 | 1-col (rule ≤575) | PASS by same CSS | |
| 390 | 1-col | PASS by same CSS | |
| 412 | 1-col | PASS by same CSS | |
| 768 | 2–3 col (`sm`/`md`) | PASS (shared nowrap) | |
| 1440 | up to 6 col (`xl-2`) | Desktop preserved | |

---

## 7. Arabic RTL / English LTR

| Locale | Result |
|--------|--------|
| AR RTL dashboard | Authenticated; KPIs 1-col; nowrap |
| EN LTR dashboard | Authenticated after `/locale/en`; KPIs 1-col; nowrap; list-view entries in user menu |

---

## 8. Frontend build

```text
npm run build → PASS (vite 6.4.3, ~4.5s)
manifest: resources/scss/app.scss → assets/app-Df8LU8jw.css
          resources/scss/app.rtl.scss → assets/app-DPeD3G6a.css
```

### Safe client cache refresh (if an old CSS hash is stuck)

1. Confirm Network shows `app-Df8LU8jw.css` / `app-DPeD3G6a.css` (not older `app-Cv64EHXW` / `app-CVDqSpYy`).
2. Hard refresh: Chrome Android → tap URL → menu → **Reload without caching** / clear site data for `generalsys.idigital-cs.com` only.
3. Do **not** flush unrelated Hostinger sites or server-wide caches.

---

## 9. GeneralHouse regression

```text
php artisan test --filter=GeneralHouse
→ PASS — 365 passed (2094 assertions)

vendor/bin/pint --dirty → passed
```

No changes to order/invoice/payment/custody/settlement/scheduler logic.

---

## 10. Remaining limitations

1. **Physical OPPO A85** not available here — owner should re-check dashboard after hard refresh.
2. Screenshot tooling may letterbox around the emulated viewport; **CDP `getBoundingClientRect` / `getClientRects`** are the measurement source of truth.
3. Driver dashboard uses order cards (already separate GH board CSS), not the KPI `col-6` grid — covered by shared `.stat-card` rules where used (e.g. cash custody).

---

## Acceptance vs owner criteria

| Criterion | Code + emulated browser | Real OPPO |
|-----------|-------------------------|-----------|
| KPI numbers horizontal | **PASS** | Pending |
| Cards not narrow vertical strips | **PASS** (1-col ≤575) | Pending |
| No overlapping KPI/chart | **PASS** | Pending |
| Header usable | **PASS** (toggle moved; brand clamped) | Pending |
| No unintended horizontal scroll | **PASS** (`overflow-x: clip` retained) | Pending |
| Desktop intact | **PASS** (`xl-2` unchanged) | Pending |

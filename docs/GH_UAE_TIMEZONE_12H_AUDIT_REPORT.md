# General House — UAE Timezone & 12-Hour UI Audit

**Date:** 2026-11-19  
**Scope:** TEST only — General House Laundry module  
**Constraint compliance:** No LIVE · no commit/push/deploy · no migrations · no `app.timezone` change · no HR/Attendance/Payroll/Gross edits · no financial calculation changes · UTC storage preserved

---

## A. Timezone inventory

### Platform contracts (unchanged)

| Layer | Zone | Notes |
|-------|------|-------|
| `config('app.timezone')` | **Africa/Cairo** | Attendance / Payroll / Gross / Laravel defaults |
| `config('general_house.timezone')` | **Asia/Dubai** (`GH_TIMEZONE`) | GH business zone only |
| `App\Casts\GhUtcDateTime` | Store/read **UTC** | Ops instants independent of app TZ |
| Form `datetime-local` | Timezone-**naive** string | Server parses as GH/order zone wall-clock |

### Classification key

| Class | Meaning |
|-------|---------|
| **VERIFIED_DUBAI_OPS** | `GhUtcDateTime` (or equivalent UTC ops) → safe Dubai 12h + UAE badge |
| **DATE_ONLY** | Calendar date; left as `Y-m-d` (no 12h / no badge) |
| **INPUT_FORM** | Naive datetime-local; interpreted as Dubai/order zone on save |
| **UNVERIFIED** | App-timezone cast or ambiguous → 12h optional, **no UAE badge** |
| **LEFT_APP_TZ** | Intentionally stays `app.timezone` (not labeled UAE) |

### Inventory (presentation)

| Area | Field / UI | Storage / source | Class | BEFORE | AFTER |
|------|------------|------------------|-------|--------|-------|
| Orders show | `pickup_starts_at`, `picked_up_at`, `scheduled_delivery_at` | GhUtcDateTime | VERIFIED | 24h + raw tz text | Dubai 12h + 🇦🇪 badge |
| Orders show | `created_at` | Laravel datetime | UNVERIFIED | 24h via displayTz | Dubai 12h, **no badge** |
| Orders show/history | event `occurred_at` / `recorded_at` | GhUtcDateTime | VERIFIED | 24h / toDateTimeString | Dubai 12h + badge |
| Order form / logistics / driver confirm | `scheduled_pickup_at`, `scheduled_delivery_at`, `occurred_at` | INPUT → parse as GH/order zone → UTC | INPUT_FORM | weak hint | `<x-gh.uae-tz-notice>` |
| Order board cards | schedule/actual times | GhUtcDateTime via presenter | VERIFIED | `H:i` 24h | 12h + badge on schedule cell |
| Dashboard / reports orders | pickup window, created | mix | VERIFIED / UNVERIFIED | 24h | 12h; created no badge |
| Invoices show / payment panel | `issued_at`, payment at | GhUtcDateTime | VERIFIED | 24h | 12h + badge |
| Invoice lists | issued date | date portion | DATE_ONLY | `Y-m-d` | **unchanged** |
| Driver cash / settlements / ledger | `collected_at`, `settled_at`, `reversed_at`, `allocated_at`, timeline | GhUtcDateTime | VERIFIED | 24h + Asia/Dubai note | 12h + UAE list note / badge |
| CN / refund / FC / debit events | `occurred_at` | GhUtcDateTime | VERIFIED | 24h | 12h + badge |
| Settings VAT audit | `recorded_at` | app datetime | LEFT_APP_TZ | app TZ 24h | **unchanged** (no UAE label) |
| App download `built_at` | publish stamp | app TZ | LEFT_APP_TZ | app TZ | **unchanged** |
| CSV driver-cash export | `collected_at` | UTC ISO in export | — | UTC `Y-m-d H:i:s` | **unchanged** (machine export stays UTC) |
| Contract `effective_from` / `to` | dates | date columns | DATE_ONLY | date | **unchanged** |

Policy reference: `docs/general-house/TIMEZONE.md` (updated display row).

---

## B. Changes

### New (GH-only)

| File | Role |
|------|------|
| `app/Support/GeneralHouse/GhDateTimePresenter.php` | Dubai projection, 12h EN AM/PM / AR ص/م, badge + selection copy |
| `resources/views/components/gh/dubai-time.blade.php` | Reusable display + optional UAE badge |
| `resources/views/components/gh/uae-tz-notice.blade.php` | Non-blocking selection notice |
| `tests/Unit/GeneralHouse/GhDateTimePresenterTest.php` | Unit coverage |
| `tests/Feature/GeneralHouse/GhUaeTimezonePresentationTest.php` | Order show / create / board HTML |

### Updated

- `resources/scss/_general-house.scss` — `.gh-dubai-time` / `.gh-uae-badge` (scoped under `[data-app-module='service']`)
- `lang/en/gh.php`, `lang/ar/gh.php` — `gh.time.*` + clearer custody/order TZ copy
- `GhOrderBoardService::presentCard` — Dubai 12h parts via presenter
- GH Blades: orders, driver, driver-cash, invoices, reports, dashboard, CN/refund/FC/debit
- `tests/Feature/GeneralHouse/DriverCashSettlementHttpTest` — expects `1:30 AM` + badge
- Built assets: `public/build/assets/app-DeiwHKhz.css` (LTR), `app-BoJK5h7w.css` (RTL)

### Explicitly not changed

- `config/app.php` / `APP_TIMEZONE`
- HR, Attendance, Payroll, Gross modules
- Shared non-GH date helpers
- Migrations / historical rows / financial math
- CSV UTC export strings

---

## C. Behaviour guarantees

1. **Display:** Verified ops clocks → Asia/Dubai, 12-hour, UAE badge.  
2. **Input:** Naive `datetime-local` still parsed as GH/order wall-clock (Dubai for new orders) regardless of browser TZ (Cairo/UTC/Dubai). Notice explains this.  
3. **Storage:** Still UTC via `GhUtcDateTime` / existing services.  
4. **Unverified:** No UAE badge (order `created_at`, settings audit, app download).  
5. **Date-only:** Invoice list dates etc. remain `Y-m-d`.

---

## D. Test results

```text
Commands:
  vendor/bin/pint --dirty
  npm run build
  php artisan test --filter='GhDateTimePresenterTest|GhUaeTimezonePresentationTest|OrderBoardV2Test|ScheduledPickupTest|DriverCashSettlementHttpTest|TimezoneIntegrityTest'

Results: 39 passed (363 assertions)

Covered scenarios:
  - EN 12h + 🇦🇪 UAE on order show / board / settlement show
  - AR selection notice + 🇦🇪 الإمارات on order create
  - app.timezone remains Africa/Cairo after GH formatting
  - Existing pickup parse / eligibility / scheduler integrity still green
```

Manual browser matrix (Cairo / Dubai / UTC browsers) recommended on TEST UI before deploy; automated tests lock presenter + key HTML contracts.

---

## E. Unresolved risks

1. **Legacy `gh_orders.timezone = Africa/Cairo` rows:** Display now always projects verified instants to **Asia/Dubai** (correct UAE wall clock for a true UTC instant). Order column may still say Cairo historically — badge means display zone, not the stored column string.  
2. **`created_at` / Laravel timestamps:** Still not `GhUtcDateTime`; shown without UAE badge. Residual ambiguity from pre-repair eras remains (documented in TIMEZONE.md).  
3. **`datetime-local` UX:** Browsers always paint the control in a locale-specific way; we cannot force the widget chrome to “Dubai”, only document interpretation + parse server-side.  
4. **CSV exports:** Left as UTC ISO for reconciliation tooling; human UI is 12h Dubai.  
5. **Explore agent inventory** was used as a cross-check while implementing; this report is the SSOT for owner review.

---

## F. Board UI regression (post-UAE UI) — fixed 2026-11-19

### Owner finding

Arabic RTL order board cards after the UAE 12h UI showed crowded / overlapping content: UAE indicators too wide, redundant timezone/date cues, squeezed date+time+icons, inconsistent badge/ref wrapping.

### Root cause (DOM, not scheduling)

| Signal | Finding |
|--------|---------|
| Real duplicate | **No** second appointment timestamp. Board was injecting a compact `gh-uae-badge` **inline** inside the old 4-column `meta--4` time cell (created date \| time+badge \| kind \| type). |
| Apparent duplicate | Created-at short date (`18/11`) sat beside schedule time in the same dense row → looked like two clocks / two dates for one appointment. |
| Crowding | Inline badge + 4 equal-ish cells left no room for RTL wrapping; badges/ref competed for horizontal space. |
| Old + new formatter | Not both rendering the same stamp. Only the board card path doubled visual TZ chrome by packing badge into `meta--4`. |

### Fix (presentation only)

- `resources/views/gh/partials/order-card.blade.php` — dedicated appointment block: `Kind: dd/mm — h:mm AM/PM` + **one** `gh-uae-badge--board` (flag span + `UAE Time` / `توقيت الإمارات`); quiet `meta--2` for created + type.
- Labels: EN `Pickup:` / AR `موعد الاستلام:` (`gh.board.appointment_*`).
- `resources/scss/_general-house.scss` — appointment stack, `meta--2`, board badge; emoji font only on `.gh-uae-flag` (not on Arabic label text).
- Tests: `OrderBoardV2Test` + `GhUaeTimezonePresentationTest` assert one board badge / appointment markers.

**Unchanged:** order statuses, pickup/delivery scheduling, DB timestamps, financial math, non-GH modules, UTC storage, `app.timezone`.

### Suggested vs shipped appointment copy

| Locale | Shipped |
|--------|---------|
| EN | `Pickup: 18/11 — 7:07 PM` + `🇦🇪 UAE Time` |
| AR | `موعد الاستلام: 18/11 — 7:07 م` + `🇦🇪 توقيت الإمارات` |

(12h uses `g:i` — `7:07` not zero-padded `07:07`; same presenter contract as other GH screens.)

### Evidence

Folder: `docs/evidence/gh-board-uae-regression/`

| File | Role |
|------|------|
| `before-card-ar-1280.png` / `before-ar-1280-board-pickup.png` | Recreated regression: `meta--4` + inline UAE badge |
| `after-card-ar-1280.png` / `after-ar-1280-board-pickup.png` | Fixed appointment hierarchy (AR RTL) |
| `after-card-en-1280.png` / `after-en-1280-board-pickup.png` | Fixed (EN LTR) |
| `ar-375-board-pickup.png`, `en-768-board-pickup.png`, … | Width matrix screenshots |
| `results.json` | Playwright DOM metrics |

### DOM / measurement gate (scratch UI @ 127.0.0.1:8766)

Widths: **375, 430, 768, 1024, 1280, 1440** × **ar RTL + en LTR** = **12** viewports.

```text
pages: 12
problemPages: 0
per card (first 3): boardBadgeCount === 1
overlaps (bounding-box intersection >1px, non-nested): []
clippedBadges: 0
pageOverflow (scrollWidth - innerWidth): 0
appointment sample AR: "18/11 — 7:07 م" + "توقيت الإمارات"
appointment sample EN: "18/11 — 7:07 PM" + "UAE Time"
```

Note: headless Chromium may not paint the 🇦🇪 color flag glyph; DOM still contains one flag + one label. Desktop browsers with emoji fonts show the flag.

### Regression test run

```text
php artisan test --filter='GhDateTimePresenterTest|GhUaeTimezonePresentationTest|OrderBoardV2Test|ScheduledPickupTest|TimezoneIntegrityTest'
Results: 29 passed (195 assertions)
npm run build — OK (LTR/RTL CSS under public/build)
```

---

## G. End-of-step

```text
Task ID: GH UAE TIMEZONE + ORDER BOARD UI REGRESSION FIX
Automated tests: PASS (29 targeted / 195 assertions)
UI/UX (AR+EN, RTL+LTR, 375–1440): PASS (DOM gate + screenshots)
DATABASE CHANGES: NO
Next: owner visual review — no commit/push/deploy until approved
```

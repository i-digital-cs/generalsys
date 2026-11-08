# Android status-bar insets + global module switcher (TEST)

**Date:** 2026-11-08 (revised after owner UAT FAIL on build 4)  
**TEST only** — no LIVE deploy / push / merge.

**Device status-bar clearance:** **UNVERIFIED** — see `ANDROID_HEADER_UAT_FAILURE_INVESTIGATION.md`.

## Issue 1 — Status bar overlap

**Root cause (confirmed):**

1. Cap `BridgeActivity` forces Cap’s `AppTheme.NoActionBar` — app-theme `windowOptOutEdgeToEdgeEnforcement` does not stick.
2. `setDecorFitsSystemWindows(true)` alone did not pad the Cap WebView container (owner UAT FAIL on 1.0.3 / code 4).
3. Cap StatusBar JSON `overlaysWebView: false` is **not** auto-applied by the plugin `load()`.

**Fix (exactly one native strategy — no CSS safe-area):**

- `MainActivity.applyWebViewSystemInsets()`
- `WindowCompat.setDecorFitsSystemWindows(window, false)`
- Transparent system bars + light status/nav icons
- `ViewCompat.setOnApplyWindowInsetsListener` on WebView **parent** → padding = `systemBars | displayCutout`
- Parent background `#79CAF9`; WebView padding forced to 0
- Re-request insets on `onResume`
- Capacitor StatusBar config kept as documentation intent only (`overlaysWebView: false`)

## Issue 2 — Global module switcher / responsive header

Shared navbar (`layouts/partials/navbar.blade.php` + `_hr-pro.scss`):

- Mobile (`<768`) / tablet (`768–1023`): two-row chrome + scrollable modules (48px tabs)
- Desktop (`≥1024`): **one compact row** — brand | modules | actions (`flex-wrap: nowrap`; modules `width: auto`)
- Desktop regression (2026-11-08): Bootstrap `.navbar > .container-fluid { flex-wrap: wrap }` + modules `width:100%` stacked brand/modules/actions into ~3 tall rows — fixed without APK rebuild
- Destinations / `AppModule::navItems()` unchanged

## TEST APK (current)

| Field | Value |
|-------|-------|
| versionName / versionCode | **1.0.5** / **6** |
| SHA-256 | `f3cdb0b573819f8ae94044add87a2ecc856b1eae6f05719404d041c56a561480` |
| applicationId | `net.genral.generalhouse.test` |
| File | `mobile/dist/test/GeneralHouse-TEST-debug.apk` |
| Download | `https://generalsys.idigital-cs.com/app/download/apk` |
| Host | `https://generalsys.idigital-cs.com/login` |

## Tests

```text
php artisan test --filter=GlobalHeaderModuleSwitcherTest
→ PASS (2 tests)
```

## Verification

| Check | Result |
|-------|--------|
| Published APK contains `applyWebViewSystemInsets` | YES (`classes4.dex`) |
| Dist ↔ storage ↔ policy SHA | MATCH |
| Mobile web header (390px) | Redesign live (browser only) |
| Android emulator / device with status bar | **UNAVAILABLE → UNVERIFIED** |

Do **not** claim status-bar clearance PASS until owner/device UAT of build **5**.

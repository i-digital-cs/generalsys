# Android header UAT failure — root-cause investigation (TEST)

**Date:** 2026-11-08  
**Verdict for device status-bar clearance:** **UNVERIFIED** (no emulator/device on this host)  
**Do not treat browser screenshots as Android PASS.**

---

## Phase 1 — Installed / published build verification

| Check | Result |
|-------|--------|
| Dist APK aapt | `net.genral.generalhouse.test` **versionCode=5** versionName=**1.0.4** targetSdk=35 |
| Dist SHA-256 | `1852246c4b0016aa4f71802440a1826d16d1c5a4df2f053a678297eacb7fe292` |
| `storage/app/private/gh-android/current.apk` SHA | **Matches** dist |
| Manifest / DB policy | version_code **5**, version_name **1.0.4**, PUBLISHED |
| Cap `server.url` inside APK | `https://generalsys.idigital-cs.com/login` |
| Service worker | **None** in app assets |
| Remote web assets | Cap loads **remote Laravel**; header CSS/HTML updates without APK reinstall; **native insets require APK reinstall** |

**Previous UAT APK (1.0.3 / code 4)** did contain `applySystemBarPolicy` + `setDecorFitsSystemWindows`, but that strategy was insufficient (see Phase 2).

### How the owner confirms build 5

1. Install from `https://generalsys.idigital-cs.com/app/download/apk` (authenticated).
2. Android: Settings → Apps → GNRL Operations TEST → App info / version → **1.0.4 (5)**.
3. Or `adb shell dumpsys package net.genral.generalhouse.test | grep version`.

---

## Phase 2 — Confirmed native root cause

1. **Capacitor `BridgeActivity` forces Cap’s theme**  
   `setTheme(com.getcapacitor.android.R.style.AppTheme_NoActionBar)` — Cap’s theme has **no** edge-to-edge opt-out. Our `values-v35` `windowOptOutEdgeToEdgeEnforcement` on **app** `AppTheme.NoActionBar` does **not** apply to the Cap theme actually used after splash.

2. **APK contains `androidx.activity.EdgeToEdge`**  
   Edge-to-edge helpers remain in the dependency graph; `setDecorFitsSystemWindows(true)` alone does **not** guarantee the Cap `CoordinatorLayout` / `CapacitorWebView` receives reserved system-bar space.

3. **Cap StatusBar JSON `overlaysWebView: false` is not auto-applied**  
   `@capacitor/status-bar` `load()` only constructs the helper; it does **not** read config and call `setOverlaysWebView(false)`. Remote Laravel pages also never call Cap JS StatusBar APIs.

4. **Previous “PASS” was overstated**  
   Validation used mobile **browser** screenshots. Browser has no Android status bar. Native clearance was **not** device-proven.

### New native strategy (exactly one path)

In `MainActivity.applyWebViewSystemInsets()`:

- `WindowCompat.setDecorFitsSystemWindows(window, false)` (edge-to-edge).
- Transparent system bar colors + light status/nav icons.
- `ViewCompat.setOnApplyWindowInsetsListener` on the **WebView parent** (Cap bridge root).
- Padding = `systemBars | displayCutout`.
- Parent background `#79CAF9` (inset strip under status icons).
- Child WebView padding forced to **0** (no double inset).
- **No** Laravel CSS `env(safe-area-inset-*)`.
- Re-request insets on `onResume` (splash / EdgeToEdge churn).

Bytecode evidence in published APK 5: `applyWebViewSystemInsets`, `WindowInsetsCompat`, `setOnApplyWindowInsetsListener`, `#79CAF9` — **FOUND**.

---

## Phase 3 — Global mobile header redesign (web)

Shared navbar (not GH-only):

- Mobile row 1: menu · **GNRL Operations TEST** identity · notifications · user.
- Theme/language moved into the user menu on small screens (less crowding).
- Mobile row 2: horizontally scrollable modules, **min-height 48px**, no shrink-to-fit.
- Desktop: company `APP_NAME` brand; modules mid-row via `display:contents` chrome flatten.
- Destinations still `AppModule::navItems()` / existing routes.

Automated: `GlobalHeaderModuleSwitcherTest` **PASS**.

---

## Phase 4 — Android runtime verification

| Requirement | Status |
|-------------|--------|
| Emulator / physical device | **Unavailable** (no AVD, no system image install listed for emulator use, no `/dev/kvm`, `adb devices` empty) |
| Screenshots with status bar + header + modules + content + nav bar | **Not captured** |
| Android 15/16 device UAT | **Not run** |

→ Native inset fix status: **UNVERIFIED** (implemented + packaged; not owner/device confirmed).

Web-only 390px screenshot of the redesigned header is supporting evidence for **layout**, not status-bar clearance.

---

## Phase 5 — Published TEST APK

| Field | Value |
|-------|--------|
| versionName / versionCode | **1.0.4** / **5** |
| applicationId | `net.genral.generalhouse.test` |
| SHA-256 | `1852246c4b0016aa4f71802440a1826d16d1c5a4df2f053a678297eacb7fe292` |
| File | `mobile/dist/test/GeneralHouse-TEST-debug.apk` |
| Download | `https://generalsys.idigital-cs.com/app/download/apk` |
| Host | `https://generalsys.idigital-cs.com/login` |

---

## Changed files (this investigation + fix)

- `mobile/android/app/src/main/java/net/genral/generalhouse/MainActivity.java` — WindowInsetsCompat strategy
- `mobile/android/app/build.gradle` (+ flavors) — versionCode 5 / 1.0.4
- `resources/views/layouts/partials/navbar.blade.php` — chrome + modules
- `resources/scss/_hr-pro.scss` — mobile/desktop header CSS
- `resources/js/app.js` — (existing) scroll active module into view
- `lang/en/nav.php`, `lang/ar/nav.php` — GNRL identity strings
- `tests/Feature/GlobalHeaderModuleSwitcherTest.php`
- Docs: this file

---

## Remaining limitations

- **Status-bar clearance is UNVERIFIED on Android** until owner/device UAT of build **5**.
- No Android 15/16 emulator on this server to capture status-bar screenshots.
- Remote WebView: web header updates live; native inset fix needs APK **5** installed (uninstall old build if side-loaded over install fails).
- Bottom inset strip uses brand blue parent background (acceptable tradeoff for single-strategy padding).

# GNRL Operations — Capacitor Android (TEST + LIVE)

**Scope:** Full GeneralSys mobile shell (all modules / users / permissions) — not General House-only. See `docs/general-house/GNRL_OPERATIONS_GLOBAL_SCOPE.md`.

**Status:** TEST debug APK **1.0.5 (versionCode 6)** built and published on TEST (WindowInsetsCompat + header polish + native mandatory Dialog). Device UAT for insets/mandatory Dialog = **UNVERIFIED** (no emulator/KVM). LIVE configuration + unsigned artifact prepared.  
**LIVE APK:** `UNVERIFIED` — **NOT APPROVED FOR PRODUCTION** until the owner authorizes release on `https://system.genral.net/`.

Inset / header: `ANDROID_HEADER_AND_MODULE_SWITCHER.md` · Mandatory updates: `ANDROID_MANDATORY_UPDATE_FIX.md`.

Stack: **Capacitor 6** (Java 17). Android flavors: `sandbox` = TEST, `production` = LIVE (AGP forbids flavor names starting with `test`).

## Package layout

| Path | Purpose |
|------|---------|
| `mobile/` | Capacitor project (does not affect Laravel Hostinger deploy) |
| `mobile/config/capacitor.test.json` | TEST server + appId |
| `mobile/config/capacitor.live.json` | LIVE server + appId |
| `mobile/android/` | Native Android project with `sandbox` / `production` product flavors |
| `mobile/dist/test/` | TEST APK + SHA-256 |
| `mobile/dist/live/` | LIVE UNVERIFIED APK + SHA-256 (optional artifact) |
| `mobile/branding/` | Official GNRL logo/icon sources |

## Identifiers

| Build | App name | applicationId | Server |
|-------|----------|---------------|--------|
| TEST | GNRL Operations TEST | `net.genral.generalhouse.test` | `https://generalsys.idigital-cs.com/` |
| LIVE | GNRL Operations | `net.genral.generalhouse` | `https://system.genral.net/` |

Branding sources: `mobile/branding/logo.png` + `mobile/branding/icon.png`. Regenerate launcher/splash with `npm run brand:assets` (runs automatically in TEST/LIVE build scripts).

Both can be installed side-by-side. LIVE config **must not** contain the TEST hostname (enforced by `apply-env.mjs`, build scripts, and `MainActivity` host guard).

## Prerequisites (this host)

```bash
export JAVA_HOME=/home/d14098/jdk
export ANDROID_HOME=/home/d14098/Android/Sdk
export ANDROID_SDK_ROOT=$ANDROID_HOME
export PATH="$JAVA_HOME/bin:$PATH"
```

## Build TEST debug APK

```bash
export JAVA_HOME=/home/d14098/jdk
export ANDROID_HOME=/home/d14098/Android/Sdk
export ANDROID_SDK_ROOT=$ANDROID_HOME
export GRADLE_USER_HOME=/home/d14098/.gradle
export PATH="$JAVA_HOME/bin:$PATH"

cd mobile
npm install
npm run build:test:debug
```

### Latest TEST artifact (this machine)

| Field | Value |
|-------|-------|
| Path | `mobile/dist/test/GeneralHouse-TEST-debug.apk` |
| applicationId | `net.genral.generalhouse.test` |
| App name | GNRL Operations TEST |
| versionName / versionCode | `1.0.5` / `6` |
| Server | `https://generalsys.idigital-cs.com/login` |
| SHA-256 | `f3cdb0b573819f8ae94044add87a2ecc856b1eae6f05719404d041c56a561480` |
| Built | 2026-11-08T11:40:57Z |
| Branding | Official GNRL logo launcher + black splash |

Also: `mobile/dist/test/GeneralHouse-TEST-debug.sha256.txt`

## Prepare / build LIVE (unsigned, unverified)

Does **not** call or modify LIVE. Only embeds `https://system.genral.net/login` in the Capacitor config.

```bash
cd mobile
npm run build:live:unsigned
```

Artifact label: **UNVERIFIED_NOT_APPROVED_FOR_PRODUCTION**

### Latest LIVE artifact (UNVERIFIED — not production)

| Field | Value |
|-------|-------|
| Path | `mobile/dist/live/GeneralHouse-LIVE-UNVERIFIED-unsigned.apk` |
| applicationId | `net.genral.generalhouse` |
| App name | GNRL Operations |
| Server | `https://system.genral.net/login` |
| Status | **UNVERIFIED / NOT APPROVED FOR PRODUCTION** |

Embedded config confirmed: LIVE APK has `system.genral.net` only (no TEST hostname).

### Production release steps (owner-authorized later)

1. Verify login + GH module on LIVE in a browser.
2. Sign the LIVE APK/AAB with the official keystore (not stored in this repo).
3. Owner UAT on a device against LIVE.
4. Explicit approval to distribute.

## Security constraints

- HTTPS only (`usesCleartextTraffic=false`, network security config).
- No embedded passwords / API keys.
- Cookies/CSRF/sessions: existing Laravel web stack inside WebView.
- Host mismatch between flavor and `capacitor.config.json` → app exits.

## Functional model

GNRL Operations is the **full GeneralSys** mobile shell — not General House-only. Capacitor loads the existing Laravel HTTPS UI (global login → role-based landing → all authorized modules). `server.allowNavigation` is host-scoped (TEST/LIVE isolation), not a GH route allowlist. Authorization remains server-side (roles/policies). Hardware back uses WebView history.

## Known limitations

- Not a native rewrite — network required.
- LIVE readiness requires LIVE verification (not done by this TEST-only work).
- Push notifications / offline cache not in scope.
- File upload / PDF download rely on WebView + DownloadManager; large uploads need device testing.
- Native update overlay UX verified by unit/API tests + APK class presence; full device install UAT not run on this host unless an emulator/device is available.

## In-app APK download + version management

Every authenticated GeneralSys user sees **Download GNRL Operations** in a **bottom footer** of the **global** sidebar (HR / Service / Product). Android links directly to the protected APK endpoint. iOS shows an unavailable note until an approved App Store / TestFlight URL is published. Version administration lives under global Manager settings (not the GH module menu).

| Item | Value |
|------|-------|
| APK endpoint | `/app/download/apk` (`app.download.file`) |
| Download page (optional) | `/app/download` (`app.download`) |
| GH URL compat | `/service/gh/app/download*` → redirects to global routes |
| Version admin | `/admin/app/versions` (`admin.app.versions`) — Manager only |
| Settings entry | Admin Settings → App Version Management |
| Version check API | `GET /app/version-check` (`app.version-check`) — trusted server channel |
| Compat version-check | `GET /service/gh/app/version-check` (`gh.app.version-check`) — same handler |
| Artifact store | `storage/app/private/gh-android/current.apk` (not web-public) |
| Manifest SSOT | `storage/app/private/gh-android/current.manifest.json` |
| Publish command | `php artisan gh:publish-android-apk mobile/dist/test/GeneralHouse-TEST-debug.apk --channel=test` |

Publishing syncs Android `latest_build` / `latest_version_name` from the APK `versionCode` / `versionName` and **does not** raise `minimum_build` or enable mandatory updates.

### Capacitor update enforcement (Android)

`AppUpdateChecker` uses the installed `versionCode`, calls version-check on launch and (throttled ~15 min) resume. Mandatory → blocking overlay (Update Now only). Optional → dismissible prompt. Service failures soft-fail (no permanent lockout). Never silently installs APKs.

Environment keys (optional overrides): `GH_ANDROID_APP_CHANNEL`, `GH_ANDROID_APP_DISTRIBUTION_ENABLED`, `GH_IOS_URL_HOSTS`.

**LIVE rule:** set `GH_ANDROID_APP_CHANNEL=live` and keep distribution gated until a signed, owner-approved APK is published. TEST / UNVERIFIED / unsigned filenames are refused on the LIVE channel.

**iOS:** policy + API prepared. No iOS binary is produced by this project.

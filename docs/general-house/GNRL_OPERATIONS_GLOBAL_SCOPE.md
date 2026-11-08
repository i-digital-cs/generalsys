# GNRL Operations — Full GeneralSys Scope (TEST)

**Date:** 2026-11-08  
**Environment:** TEST only (`https://generalsys.idigital-cs.com/`)  
**No LIVE deploy / push / merge.**

## Verdict

GNRL Operations is the Capacitor shell for the **entire** GeneralSys web app. APK download sits in the **global** sidebar footer for every authenticated user. App Version Management lives under **Admin Settings**. Package IDs and branding are unchanged.

## Routes

| Purpose | Primary | Compat |
|---------|---------|--------|
| APK file | `GET /app/download/apk` (`app.download.file`) | `GET /service/gh/app/download/apk` → redirect |
| Download page | `GET /app/download` | GH path → redirect |
| Version admin | `GET/PUT /admin/app/versions` | GH GET → redirect; GH PUT still updates then redirects |
| Version check | `GET /app/version-check` | `GET /service/gh/app/version-check` (same handler) |

Auth: download = any **active** authenticated user (`GnrlAppAccess::mayDownload`). Version admin = Manager only (`GnrlAppAccess::mayManageVersions`). Guest → login. Inactive → `account.inactive`.

## Capacitor navigation

`allowNavigation` remains **host-scoped** (TEST/LIVE isolation). No GH route allowlist. Same-origin navigation across HR / Service / Product / Settings uses existing Laravel auth, policies, CSRF, and cookies. HTTPS-only preserved.

Installed TEST builds that still call `/service/gh/app/version-check` keep working (compat alias). New build **1.0.2 (versionCode 3)** calls `/app/version-check`.

## TEST APK

| Field | Value |
|-------|-------|
| File | `mobile/dist/test/GeneralHouse-TEST-debug.apk` |
| applicationId | `net.genral.generalhouse.test` |
| versionName | `1.0.2` |
| versionCode | `3` |
| SHA-256 | `ce9244f31a33f267d5e8df020510b498acf649a16dcf5ce58469745052003810` |
| Download | `https://generalsys.idigital-cs.com/app/download/apk` (auth required) |

Publish: `php artisan gh:publish-android-apk mobile/dist/test/GeneralHouse-TEST-debug.apk --channel=test`

## Automated tests

```text
php artisan test --filter='AndroidAppDownloadTest|AppVersionPolicyTest'
→ 19 passed (100 assertions)
```

## Browser verification (TEST, Manager session)

| Check | Result |
|-------|--------|
| Global login landing | PASS — `/home` role/portal overview |
| HR sidebar APK footer | PASS — bottom of HR sidebar, Android direct link |
| GH Service sidebar | PASS — footer present; **no** App Version Management menu item |
| Settings → App Version Management | PASS — `إدارة إصدارات التطبيق` / link to `/admin/app/versions` |
| Version admin Android TEST | PASS — latest `1.0.2` / build `3`, published, optional |
| Guest APK | PASS — 302 → login |
| Version-check API | PASS — `/app/version-check` + compat GH path |
| Product (Mohra) sidebar APK footer | PASS — Android download present at sidebar bottom |

Screenshots were captured in the Cursor browser session (HR footer, Settings card, App Version Management, GH Service without versions menu).

## Multi-role coverage

| Role | How verified |
|------|----------------|
| Manager / system admin | Browser + feature tests |
| HR employee (no GH) | Feature tests: download OK + HR footer |
| GH driver / service employee | Feature tests |
| Guest / inactive | Feature + curl |
| Accountant | **Not available** on this TEST DB (no ACCOUNTANT user) |
| Payroll UI in WebView | **Not device-tested** |

## Known mobile limitations (honest)

- No emulator/device install of build 3 was run on this host in this pass.
- PDF / large file downloads rely on WebView `DownloadManager` (unchanged); not re-validated on device.
- Product (Mohra) Manager browser check confirmed the global APK footer; deep Product workflows were not exercised.
- LIVE channel policies remain unpublished / separate; no LIVE APK publish.

## Package / branding (unchanged)

- TEST label: GNRL Operations TEST — `net.genral.generalhouse.test`
- LIVE label: GNRL Operations — `net.genral.generalhouse`
- Launcher / splash: `mobile/branding/*`

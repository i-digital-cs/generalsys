# Mandatory Android update enforcement fix (TEST)

**Date:** 2026-11-08  
**TEST only** — no LIVE changes.

## Current TEST Android policy (after publish — unchanged floor)

| Field | Value |
|-------|--------|
| latest_version_name | **1.0.5** |
| latest_build | **6** |
| minimum_build | **0** (not raised) |
| update_mode | **OPTIONAL** |
| publication_status | PUBLISHED |
| package_id | `net.genral.generalhouse.test` |
| sha256 | `f3cdb0b573819f8ae94044add87a2ecc856b1eae6f05719404d041c56a561480` |
| update_url | `https://generalsys.idigital-cs.com/app/download/apk` |

Publishing **does not** auto-enable mandatory updates.

## Root cause of “message shows but app still usable”

1. **Policy vs expectation:** With `minimum_build=0` + `OPTIONAL`, the API correctly returns `update_required=false` and `update_available=true` for older builds. That is an **optional** prompt (Later allowed) — not a hard block.
2. **Native bypasses (real defects when mandatory *was* configured):**
   - Overlay was a content-view inflate; **BACK** still ran Cap WebView `goBack()` / finished activity.
   - WebView stayed **enabled and interactive** under the overlay.
   - Soft-fail / hide paths could leave enforcement incomplete; mandatory was not a true Android Dialog.

A dismissible web modal was never the enforcement layer; the shell overlay was insufficient.

## Corrected native enforcement (build 6)

- `AppUpdateDecision` — pure rules (NONE / OPTIONAL / MANDATORY / SOFT_FAIL).
- `AppUpdateChecker` — **non-cancelable fullscreen Dialog**; no outside dismiss; BACK consumed when mandatory; WebView disabled while blocking; resume recheck; soft-fail never clears an active mandatory block; optional Later still available.
- `MainActivity` — BACK callback calls `updateChecker.consumeBackPress()` before WebView history.
- Server `evaluateCheck` — never sets `update_available` / `update_required` unless Android release artifact is ready for the trusted channel.

## Automated tests

```text
php artisan test --filter='AppVersionPolicyTest|GlobalHeaderModuleSwitcherTest'
→ 16 passed

./gradlew :app:testSandboxDebugUnitTest
→ AppUpdateDecisionTest PASS
```

Native Dialog BACK/WebView blocking on a real device: **UNVERIFIED** (no emulator/KVM on this host).

## Owner UAT procedure (controlled — do not leave minimum raised)

Uses two TEST builds with the **same** signing / package `net.genral.generalhouse.test`:

1. Confirm current policy: latest=6, minimum=0, OPTIONAL.
2. Install **build 5** (previous APK) OR keep an older TEST install.
3. In Admin → App versions (TEST Android): set `minimum_build=6`, `update_mode=REQUIRED_BY_MINIMUM`, keep PUBLISHED. Save.
4. Open the older app → expect fullscreen **Update required**, **no Later**, BACK does nothing useful, WebView not usable.
5. Tap **Update Now** → opens `https://generalsys.idigital-cs.com/app/download/apk` (login if needed) → install **1.0.5 (6)** over the old build.
6. Relaunch → normal access.
7. **Reset** policy: `minimum_build=0`, `update_mode=OPTIONAL` (unless owner wants mandatory permanently).

Optional-only check: with minimum=0, older build shows Update available + Later and may continue.

## Unverified on this host

- Physical/emulator proof of Dialog BACK / outside-tap / WebView disable.
- Android 15/16 device screenshots of the mandatory UI.

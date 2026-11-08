# GeneralSys FULL TEST → LIVE Release Preparation Audit

**Date:** 2026-11-08  
**Phase:** READ-ONLY pre-deployment audit (Phase 1)  
**Scope:** Full GeneralSys (HR, Attendance, Payroll, Gross/Product finance, Service modules, General House, GNRL Operations mobile)  
**Execution status:** **NO LIVE writes · NO migrations · NO deploy · NO push · NO merge**

**Final verdict: BLOCKED**

---

## 0. Executive summary

LIVE (`https://system.genral.net/`) is **reachable over HTTPS** and appears to be the same Laravel app family (session cookie `general_house_laundry_session`, PHP 8.2.35, Hostinger CDN).  

However, **LIVE is not hosted on this cPanel account** (`d14098`). Its A record resolves to **`195.35.60.130`**, while TEST (`generalsys.idigital-cs.com`) resolves to **`185.181.255.62`**. No filesystem path, MySQL database, git revision, or migration status for LIVE is available from this host.

Without LIVE shell/DB access, schema comparison, backup validation, and a safe deploy cannot be completed. Owner must provide LIVE project path / credentials / panel access before any production release.

Additionally, TEST currently has a **dirty working tree** (~100 modified + ~104 untracked paths) that is **not committed**, and the active branch mixes Attendance RC history with General House work. Shipping “everything on disk” as-is is unsafe.

---

## 1. Environment inventory

### 1.1 TEST (accessible)

| Item | Value |
|------|--------|
| Path | `/home/d14098/generalsys.idigital-cs.com` |
| Public URL | `https://generalsys.idigital-cs.com/` |
| Server IP | `185.181.255.62` |
| PHP | **8.2.35** (CLI + LIVE/TEST HTTP both report 8.2.35) |
| Laravel | **11.55.0** |
| Node / npm | v20.20.2 / 10.8.2 |
| DB name | `d14098_general` @ `127.0.0.1` |
| Tables | **98** total; **33** `gh_*` |
| Queue | `database` |
| Cache / session | `file` / `file` |
| GH Android channel config | `test` |
| Cron | `* * * * * cd …/generalsys.idigital-cs.com && /usr/local/bin/php artisan schedule:run` |
| Scheduler jobs | `queue:work --stop-when-empty`, `reports:warn-missing`, `gh:promote-pickup-eligible` |
| Storage | `storage/`, `bootstrap/cache` writable; `storage/app/private/gh-android` present (mode 700) |

### 1.2 Git (TEST)

| Item | Value |
|------|--------|
| Branch | `release/attendance-ssot-grace-payroll-rc-20261022` |
| HEAD | `7e2cb3b5c46ad94971ca43b2cdcf4acf5ae22283` |
| vs origin (same branch) | **ahead 20** |
| vs `origin/main` | ~**23** commits / **344 files** (+31k/−154) in committed tip alone |
| Working tree | **DIRTY** — ~100 modified, 3 deleted build assets, ~104 untracked paths |
| Uncommitted scope | General House GH-9.x–GH-10+, VAT audits, app version policies, Capacitor `mobile/`, global header CSS/JS, many tests/docs |

**Implication:** There is no single clean release commit representing “all completed work.” A release artifact must be curated before LIVE.

### 1.3 LIVE (partially observable only)

| Item | Value |
|------|--------|
| URL | `https://system.genral.net/` → redirects to `/login` |
| Login title | `تسجيل الدخول — General House Laundry` |
| PHP | 8.2.35 (`x-powered-by`) |
| Platform | Hostinger (`platform: hostinger`, `server: hcdn`) |
| A record | **`195.35.60.130`** (≠ TEST) |
| cPanel domains on `d14098` | **No** `system.genral.net` / `genral.net` |
| Filesystem path | **UNKNOWN / INACCESSIBLE from this host** |
| Database name | **UNKNOWN** |
| Git revision | **UNKNOWN** |
| Migration status | **UNKNOWN** |
| Cron / queue | **UNKNOWN** |
| Served frontend assets (login) | `app-DTRGYXEQ.css`, `app-BBdTcOWU.js` |
| TEST frontend assets (login) | `app-DvBJTbS4.css`, `app-Di7dl39l.js` |

**Finding LIVE-ACCESS-1 (BLOCKER):** LIVE code and DB cannot be audited or deployed from this TEST account. Owner must identify the LIVE Hostinger account / path / SSH / panel.

**Finding LIVE-ASSET-1 (INFO):** LIVE login still serves older Vite build hashes than current TEST, confirming environments diverge.

### 1.4 Environment keys present on TEST (names only — no values)

Required class of keys observed: `APP_*`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_*`, `MAIL_*`, `AWS_*`, `GH_STANDARD_VAT_RATE`, `LOG_*`, `FILESYSTEM_DISK`, `PREPROD_ACCESS_KEY`, etc.

LIVE `.env` must be independently verified on the LIVE host (never copy TEST secrets).

---

## 2. Full change inventory (TEST → intended LIVE)

### 2.1 Already on committed tip vs `origin/main` (partial)

Includes Attendance SSOT / grace / payroll RC work **and** General House GH-1…GH-9.x committed history on this branch. Exact LIVE delta **cannot** be computed without LIVE git hash.

### 2.2 Uncommitted / untracked on TEST disk (must be release-gated)

**General House Laundry (major):**

- Orders / logistics / driver workspace / movement log
- Invoices / payments / VAT settings + audits
- Credit notes / refunds / financial corrections (GH-10)
- Excel import
- Pricing review flags
- Contract sequence / driver list positions
- Zones validation-only launch policy
- Reporting / dashboard updates
- New Blade views, policies, services, feature tests

**Global / shell:**

- Shared navbar / module switcher / desktop header fix (`_hr-pro.scss`, `navbar.blade.php`)
- `GnrlAppAccess`, global APK download routes, admin app versions UI
- Lang keys AR/EN

**GNRL Operations mobile:**

- Entire `mobile/` Capacitor project (TEST+LIVE flavors)
- Publish command `gh:publish-android-apk`
- Version-check API + policy tables
- Private APK store under `storage/app/private/gh-android`

**HR / Attendance / Payroll / Gross:**

- Present in branch history vs `main`; treat as **separate approval** — do not assume all Attendance RC commits are approved for this LIVE window unless owner explicitly includes them.

### 2.3 Experimental / incomplete / do-not-ship-as-is

| Item | Classification |
|------|----------------|
| Dirty uncommitted tree | **Not shippable** until committed/tag reviewed |
| `mobile/dist/live/*-UNVERIFIED-unsigned.apk` | **Not production** |
| Attendance RC commits on same branch | **Requires explicit inclusion decision** |
| Docs-only evidence folders | Ship optional; not runtime |
| TEST APK / TEST channel policies | Never copy into LIVE |

---

## 3. Database safety (migration risk matrix)

### 3.1 Cannot verify LIVE schema

Blocked without LIVE DB access. Below assumes LIVE is older (pre–GH tables) based on older frontend assets — **hypothesis only**.

### 3.2 Forward migrations relevant to this release (TEST has applied)

| Migration | Class | Risk notes |
|-----------|-------|------------|
| `2026_10_25_200000_create_gh_catalog_tables` | Safe additive (create) | New tables only |
| `2026_10_25_210000_create_gh_customers_zones_contracts_tables` | Safe additive | New tables only |
| `2026_10_25_220000_create_gh_orders_tables` | Safe additive | New tables only |
| `2026_10_31_110000_create_gh_logistics_tables` | Additive + alter `gh_orders` | Safe if GH orders empty/new; column adds |
| `2026_10_31_120000_repair_gh_utc_timestamps` | **Data repair** | Needs review on LIVE if GH timestamps already exist; no-op if empty |
| `2026_10_31_140000_create_gh_invoice_tables` | Safe additive | Financial tables |
| `2026_10_31_150000_create_gh_payment_tables` | Safe additive | Financial tables |
| `2026_10_31_160000_add_gh_reporting_indexes` | Safe additive indexes | Low risk |
| `2026_10_31_170000_gh92_zones_validation_only_launch` | Enum/default alter | Potentially breaking if LIVE already uses other `min_order_policy` values |
| `2026_10_31_180000_create_gh_financial_correction_tables` | Safe additive | Credit notes / refunds / corrections |
| `2026_11_04_120000_…driver_list_positions` | Additive + sequence data | May include seed/sequence init — verify idempotency |
| `2026_11_04_130000_…pricing_review…received_lines` | Additive columns | Low risk |
| `2026_11_07_164133_…pricing_review…invoice_lines` | Additive columns | Low risk |
| `2026_11_08_082400_create_gh_vat_rate_audits_table` | Safe additive | |
| `2026_11_08_101000_create_gh_app_version_policy_tables` | Safe additive + seed rows | Seeds TEST/LIVE channel policy rows; **do not copy TEST APK metadata** |

**Hard rules:**

- Never copy TEST DB over LIVE.
- Never copy TEST GH orders/invoices/payments/payroll/attendance into LIVE.
- Do not run `migrate:rollback` on LIVE for GH recovery.
- Down methods that `drop*` are **not** a safe LIVE rollback path.

### 3.3 Attendance / payroll / gross migrations

Already largely historical on TEST; LIVE status **unknown**. Any Attendance SSOT migrations not yet on LIVE need a separate risk review before inclusion.

---

## 4. Operational dependencies

| Dependency | TEST status | LIVE readiness |
|------------|-------------|----------------|
| PHP 8.2 | OK | HTTP reports 8.2.35 — OK if same for CLI |
| Composer vendor upload | Required (no server `composer install` per policy) | Unknown path |
| `public/build` Vite artifacts | Present (new hashes) | LIVE still on older hashes |
| Queue `database` | Scheduled via `schedule:run` | LIVE cron **unknown** |
| Scheduler | Cron present for TEST | Must install on LIVE host |
| Private APK storage | Exists | Must create + permissions on LIVE |
| `GH_*` env vars | Present on TEST | Must configure on LIVE |
| PDF / uploads / AWS | Keys named in TEST `.env` | Verify LIVE independently |
| GNRL Operations on LIVE host | Configured in Cap LIVE JSON | **Server-side download/version APIs not verified on LIVE** |

---

## 5. Mobile release readiness

| Requirement | Status |
|-------------|--------|
| LIVE package ID `net.genral.generalhouse` | Configured in Cap LIVE / production flavor |
| Label `GNRL Operations` | Configured |
| Host `https://system.genral.net/login` | Embedded in LIVE Cap config |
| Official branding assets | Present under `mobile/branding/` |
| Signed release keystore | **NOT FOUND** on this host |
| LIVE APK artifact | `mobile/dist/live/GeneralHouse-LIVE-UNVERIFIED-unsigned.apk` — **UNVERIFIED / NOT APPROVED** |
| SHA-256 (unsigned) | `6f0d0d2ac85326f07af2aa2224d9ea86c2dadcefa993836e39e07a97a1591dd9` |
| Publish to LIVE store | Blocked until LIVE app + signing + owner approval |
| Version-check / mandatory update | Implemented on TEST; LIVE DB/routes **unverified** |
| iOS app | Infrastructure/policy only — **no iOS app claimed** |

**Do not** publish unsigned APK. **Do not** copy TEST APK into LIVE.

---

## 6. Backup & rollback plan (to execute only after access + approval)

### 6.1 Before any LIVE change

1. Full LIVE MySQL dump (all schemas used by app) → off-host secure storage + checksum.
2. Tar LIVE app tree (code + `vendor` + `public/build` + `.env` securely stored) excluding noisy logs.
3. Record LIVE git commit / deploy revision / file mtimes.
4. Export LIVE crontab.
5. Restore dump + tar to a **scratch** environment and boot-check (do not overwrite LIVE).

### 6.2 Rollback strategy

| Layer | Rollback |
|-------|----------|
| Code | Re-upload previous tar / previous git tag |
| Config | Restore prior `.env` from secure backup |
| Assets | Restore prior `public/build` |
| DB schema | Prefer restore from pre-migrate dump; **do not rely on migrate:rollback** for GH/finance tables |
| Mobile | Keep previous LIVE APK; do not force-minimum until new signed APK is live |

### 6.3 Non-reversible / high-cost migrations

- Enum alters (`gh92_zones_validation_only_launch`)
- Timestamp repair data migration
- Any financial table that accumulates LIVE money after go-live (restore-from-backup only)

---

## 7. Proposed release sequence (NOT AUTHORIZED YET)

1. **Unlock LIVE access** (path, DB, SSH/panel) — hard gate.  
2. Owner approves release scope (include Attendance RC? GH-10 corrections? mobile?).  
3. Create clean release branch/tag from reviewed commits; commit remaining approved uncommitted work; exclude experiments.  
4. Scratch DB: `migrate:fresh` smoke + upgrade-path migrate on clone of LIVE dump.  
5. Pre-prod backup of LIVE (validated restore).  
6. Maintenance window / traffic strategy (Hostinger shared — short downtime likely).  
7. Deploy code + `vendor` + `public/build` (no server npm/composer unless owner changes policy).  
8. Configure LIVE `.env` (channel `live`, URLs, GH VAT, mail, etc.).  
9. `php artisan migrate --force` (authorized).  
10. `config:cache` / `route:cache` / `view:cache` as appropriate; clear stale caches.  
11. Ensure LIVE cron `schedule:run` + queue empty-worker pattern.  
12. Smoke: login, HR, payroll menu ACL, GH catalog/order/invoice paths, version-check endpoint, APK download ACL.  
13. Mobile: sign LIVE APK → owner UAT → publish via LIVE policy system; never auto-raise `minimum_build`.  
14. Monitor logs / payments / attendance for 24–72h.  
15. Rollback triggers: migrate failure, login failure, payroll ACL breach, financial calculation mismatch, mobile pointing at wrong host.

### GO / NO-GO criteria

| Criterion | Status now |
|-----------|------------|
| LIVE filesystem + DB accessible for audit | **NO-GO** |
| Clean release artifact (no dirty tree) | **NO-GO** |
| Owner-approved scope vs Attendance RC | **NO-GO** (undecided) |
| LIVE backup validated | **NO-GO** |
| Scratch migrate vs LIVE dump | **NO-GO** |
| Signed LIVE APK available | **NO-GO** |
| LIVE cron/queue confirmed | **NO-GO** |

---

## 8. Estimated downtime & risk (conditional on LIVE hosting)

| Item | Estimate |
|------|----------|
| Downtime (shared Hostinger file swap + migrate) | **15–45 minutes** typical if prep complete; longer if schema conflicts |
| Risk level | **HIGH** until LIVE schema known; financial modules raise blast radius |
| Data loss risk if backup skipped | **Unacceptable** |

---

## 9. Blockers & required owner actions

1. **Provide LIVE project path** (or Hostinger account / SSH) for `system.genral.net`.  
2. Authorize read-only LIVE DB credentials (or dump) for schema comparison.  
3. Decide release scope: full Attendance RC + GH + mobile, or GH-focused isolation.  
4. Authorize commit/tag of approved TEST work (dirty tree cleanup).  
5. Provide Android **release signing keystore** (or confirm location).  
6. Explicitly approve a later Phase 2 deploy window.

---

## 10. Final verdict

# **BLOCKED**

Phase 1 audit cannot proceed to deployment planning execution because:

1. LIVE application root and database are **not accessible** from the TEST Hostinger account.  
2. TEST working tree is **not a clean release candidate**.  
3. LIVE Android release signing and production APK are **not ready**.  

**No LIVE writes were performed.** Waiting for explicit owner approval and LIVE access before Phase 2.

---

## Appendix A — Exact LIVE deployment path

**Unknown.** Must be discovered on the Hostinger account that owns `system.genral.net` (IP `195.35.60.130`).

## Appendix B — TEST identity reminder

Do **not** treat `APP_ENV=production` on TEST as LIVE. Identity = path + DB + hostname:

- TEST path: `/home/d14098/generalsys.idigital-cs.com`  
- TEST DB: `d14098_general`  
- TEST host: `generalsys.idigital-cs.com`

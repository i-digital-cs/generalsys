# GH-9 — Production Readiness Audit (AUDIT ONLY)

**Phase:** GH-9  
**Date:** 2026-10-31  
**Auditor role:** read-only assessment  
**Status verdict:** **CONDITIONAL** (see §15 / GH9_FINAL_CHECKLIST.md)

**Constraints honored:** no new features; no LIVE deploy; no mutation of TEST/LIVE DB records, cron, config, or application code; no remediation without owner approval.

---

## 1. Environment verification

| Signal | Observed value | Interpretation |
|---|---|---|
| App path | `/home/d14098/generalsys.idigital-cs.com` | Matches owner-identified **TEST** |
| MySQL database | `d14098_general` @ `127.0.0.1` | Matches owner-identified **TEST** |
| `APP_ENV` | `production` | **Does not** establish LIVE identity |
| `APP_URL` | `https://generalsys.idigital-cs.com` | TEST host naming |
| `APP_NAME` | `General House Laundry` | Branding only |
| PHP CLI | `8.2.35` | Compatible with Laravel 11 / AGENTS.md |
| Queue / cache | `database` / `file` | Shared-hosting compliant |
| App timezone | `Africa/Cairo` | Attendance/Payroll unchanged |
| GH timezone | `Asia/Dubai` (`config/general_house.php`) | GH business TZ |

**Finding ENV-1** — Severity: **Info**  
**Evidence:** `config('app.env')=production` while DB=`d14098_general`.  
**Affected:** operational communication.  
**Business impact:** risk of mistaking TEST for LIVE if ops rely on `APP_ENV` alone.  
**Recommended correction:** always identify environments by path + DB name (owner policy).  
**Blocks LIVE:** No.

**LIVE was not accessed.**

---

## 2. Git & release audit (summary)

| Item | Value |
|---|---|
| Branch | `release/attendance-ssot-grace-payroll-rc-20261022` |
| HEAD | `fd9846a` (`feat(gh): Phase GH-8 dashboard and reporting on TEST`) |
| Remote tracking | ahead **11** of `origin/release/attendance-ssot-grace-payroll-rc-20261022` |
| Dirty tree | `M error_log` only (untracked noise / host log) |
| GH-1…GH-8 tip | `fd9846a` (known GH-8 commit confirmed) |

Local commits ahead of origin (all GH):

1. `c8d2938` GH-1 catalog  
2. `6ed4b38` GH-2 customers/zones/contracts  
3. `57035e9` GH-3 orders  
4. `794f01f` GH-4 logistics  
5. `f3694b0` GH-4 migration integrity docs  
6. `a1fcd00` GH pickup display TZ docs  
7. `54d2964` UTC store + repair  
8. `c03bce9` GH-5 invoices  
9. `a26358e` GH-6 payments  
10. `7ae2819` GH-7 driver workspace  
11. `fd9846a` GH-8 dashboard/reporting  

vs `origin/main`, three **attendance** commits sit **below** GH-1 (`7ed3ed2`, `5a34058`, `ff9e36a`).

**Finding GIT-1** — Severity: **HIGH**  
**Evidence:** GH code lives on Attendance RC branch; tip is 11 commits ahead of origin; ancestry includes attendance SSOT commits vs `main`.  
**Affected:** release packaging / LIVE deploy risk.  
**Business impact:** shipping this branch tip can pull unrelated Attendance changes.  
**Recommended correction:** dedicated GH release isolation (see `GH9_RELEASE_ISOLATION_PLAN.md`). Do **not** merge tip blindly.  
**Blocks LIVE:** **Yes** until isolation strategy approved and executed.

**Finding GIT-2** — Severity: **LOW**  
**Evidence:** working tree `M error_log` (20 129 bytes).  
**Affected:** git hygiene.  
**Business impact:** noise in status; not deployable product code.  
**Recommended correction:** ensure `error_log` remains ignored / not committed.  
**Blocks LIVE:** No.

Detailed strategy: `GH9_RELEASE_ISOLATION_PLAN.md`.

---

## 3. Architecture audit

### 3.1 Layering (positive)

- Controllers under `app/Http/Controllers/GeneralHouse/` authorize then call services.
- Domain services under `app/Services/GeneralHouse/{Catalog,Customers,Orders,Invoices,Payments,Reporting}/`.
- Policies under `app/Policies/GeneralHouse/`; access gate `GhCatalogAccess::mayManage` = Manager-only.
- Financial SSOT: `GhInvoiceCalculationEngine`, `GhPaymentBalanceService`.
- UTC cast: `App\Casts\GhUtcDateTime`; scheduler command `gh:promote-pickup-eligible`.

### 3.2 Findings

**Finding ARCH-1** — Severity: **MEDIUM**  
**Evidence:** LOC — `GhOrderService.php` 612; `GhOrderStateService.php` 560; `GhReportQueryService.php` 453; `GhInvoiceCalculationEngine.php` 442; `GhDashboardService.php` 402; `GhInvoiceService.php` 381 (AGENTS.md §1.3 ≈300 line guideline).  
**Affected:** maintainability / reviewability.  
**Business impact:** higher change risk in logistics/finance paths; not a runtime defect.  
**Recommended correction:** post-launch SRP splits (state transitions vs CRUD; report query vs row mappers).  
**Blocks LIVE:** No.

**Finding ARCH-2** — Severity: **MEDIUM**  
**Evidence:** `GhReportQueryService::confirmedForInvoice` / `remainingForInvoice` (lines ~236–254) called per invoice inside `mapInvoiceRows` / `mapOutstandingRows` (~416–440); Blade `resources/views/gh/reports/outstanding.blade.php` also calls `$reports->confirmedForInvoice($inv)`.  
**Affected:** reporting performance (N+1 aggregates).  
**Business impact:** slow CSV/HTML reports as invoice volume grows.  
**Recommended correction:** single grouped `SUM` join (pattern already used in `b2bGrouped`, lines ~305–314).  
**Blocks LIVE:** No (launch volume expected low); monitor after go-live.

**Finding ARCH-3** — Severity: **LOW**  
**Evidence:** `GhCatalogAccess` comment — fine-grained `gh_user_pages` ACL deferred.  
**Affected:** multi-manager / accountant segregation.  
**Business impact:** any Manager can perform all GH finance actions.  
**Recommended correction:** owner decide if Accountant role needs GH finance later.  
**Blocks LIVE:** No for single-manager ops.

**Finding ARCH-4** — Severity: **LOW**  
**Evidence:** Repositories not used; Eloquent queried from services (consistent with existing app style).  
**Affected:** none critical.  
**Business impact:** none.  
**Recommended correction:** keep as-is unless team standardizes repositories.  
**Blocks LIVE:** No.

Transaction boundaries / locking: payments and issuance use `DB::transaction` + `lockForUpdate()` (`GhPaymentService.php` ~160–167; `GhInvoiceIssuanceService.php` ~25–46) — **PASS**.

---

## 4. Financial / tax / security / DB / TZ / infra

See dedicated docs:

| Topic | Document |
|---|---|
| Financial integrity | `GH9_FINANCIAL_INTEGRITY.md` |
| Security | `GH9_SECURITY_FINDINGS.md` |
| Open decisions (incl. C-GH-TAX-MIN) | `GH9_OPEN_DECISIONS.md` |
| Deployment | `GH9_DEPLOYMENT_PLAN.md` |
| Release isolation | `GH9_RELEASE_ISOLATION_PLAN.md` |
| Checklist / verdict | `GH9_FINAL_CHECKLIST.md` |

---

## 5. Database & migrations (summary)

| Migration | Batch on TEST | Status |
|---|---|---|
| `2026_10_25_200000_create_gh_catalog_tables` | 14 | Ran |
| `2026_10_25_210000_create_gh_customers_zones_contracts_tables` | 14 | Ran |
| `2026_10_25_220000_create_gh_orders_tables` | 14 | Ran |
| `2026_10_31_110000_create_gh_logistics_tables` | 15 | Ran |
| `2026_10_31_120000_repair_gh_utc_timestamps` | 16 | Ran |
| `2026_10_31_140000_create_gh_invoice_tables` | 17 | Ran |
| `2026_10_31_150000_create_gh_payment_tables` | 18 | Ran |
| `2026_10_31_160000_add_gh_reporting_indexes` | 19 | Ran |

**Finding DB-1** — Severity: **MEDIUM**  
**Evidence:** `docs/general-house/MIGRATION_INTEGRITY_AUDIT.md` — GH-1–3 bookkeeping rows re-inserted as batch **14** with ids **70–72** after GH-4 id **69** batch **15**.  
**Affected:** `migrations` table history on TEST; rollback order semantics.  
**Business impact:** `migrate:rollback` by batch is unsafe / misleading; fresh LIVE install of files is fine.  
**Recommended correction:** forward-only deploys; never rollback GH batches; do not renumber history without approval.  
**Blocks LIVE:** No if LIVE uses clean forward `migrate` from empty GH schema; **Yes** if someone plans rollback-based recovery.

FKs / uniques / decimal money columns: verified present on TEST (`uq_gh_invoice_order`, `uq_gh_payment_idempotency`, payment/invoice FKs). Indexes for driver/status/issued/payment paths present (GH-8 migration `160000`).

---

## 6. Timezone audit (summary)

| Concern | Result |
|---|---|
| GH storage | UTC via `GhUtcDateTime` |
| GH business TZ | `Asia/Dubai` |
| Eligibility / scheduler | UTC SQL cutoffs; command every 5 minutes |
| App TZ | `Africa/Cairo` untouched |
| Attendance/Payroll | No GH commit in `c8d2938^..fd9846a` touches attendance/payroll calculation paths |

**Finding TZ-1** — Severity: **Info**  
Historical skew repaired on TEST via `54d2964` + migration `120000`; document in `TIMEZONE.md`.  
**Blocks LIVE:** No if LIVE starts clean with UTC-only writes.

---

## 7. Infrastructure (summary)

| Item | Evidence | Verdict |
|---|---|---|
| Cron `schedule:run` | crontab line for `generalsys.idigital-cs.com` present | PASS on TEST |
| Queue drain | `routes/console.php` `queue:work --stop-when-empty --max-time=50` | Shared-hosting safe |
| GH promote | `gh:promote-pickup-eligible` everyFiveMinutes | Registered |
| Storage dirs | `storage`, `bootstrap/cache` writable by app user | PASS |
| Backups | `storage/backups/d14098_general_pre_gh{1..8}_*.sql` present | Good practice |
| Redis/Supervisor | Not required | PASS |

**Finding INFRA-1** — Severity: **MEDIUM**  
**Evidence:** LIVE host cron / path not verified (LIVE not accessed).  
**Affected:** LIVE scheduler / pickup promotion / queue.  
**Business impact:** without LIVE cron, LATER→PICKUP stalls; queued jobs stall.  
**Recommended correction:** verify LIVE crontab mirrors TEST before go-live (ops step in deployment plan).  
**Blocks LIVE:** **Yes** until LIVE cron verified at deploy time.

---

## 8. Automated tests / regression

| Suite | Result |
|---|---|
| Full `php artisan test` (isolated phpunit DB) | **1195 passed**, **4 failed**, **1 skipped**, 12524 assertions (~39.5s) |
| `--filter=GeneralHouse` | **93 passed**, 353 assertions (~3.0s) |
| `--filter='Attendance\|Payroll\|Gross\|Valuation'` | **641 passed**, **1 skipped**, 3411 assertions (~21.3s) |

### Full-suite failures (not hidden)

| Test | Relation to GH | Notes |
|---|---|---|
| `DriverRoleTest::test_driver_menu_has_clock_and_report_and_hr_lands_on_clock` | **GH-7** | Expects DRIVER menu routes without `gh.driver.dashboard`; config now prepends GH driver route |
| `Admin\DashboardTest::manager can select dashboard…` | Non-GH | Expects `value="2026-09"` month control |
| `Employee\EmployeeDashboardTest::dashboard lists…` | Non-GH | Expects `__('shifts.types.ONLINE_HALF')` text |
| `TeamLeader\ApprovalsFilterTest::approvals can filt…` | Non-GH | Expects `reason-rejected-bob` in filtered view |

**Finding TEST-1** — Severity: **HIGH**  
**Evidence:** `tests/Feature/DriverRoleTest.php:133` vs `config('menu.DRIVER')` including `gh.driver.dashboard` (GH-7).  
**Affected:** DRIVER menu contract / CI green.  
**Business impact:** release gate red if full suite required; product behavior intentional.  
**Recommended correction:** update DriverRoleTest expectation (owner-approved remediation).  
**Blocks LIVE:** **Conditional** — blocks if policy is “full suite green”; does not block cash ops functionally.

**Finding TEST-2** — Severity: **MEDIUM**  
**Evidence:** three non-GH failures above.  
**Affected:** Attendance/HR dashboard/approvals confidence.  
**Business impact:** pre-existing or environment-date flaky; not introduced by GH financial code.  
**Recommended correction:** triage separately from GH LIVE decision.  
**Blocks LIVE:** No for GH module alone; Yes for whole-platform “all green” policy.

---

## 9. End-to-end business workflow (static assessment)

Lifecycle assessed against services/tests (no shared TEST record mutation):

`Customer → Order → Pickup assign → Physical pickup → Received items → Invoice draft → Issue → Delivery assign → Delivery confirm → Payment → Reporting`

| Path | Assessment |
|---|---|
| Fully paid delivery | Supported: cash CONFIRMED → balance SSOT → PAID; delivery may complete while unpaid (`PaymentTest`) |
| Partial / unpaid delivery | Supported: PARTIAL/UNPAID statuses; delivery not gated on payment |
| Bypass auth | Manager policies on finance; driver workspace scoped by `pickup_driver_id` / `delivery_driver_id` |
| Issue with min-order surcharge | **Blocked** until `C-GH-TAX-MIN` resolved (`min_order_tax_unresolved`) |
| Online paid | Initiation only; gateway confirm disabled (fail closed) |
| Refund / credit note | Disabled / not implemented (`C-GH-CORR`) |

---

## 10. Performance (summary)

| Area | Finding | Severity |
|---|---|---|
| Report invoice maps | Per-row payment SUM (ARCH-2) | MEDIUM |
| Driver dashboard | Eager `with`, `limit(50)` | PASS |
| Order lists | Paginate 25 | PASS |
| CSV export | Streamed; formula sanitize | PASS |
| Scheduler | `withoutOverlapping`, 5‑min promote | PASS |

---

## 11. Critical / High rollup

See `GH9_FINAL_CHECKLIST.md` for complete severity tables and recommended fix order.

**LIVE deployment blockers (must clear before go-live):**

1. GIT-1 — release isolation  
2. C-GH-TAX-MIN — owner/accountant tax class decision (or accept surcharge-issue block)  
3. INFRA-1 — LIVE cron verification at deploy  
4. Launch payment feature matrix owner sign-off (cash-only vs online-init)  
5. TEST-1 — if full-suite-green is a hard gate  

**Do not deploy. Do not remediate without owner approval.**

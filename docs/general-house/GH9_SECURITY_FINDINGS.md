# GH-9 — Security Findings

**Phase:** GH-9 (audit only)  
**Date:** 2026-10-31  
**Scope:** General House GH-1…GH-8 on TEST (`d14098_general`)  
**Method:** static review + policy/route/service inspection; **no** destructive penetration testing; **no** LIVE access.

---

## Summary

| Severity | Count |
|---|---|
| Critical | 0 |
| High | 2 |
| Medium | 4 |
| Low | 4 |
| Info | 2 |

Overall security posture for Manager-only GH finance is **sound for controlled launch**, with residual risks around mass-assignment surface, deferred ACL, and DRIVER menu/regression hygiene.

---

## Critical

_None identified._

---

## High

### SEC-H1 — DRIVER menu contract drift (regression / auth surface expansion)

| Field | Detail |
|---|---|
| Severity | **HIGH** |
| Evidence | `config/menu.php` DRIVER routes now include `gh.driver.dashboard`; `tests/Feature/DriverRoleTest.php:133` still expects only `employee.clock`, `employee.report`, `employee.media`. Full suite failure confirms drift. |
| Affected | DRIVER navigation / Post-login expectations / CI gate |
| Business impact | Intentional GH-7 workspace entry; unupdated test means release gates fail. Not an IDOR by itself. |
| Recommended correction | Update DriverRoleTest + document DRIVER GH entry as approved surface; confirm PostLoginRedirector still lands HR clock as designed. |
| Blocks LIVE | **Conditional** (hard if “all tests green” policy) |

### SEC-H2 — Invoice/Payment `$fillable` includes mutable financial status fields

| Field | Detail |
|---|---|
| Severity | **HIGH** |
| Evidence | `GhInvoice::$fillable` includes `status`, `payment_status` (`app/Models/GeneralHouse/GhInvoice.php` ~17–18). `GhPayment::$fillable` includes `status` (`GhPayment.php` ~16–17). Controllers use Form Requests + services, but defense-in-depth is weaker if any future path uses mass assignment from request input. |
| Affected | Invoice / payment models |
| Business impact | Theoretical unauthorized status mutation if a controller ever `create($request->validated())` without stripping status. Current services set status explicitly inside transactions. |
| Recommended correction | Remove `status` / `payment_status` from `$fillable`; set only in services. Add feature test that HTTP cannot set payment_status. |
| Blocks LIVE | **No** (current call paths audited); **Yes** if owner requires hardened mass-assignment before money goes live |

---

## Medium

### SEC-M1 — Manager-only coarse ACL (no per-page GH permissions)

| Field | Detail |
|---|---|
| Severity | **MEDIUM** |
| Evidence | `GhCatalogAccess::mayManage` → `$user->isManager()` only (`app/Support/GeneralHouse/GhCatalogAccess.php:13–16`). Comment defers `gh_user_pages`. Policies (`GhPaymentPolicy`, `GhInvoicePolicy`, etc.) all gate on `mayManage`. |
| Affected | All GH admin/finance routes |
| Business impact | Every Manager can collect cash, issue invoices, export receivables. No Accountant-only or read-only Manager split. |
| Recommended correction | Owner decide whether ASSISTANT/ACCOUNTANT need scoped GH access before multi-user ops. |
| Blocks LIVE | No for single trusted Manager |

### SEC-M2 — Driver IDOR mitigated in service, dual enforcement points

| Field | Detail |
|---|---|
| Severity | **MEDIUM** |
| Evidence | `GhDriverWorkspaceService` filters by `pickup_driver_id` / `delivery_driver_id` (e.g. lines 40–88, 134–141). `DriverWorkspaceController::history` re-scopes query (lines 117–123). Confirm/fail uses `GhOrderAccess::mayConfirmDelivery` / `mayRecordDeliveryAttempt`. |
| Affected | Driver workspace |
| Business impact | Correct scoping observed; risk is future controller bypassing service helpers. |
| Recommended correction | Keep mutations exclusively via `GhOrderStateService` + `GhOrderAccess`; add IDOR feature tests for foreign order IDs (if not already exhaustive). |
| Blocks LIVE | No |

### SEC-M3 — Online payment initiation without gateway confirm (orphan PENDING)

| Field | Detail |
|---|---|
| Severity | **MEDIUM** |
| Evidence | `online_gateway_confirm_enabled=false`; `GhPaymentService::confirmOnlineGateway` always throws (`~124–133`). Pending amounts excluded from balance (`GhPaymentBalanceService::forInvoice` uses CONFIRMED only). |
| Affected | Online initiation UX / ledger clutter |
| Business impact | Operators may create PENDING online rows that never settle; outstanding unchanged (correct). Abuse = noise / support load, not underpayment of books. |
| Recommended correction | Keep gateway off until provider approved; optionally disable initiation in UI for launch; purge/cancel PENDING via existing cancel path. |
| Blocks LIVE | No if cash is primary launch method |

### SEC-M4 — CSV formula injection mitigated (verify all export columns)

| Field | Detail |
|---|---|
| Severity | **MEDIUM** (residual) |
| Evidence | `GhCsvExportService::sanitizeCell` prefixes `=+-@` (`app/Services/GeneralHouse/Reporting/GhCsvExportService.php:42–54`). ReportController uses this service for all GH CSV downloads. |
| Affected | Exports |
| Business impact | Residual risk if a future export bypasses sanitize. |
| Recommended correction | Forbid raw `fputcsv` outside this service (code review rule). |
| Blocks LIVE | No |

---

## Low

### SEC-L1 — XSS posture

| Field | Detail |
|---|---|
| Severity | **LOW** |
| Evidence | Blade GH views use `{{ }}` by convention; no GH `{!! !!}` on user content spotted in sampling. Daily-report rule in AGENTS.md remains app-wide. |
| Affected | Customer names/notes in views |
| Business impact | Standard Laravel escaping if followed. |
| Recommended correction | Continue Blade review on new GH templates. |
| Blocks LIVE | No |

### SEC-L2 — CSRF

| Field | Detail |
|---|---|
| Severity | **LOW** |
| Evidence | GH routes under `auth` web middleware group (`routes/web.php` ~90+, prefix `service/gh`). No Sanctum API for GH mutations in this phase. |
| Affected | Forms |
| Business impact | CSRF tokens apply to web posts. |
| Recommended correction | None for launch. |
| Blocks LIVE | No |

### SEC-L3 — SQL injection

| Field | Detail |
|---|---|
| Severity | **LOW** |
| Evidence | Query builder / Eloquent dominate; reporting uses parameterized where + `selectRaw` with fixed aggregates (`SUM(amount)`). |
| Affected | Reports |
| Business impact | Low if filter inputs cast/validated via `GhReportFilterRequest`. |
| Recommended correction | Keep filters validated; avoid interpolating user strings into raw SQL. |
| Blocks LIVE | No |

### SEC-L4 — Rate limiting

| Field | Detail |
|---|---|
| Severity | **LOW** |
| Evidence | Login throttles exist elsewhere; GH cash/record endpoints not specifically throttled. |
| Affected | Payment/order POST endpoints |
| Business impact | Low volume Manager ops; shared hosting DoS limited. |
| Recommended correction | Optional throttle on payment record routes post-launch. |
| Blocks LIVE | No |

---

## Info

### SEC-I1 — Sensitive logs

| Field | Detail |
|---|---|
| Severity | **Info** |
| Evidence | Root `error_log` modified (20 KB); Laravel logs under `storage/logs`. |
| Affected | Host logging |
| Business impact | Ensure payment amounts/PII not dumped via `dd`/debug. No debug leftovers required by AGENTS.md. |
| Recommended correction | Keep `error_log` out of git; log rotation on LIVE. |
| Blocks LIVE | No |

### SEC-I2 — File uploads

| Field | Detail |
|---|---|
| Severity | **Info** |
| Evidence | GH-1…8 do not introduce customer document upload endpoints in the audited phase. |
| Affected | N/A |
| Business impact | None for GH launch surface. |
| Recommended correction | Re-audit when document features added. |
| Blocks LIVE | No |

---

## Authorization matrix (observed)

| Actor | Catalog/Customers/Orders (manage) | Issue invoice | Record cash | Initiate online | Driver workspace | Reports/CSV |
|---|---|---|---|---|---|---|
| MANAGER | Yes (`mayManage`) | Yes | Yes | Yes (flag) | If also driver access rules | Yes |
| DRIVER | No (403 on manage) | No | No (`PaymentTest`) | No | Yes (scoped) | No |
| TEAM_LEADER / EMPLOYEE | No | No | No | No | No (unless driver role) | No |

Payment status is **not** independently set via HTTP in current controllers; `GhPaymentBalanceService::syncInvoicePaymentStatus` recomputes from CONFIRMED ledger.

---

## IDOR checklist

| Scenario | Result |
|---|---|
| Driver acts on another driver’s order | Service asserts assigned driver id (workspace + access helpers) |
| Driver records payment | Forbidden by policy / tests |
| Manager accesses any GH invoice | Allowed by design |
| Cancel non-pending payment | `cancelPending` requires `isPending()` |

---

## Blocks LIVE (security-specific)

- None **Critical**.
- **SEC-H2** remediation recommended before high-volume finance, not a hard blocker if current services remain sole writers.
- **SEC-H1** if organization requires green full suite.

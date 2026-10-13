# Task 2 Phase 1 — Exit report

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 1 only (baseline stabilization).  
**Phase 2:** Not started.

---

## Original seven failures

| # | Test | Classification | Root cause | Resolution |
|---|---|---|---|---|
| 1 | `PayrollLateMinutesFromClockTest::exact_late_uses_clock_in…` | **STALE TEST** | Expected punch late on **2026-09-14** with stored `late_minutes=0`; Task 1.6 requires stored path pre-Oct | Rewrote expectations: stored 0 + raw 104; added Oct grace-counted case |
| 2 | `PayrollLateMinutesFromClockTest::aggregator_sums…` | **STALE TEST** | Same historical path; expected 50 (30 punch + 20 override) | Assert Sep total **20** (override only); Oct punch 30 separately |
| 3 | `DualDefaultShiftsTest::late_start_allowed…` | **STALE TEST** | Expected `STATUS_LATE` on clock-in; flexible start keeps PRESENT | Assert PRESENT on clock-in; pre-Oct close late_minutes 0 |
| 4 | `PayrollPrivacyTest::team_leader_sidebar…` | **STALE TEST** | `assertDontSee(__('nav.payroll'))` matches Arabic **الرواتب** in attendance/permission copy; server 403 already green | Assert no payroll route / `bi-cash-stack`; assert menu config; keep route 403 test |
| 5 | `GrossHardeningTest::currency_immutable_after_confirm` | **CONFIRMED APPLICATION DEFECT** | `updateDraft`→`update()` (T-15.5) allowed currency change after confirm; contradicts `GROSS_MODULE.md` | Enforce `currency_immutable` in sales (+ purchase) `update()`; update test |
| 6 | `PurchaseInvoiceFlowTest::http_create…` sales 403 | **CONFIRMED APPLICATION DEFECT** (test isolation) | `GrossAccess::$pageKeyCache` survived RefreshDatabase → user ID reuse leaked pages | Clear cache in `TestCase::{setUp,tearDown}` |
| 7 | `ProductExpenseModuleTest::assigned…` 302 vs 403 | **CONFIRMED APPLICATION DEFECT** (same cache) | Budget authorize used polluted `mayManageMasterData` | Same cache clear |

### Follow-on stale tests exposed by honest ACL cache

After fixing #6/#7, two tests that had been **false-green** via cache pollution failed correctly:

| Test | Classification | Resolution |
|---|---|---|
| `SalesHrAccessTest` sales index after product-only assign | **STALE TEST** vs T-15.6 page checklist | Assign sales role default pages via `AssignsGrossPages` |
| `GrossFoundationTest` items with product-only assign | **STALE TEST** vs T-15.6 | Assign `items` page explicitly |

---

## Changes made (application)

| Path | Change |
|---|---|
| `tests/TestCase.php` | Clear `GrossAccess` page-key cache each test |
| `app/Services/Gross/SalesInvoiceService.php` | Reject currency change when not DRAFT |
| `app/Services/Gross/PurchaseInvoiceService.php` | Same for non-draft purchases |
| `app/Exceptions/GrossException.php` | `currencyImmutable()` |
| `lang/{en,ar}/gross.php` | `gross.errors.currency_immutable` |

## Changes made (tests / docs)

| Path | Change |
|---|---|
| `tests/Unit/Payroll/PayrollLateMinutesFromClockTest.php` | Task 1.6 date-aware cases |
| `tests/Feature/Admin/DualDefaultShiftsTest.php` | Flexible-start expectation |
| `tests/Feature/Admin/PayrollPrivacyTest.php` | Menu/route assertions |
| `tests/Feature/Gross/GrossHardeningTest.php` | Currency immutable message |
| `tests/Feature/SalesHrAccessTest.php` | Page checklist |
| `tests/Feature/Gross/GrossFoundationTest.php` | Page checklist |
| `docs/refactoring/PHASE_1_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` | Progress log |
| `docs/refactoring/REGRESSION_BASELINE.md` | Updated counts |

**Not done:** service extraction, historical attendance backfill, Phase 2.

---

## Authorization findings

| Area | Server-side | UI | Verdict |
|---|---|---|---|
| Payroll TL | Routes/policies 403 (existing test green) | Menu config has no payroll; incidental Arabic word is not a menu leak | **PASS** — no ACL defect |
| Gross purchases (sales without purchases page) | Policy `mayPage('purchases')` | — | **PASS** after cache fix |
| ProductExpense budget write | FormRequest `mayManageMasterData` | View flag `canManage` | **PASS** after cache fix (was false-allow under pollution) |
| Gross sales / items | Page checklist + Product module | — | Tests aligned to T-15.6 |

Hiding nav alone was never treated as authorization; route 403 remains the primary payroll gate.

---

## Regression results

```text
Protected attendance/payroll filter: 95 passed (387 assertions)
Full suite: 922 passed (10068 assertions) — 0 failed, 0 skipped
Pint: passed
php -l: clean on touched PHP
```

---

## Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS: NOT APPLICABLE
DATA BACKFILL: NO
HISTORICAL ATTENDANCE RECONCILIATION: NOT EXECUTED
DATABASE MIGRATION POLICY: PASS
```

---

## Remaining risks

- `GrossAccess` static cache is process-lifetime; production is safe for distinct user IDs, but any code path that mutates pages **must** keep calling `clearPageKeyCache` (already done in `GrossUserPageService`).
- Other latent tests may still assume “Product module ⇒ all Gross pages”; prefer `AssignsGrossPages` in new tests.
- Historical pre-Oct attendance values that may have been rewritten before Task 1.6 remain out of scope.

---

## Readiness for Phase 2

Baseline is trustworthy and green. Phase 2 (attendance domain seams) may start **only after explicit owner approval**.

```text
TASK 2 — PHASE 1: PASS
ORIGINAL FAILING TESTS: 7
ORIGINAL FAILURES RESOLVED: 7
CONFIRMED APPLICATION DEFECTS: 3 (currency lock; GrossAccess cache isolation ×2 symptoms)
STALE TESTS CORRECTED: 6 (incl. 2 false-green exposed by cache fix)
ACL SECURITY: PASS
PROTECTED ATTENDANCE REGRESSION: PASS
FULL REGRESSION: PASS
DATABASE GOVERNANCE: PASS
APPLICATION BEHAVIOR: APPROVED CORRECTIONS ONLY
READY FOR PHASE 2: YES (pending owner approval)
```

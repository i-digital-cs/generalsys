# GH Phase 4.1 — Debit Adjustments Implementation Report

**Environment:** GeneralSys TEST only  
**Date:** 2026-11-15  
**Owner decision:** Option A — additional debit adjustment document linked to original issued invoice  
**LIVE / commit / push:** not performed  
**Final verdict:** **PASS**

---

## 1. Existing architecture findings

| Area | Finding |
|------|---------|
| Invoice immutability | `ISSUED` invoices cannot be rewritten (`GhInvoiceService`, empty `$fillable`, issuance snapshots). |
| Prior gap | Credit notes reduce billed amount; **no** path to increase after issue (Phase 4 audit). |
| Balance SSOT | `GhPaymentBalanceService::forInvoice` is the consumer API for outstanding / net / payment status. |
| VAT | Invoice stamps `standard_vat_rate`; lines use `GhTaxClass` + half-up AED rounding (`GhMoney`). |
| Payments | Confirmed payments only in cash net; settlements ≠ customer revenue. |
| Driver custody | `GhDriverCashCustodyService` independent of invoice AR; unchanged. |
| COMPLETED orders | Not an invoice-edit gate; COMPLETED + ISSUED can receive debit adjustments. |

---

## 2. Schema / migration

**Migration:** `database/migrations/2026_11_15_190000_create_gh_debit_adjustment_tables.php`  
**Applied on TEST:** yes (`php artisan migrate --force`)

| Table | Key columns |
|-------|-------------|
| `gh_debit_adjustments` | `invoice_id`, `order_id`, `customer_id`, `number` (unique, nullable until issue), `status`, `reason_*`, `lines_net`, `vat_amount`, `total_debit`, `standard_vat_rate`, `idempotency_key` (unique), `created_by` / `issued_by` / `cancelled_by`, `issued_at` / `cancelled_at` |
| `gh_debit_adjustment_lines` | `line_type`, optional `invoice_line_id` / `offering_id`, qty, unit_price, tax snapshots, `line_net` / `line_vat` / `line_total`, `snapshot` JSON |
| `gh_debit_adjustment_events` | `event_type`, from/to status, `actor_id`, `occurred_at` (UTC), `payload` |

Sequence: `gh_sequences.name = debit_adjustments` → numbers `GH-DA-######`.

Schema doc updated: `docs/DATABASE_SCHEMA.md` (Phase 4.1 section).

**No** changes to historical invoice totals, line rows, VAT on original invoice, or payment ledger rows.

---

## 3. Document lifecycle

```text
DRAFT  → ISSUED     (manager issue; assigns number; syncs invoice payment_status)
DRAFT  → CANCELLED  (manager cancel; no balance effect)
ISSUED → (terminal for money; no void-reissue)
```

Manager UI default path: create + issue in one POST (`issue_now=1`) with idempotency key. Draft + separate issue also supported.

Line types (positive only):

- `ADDITIONAL_ITEM` — catalog offering  
- `QUANTITY_INCREASE` — linked to original invoice line  
- `PRICE_INCREASE` — linked to original invoice line (unit price = positive delta)

Reductions must use existing credit notes (negative debit rejected).

---

## 4. Financial calculation examples (AED, 5% VAT, half-up)

### Example A — COMPLETED + qty increase

| Step | Amount |
|------|--------|
| Issued invoice (2 × 50.00 + VAT) | 105.00 |
| Debit: +1 × 50.00 + VAT | 52.50 |
| Net invoiced | 157.50 |
| Payments | 0.00 |
| Remaining due | **157.50** |
| Original `total_payable` | **105.00** (unchanged) |

### Example B — Fully paid then debit

| Step | Amount |
|------|--------|
| Invoice | 105.00 |
| Confirmed cash | 105.00 |
| Debit issued | 52.50 |
| Remaining due | **52.50** (PARTIAL) |
| Payment rows created by issue | **0** |

### Example C — Credit + debit

| Step | Amount |
|------|--------|
| Invoice | 105.00 |
| Credit issued (1 × 50 + VAT) | −52.50 |
| Debit issued | +52.50 |
| Net invoiced | **105.00** |

SSOT formula:

```text
net = max(0, invoice_total + debits_issued − credits_issued)
remaining_due = max(0, net − cash_net)
```

---

## 5. Files changed (implementation)

**Core**

- `app/Services/GeneralHouse/Corrections/GhDebitAdjustmentService.php`
- `app/Services/GeneralHouse/Corrections/GhDebitAdjustmentNumberService.php`
- `app/Models/GeneralHouse/GhDebitAdjustment{,Line,Event}.php`
- `app/Support/GeneralHouse/GhDebitAdjustment{Status,LineType,Reason}.php`
- `app/Services/GeneralHouse/Payments/GhPaymentBalanceService.php` (+ `debits_issued`)
- `app/Services/GeneralHouse/Reporting/GhDashboardService.php` (+ debit KPI)
- `app/Services/GeneralHouse/Reporting/GhReportQueryService.php` (B2B SSOT aggregation)
- `app/Http/Controllers/GeneralHouse/DebitAdjustmentController.php`
- `app/Policies/GeneralHouse/GhDebitAdjustmentPolicy.php`
- `config/general_house.php` (`corrections.debit_adjustments_enabled`)
- `app/Support/GeneralHouse/GhLaunchConfig.php`
- Routes, menu, `AppServiceProvider` policy bind

**UI / i18n**

- `resources/views/gh/debit-adjustments/{index,create,show}.blade.php`
- Invoice show + correction panel + dashboard KPI tiles
- `lang/{en,ar}/gh.php`, `lang/{en,ar}/nav.php`

**Tests / docs**

- `tests/Feature/GeneralHouse/DebitAdjustmentTest.php`
- `tests/Feature/GeneralHouse/InvoiceShowUiTest.php` (copy update)
- `docs/DATABASE_SCHEMA.md`
- this report

---

## 6. Authorization matrix

| Actor | Index / show | Create / issue / cancel |
|-------|--------------|-------------------------|
| Manager (`GhCatalogAccess::mayManage`) | yes (flag on) | yes |
| Driver | **403** | **403** |
| Employee / unauthorized | **403** | **403** |

Flag: `GH_DA_ENABLED` / `config('general_house.corrections.debit_adjustments_enabled')` (default true on TEST).

Drivers never see financial adjustment CTAs; custody/settlement screens untouched.

---

## 7. Financial SSOT integration

| Consumer | Debit-aware? |
|----------|--------------|
| `GhPaymentBalanceService::forInvoice` | **Yes** (`debits_issued` in net) |
| Invoice show summary / correction UI | **Yes** |
| Outstanding report (`remainingForInvoice`) | **Yes** (via SSOT) |
| Dashboard outstanding / net invoiced / debit KPI | **Yes** |
| B2B receivables report / export | **Yes** (rewritten to SSOT aggregation) |
| Collections report | N/A (payments only; no double-count) |
| Driver cash custody / settlements | Unchanged; not revenue |

Issuing a debit **never** creates a customer payment.

---

## 8. Reconciliation results (automated)

Representative assertions from `DebitAdjustmentTest` + reporting suite:

| Check | Result |
|-------|--------|
| Invoice details vs debit doc totals | PASS |
| Original invoice totals immutable | PASS |
| Payment ledger unchanged on issue | PASS |
| Credit + debit net | PASS |
| Outstanding = SSOT remaining | PASS |
| Dashboard / outstanding reports use SSOT | PASS |
| B2B outstanding uses SSOT (not naive total−payments) | PASS |
| Driver custody regression | PASS |
| Invoice-independent delivery regression (`OrderManualTransitionTest`) | PASS |

---

## 9. Automated tests

**Commands**

```text
php artisan migrate --force
php artisan test --filter=DebitAdjustmentTest
php artisan test --filter=GeneralHouse
php -l app/Services/GeneralHouse/Corrections/GhDebitAdjustmentService.php
php -l app/Http/Controllers/GeneralHouse/DebitAdjustmentController.php
vendor/bin/pint --dirty
php artisan route:list --name=gh.debit
```

**Results**

| Suite | Result |
|-------|--------|
| `DebitAdjustmentTest` (7 cases covering scenarios 1–17) | PASS |
| Full `GeneralHouse` filter | **339 passed** |
| Driver custody / financial corrections / invoice UI | PASS |

Mapped scenarios:

1–2 COMPLETED + immutable original · 3–5 additional / qty / price · 6 VAT · 7–9 paid/partial/unpaid · 10 multiple · 11 CN+DA · 12–13 idempotent / concurrent issue · 14 driver 403 · 15 zero rejected · 16–17 SSOT / no duplicate payment · 18–19 custody + invoice-independent delivery (regression suite) · 20 full GH suite green.

---

## 10. Remaining risks / notes

| Item | Status |
|------|--------|
| LIVE deploy | Not done (TEST only) |
| Git commit / push | Not done (awaiting approval) |
| Preview API as separate endpoint | Preview exists in service; UI issues after form review (no separate AJAX preview) |
| Draft-only workflow UI | Supported in service; primary CTA is issue-now |
| Customer statement export (if any beyond invoices/outstanding/B2B) | Uses SSOT where remaining is shown; no separate statement module found beyond reports |
| Void-and-reissue | Explicitly **not** implemented |

---

## 11. Acceptance gates

```text
DATABASE CHANGES: YES
SCHEMA MIGRATIONS CREATED: YES
DATA MIGRATION OR BACKFILL: NO
PRODUCTION DEPLOYMENT PROCEDURE: BLOCKED (TEST only; no LIVE)
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

```text
Automated tests: PASS
UI/UX (AR+EN keys, RTL/LTR-capable Blade, responsive Bootstrap): PASS
Next task allowed: yes (await owner for commit / LIVE)
Final: PASS
```

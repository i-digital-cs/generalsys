# GH-9 — Financial Integrity Audit

**Phase:** GH-9 (audit only)  
**Date:** 2026-10-31  
**Scope:** GH-5 invoice engine + GH-6 payment ledger + GH-8 reporting consumers  
**Method:** code review, config inspection, schema constraints, automated GH payment/invoice tests. **Unit tests ≠ proof of absolute financial correctness** — edge gaps listed explicitly.

---

## 1. Single sources of truth

| Concern | Authority | Consumers |
|---|---|---|
| Line/discount/VAT/min-order math | `GhInvoiceCalculationEngine` | Draft update, issuance |
| Money arithmetic | `GhMoney` (bcmath, ROUND_HALF_UP, 2 dp) | Engine, payments, reports |
| Payment balance / invoice payment_status | `GhPaymentBalanceService` | PaymentService sync, driver hint, reports |

**Finding FIN-OK-1** — Severity: **Info**  
Remaining balance uses **CONFIRMED** only (`GhPaymentBalanceService.php:31–33, 68–76`). Pending excluded. **PASS** vs requirement.

---

## 2. Decimal precision & rounding

| Field | Detail |
|---|---|
| Severity | **Info** (positive) |
| Evidence | Schema DECIMAL money columns; `GhMoney::roundHalfUp` uses bcmath (`app/Support/GeneralHouse/GhMoney.php`). VAT rate config `standard_vat_rate` default `5.00`. |
| Affected | All GH money paths |
| Business impact | Avoids float drift if callers use GhMoney |
| Recommended correction | Never introduce float intermediates in new code |
| Blocks LIVE | No |

**Finding FIN-E1** — Severity: **LOW**  
**Evidence:** `GhMoney::of(float)` uses `number_format` — float inputs can already be imprecise before rounding.  
**Affected:** any caller passing PHP floats.  
**Business impact:** rare if Form Requests pass strings.  
**Recommended correction:** prefer string decimals from HTTP; ban float in money APIs (static analysis).  
**Blocks LIVE:** No.

---

## 3. Discount allocation & VAT

| Field | Detail |
|---|---|
| Severity | **Info** (positive with caveat) |
| Evidence | Engine computes service after discounts, VAT by tax class, snapshots on ISSUE (`GhInvoiceIssuanceService`). Issued lines frozen per FINANCIAL_RULES §5–6. |
| Affected | Invoice builder |
| Business impact | Correctness depends on tax class assignment per line |
| Recommended correction | Keep regression fixtures in `GhInvoiceCalculationEngineTest` |
| Blocks LIVE | No for STANDARD_RATED-only catalogs; review if mixed zero-rated lines used |

**Finding FIN-E2** — Severity: **MEDIUM** (edge)  
**Evidence:** Mixed tax-class discount allocation edge cases should be confirmed against accountant expectations (proportional vs line-local). Tests cover primary paths; not a substitute for tax advice.  
**Affected:** Multi-class invoices  
**Business impact:** Possible VAT basis disputes if B2C/B2B mix tax classes with header discounts  
**Recommended correction:** Owner/accountant sample 3 mixed invoices before LIVE; document expected allocation in DECISION_LOG  
**Blocks LIVE:** Conditional if mixed tax classes are in launch catalog

---

## 4. Minimum-order surcharge tax (`C-GH-TAX-MIN`)

| Field | Detail |
|---|---|
| Severity | **HIGH** (business / tax) |
| Evidence | `config('general_house.min_order_surcharge_tax_class')` = **null**. Engine adds `min_order_tax_unresolved` when adjustment > 0 (`GhInvoiceCalculationEngine.php:421–428`). Issuance reads same config (`GhInvoiceIssuanceService.php:93`). |
| Affected | ISSUE when zone min-order SURCHARGE applies |
| Business impact | Cannot issue invoices that require surcharge until tax class set — **fail-closed** (safe). Or wrong VAT if set incorrectly. |
| Recommended correction | Owner/accountant decision — see `GH9_OPEN_DECISIONS.md` |
| Blocks LIVE | **Yes** for any zone/policy that produces surcharge; **No** if launch zones never surcharge or use VALIDATION_ONLY / waive |

---

## 5. Invoice snapshots & immutability

| Field | Detail |
|---|---|
| Severity | **Info** (positive) |
| Evidence | `GhInvoiceService::updateDraft` rejects non-draft (`invoice_immutable`, lines 120–123). Issuance locks invoice+order, sets ISSUED, snapshots totals. Unique `uq_gh_invoice_order`, `uq_gh_invoice_number`. |
| Affected | Draft/issue lifecycle |
| Business impact | Prevents silent edit of issued totals via draft path |
| Recommended correction | Keep void/credit-note as separate approved workflow (`C-GH-CORR`) |
| Blocks LIVE | No |

**Finding FIN-E3** — Severity: **MEDIUM**  
**Evidence:** Corrections (void / credit note / refund) designed but not implemented (`FINANCIAL_RULES.md` §7; `refunds_enabled=false`).  
**Affected:** Post-issue error handling  
**Business impact:** Wrong issued invoice cannot be corrected in-app; operational workaround only (manual process).  
**Recommended correction:** Accept as launch limitation; escalate C-GH-CORR post-launch  
**Blocks LIVE:** No if owner accepts “no in-app corrections”

---

## 6. Payments — balance, partial, concurrency, idempotency

### 6.1 Pending must not reduce outstanding — **PASS**

Evidence: `forInvoice` remaining = total − confirmed (`GhPaymentBalanceService.php:33`). Online pending test: `PaymentTest` “online pending does not affect balance”.

### 6.2 Confirmed cannot exceed invoice total — **PASS** (flag)

Evidence: `GhPaymentService` projects confirmed+amount vs invoice_total; rejects when `overpayment_allowed=false` (default) (~195–203).

### 6.3 Partial payments — **PASS** (enabled)

`partial_payments_enabled=true`; PARTIAL status when 0 < confirmed < total.

### 6.4 Concurrent collections — **PASS** design

`lockForUpdate` on idempotency key + invoice inside transaction (`GhPaymentService.php:160–167`). MySQL InnoDB row locks.

**Finding FIN-E4** — Severity: **LOW**  
**Evidence:** Concurrent integration test under load not run in GH-9 (audit-only; no soak).  
**Affected:** Peak cash desk  
**Business impact:** Theoretical race if locks mis-ordered elsewhere; current order looks safe  
**Recommended correction:** Optional concurrent feature test post-approval  
**Blocks LIVE:** No for expected volume

### 6.5 Idempotency — **PASS**

Unique `uq_gh_payment_idempotency`; unique `(invoice_id, external_ref)`; service returns existing on key hit. Covered by PaymentTest overpayment/idempotent retry.

### 6.6 Payment status independent manipulation — **PASS** (application path)

`syncInvoicePaymentStatus` recomputes from ledger. No controller endpoint to set `payment_status` directly observed. Residual: SEC-H2 fillable surface.

### 6.7 Delivery vs payment coupling — **PASS** (product rule)

Delivery confirmation allowed while unpaid (`PaymentTest`). Finance not blocking logistics — intentional.

---

## 7. Feature flags (launch matrix)

From `config/general_house.php` payments:

| Flag | Value | Launch implication |
|---|---|---|
| `cash_enabled` | true | Primary collection path |
| `online_initiation_enabled` | true | Creates PENDING only |
| `online_gateway_confirm_enabled` | **false** | No online settlement |
| `bank_transfer_enabled` | false | Disabled |
| `partial_payments_enabled` | true | Allowed |
| `overpayment_allowed` | false | Hard reject |
| `refunds_enabled` | false | Disabled |
| `driver_collection_enabled` | false | Drivers cannot collect |

**Finding FIN-E5** — Severity: **MEDIUM**  
**Evidence:** Online initiation enabled while gateway confirm disabled.  
**Affected:** UX / ledger hygiene  
**Business impact:** Operators may think online payment “started” equals paid  
**Recommended correction:** Disable initiation for launch **or** train staff + UI labels PENDING ≠ paid  
**Blocks LIVE:** Conditional (owner UX decision)

---

## 8. Reporting reconciliation

| Report | Confirmed source | Risk |
|---|---|---|
| Collections | CONFIRMED + `confirmed_at` in period | PASS |
| Outstanding | ISSUED + UNPAID/PARTIAL | PASS if `payment_status` synced |
| Invoice list map | Per-invoice SUM (N+1) | Performance; math matches SSOT helpers |
| B2B grouped | JOIN subquery SUM | Prefer this pattern |

**Finding FIN-E6** — Severity: **MEDIUM**  
**Evidence:** `payment_status` column denormalized; sync on payment flows. Reports also filter on column (`outstandingBase`). If historical rows ever desynced, outstanding list drifts until re-sync.  
**Affected:** Outstanding report  
**Business impact:** Misstated AR aging  
**Recommended correction:** Periodic reconcile command (future) comparing column vs SUM(CONFIRMED); or always compute status in query  
**Blocks LIVE:** No at low volume if sync paths covered

**Finding FIN-E7** — Severity: **LOW**  
**Evidence:** Blade outstanding view may call `confirmedForInvoice` again (duplicate queries).  
**Affected:** Performance only  
**Business impact:** Slow page  
**Recommended correction:** Pass precomputed row DTOs only  
**Blocks LIVE:** No

---

## 9. Unauthorized financial mutations

| Path | Guard |
|---|---|
| Cash record | `GhPaymentPolicy::recordCash` + issued invoice |
| Online init | `initiateOnline` + flag |
| Cancel | pending only |
| Driver payment | Forbidden (tested) |
| Draft edit after issue | ValidationException immutable |
| Gateway confirm | Hard disabled |

---

## 10. Missing edge cases (explicit)

1. **C-GH-TAX-MIN** unresolved — surcharge ISSUE blocked.  
2. **Refunds / credit notes / voids** — absent.  
3. **Bank transfer / driver collection** — disabled.  
4. **Overpayment** — rejected; no credit-balance customer wallet.  
5. **Zero-total invoices** — treated PAID when total=0 (`forInvoice` lines 39–41); confirm business wants that.  
6. **Currency** — AED assumed; multi-currency deferred (FINANCIAL_RULES §9).  
7. **Concurrent double-submit without idempotency key** — could create two payments if client omits key; unique refs help; ensure UI always sends key.  
8. **Issued invoice line tax class change** — blocked by immutability; good.  
9. **Partial then full with rounding remainder 0.01** — rely on half-up; needs fixture if many line VATs.  
10. **Reporting period TZ** — periods should use Dubai business day boundaries (verify `GhReportFilters` / period helper vs UTC storage).

**Finding FIN-E8** — Severity: **MEDIUM**  
**Evidence:** Need confirmation that report period start/end convert Dubai calendar days → UTC correctly for `issued_at` / `confirmed_at` filters.  
**Affected:** Daily/monthly revenue & collections  
**Business impact:** Border-hour mis-binning (UTC vs Dubai)  
**Recommended correction:** Add explicit period-boundary unit tests if not already present in DashboardReportingTest  
**Blocks LIVE:** Conditional until verified in DashboardReportingTest / TIMEZONE docs

---

## 11. Reconciliation statement (launch)

With cash-only CONFIRMED collections and fail-closed surcharge tax:

- Books should reconcile: `SUM(confirmed payments) ≤ SUM(issued totals)` per invoice; equality ⇒ PAID.  
- Pending online must be excluded from cash reports (collections query filters CONFIRMED).  
- Do **not** treat automated tests alone as tax or audit certification.

---

## Blocks LIVE (financial)

1. **C-GH-TAX-MIN** if surcharge used at launch.  
2. Owner acceptance of **no refunds/credit notes**.  
3. Owner acceptance of **cash-primary** (gateway off).  
4. Optional: disable online initiation to avoid PENDING confusion.

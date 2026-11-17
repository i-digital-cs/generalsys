# GH — COMPLETED Order Invoice Correction Audit (Phase 4 prep)

**Environment:** TEST only  
**Date:** 2026-11-15  
**Status:** AUDIT ONLY — no financial mutations implemented  
**LIVE / commit / push / production data:** not performed

---

## Task C verdict: **PASS** (audit complete; implementation deferred)

Owner asked for the ability to correct an invoice even when its order is COMPLETED. This audit shows:

- Order `COMPLETED` is **not** an invoice-edit gate.
- Immutability is driven by invoice status **`ISSUED`**.
- Missing/draft invoices on COMPLETED orders can already be created/edited/issued via existing services.
- Over-billing after issue is correctable today via GH-10 credit notes (+ refunds).
- **Under-billing / adding lines / raising totals after ISSUED** has **no** in-app document path and needs a new audited design before any Phase 4 implementation.

---

## 1. Existing invoice edit restrictions

| Gate | Rule | Location |
|------|------|----------|
| Policy update/issue | Manager + `invoice->isDraft()` | `app/Policies/GeneralHouse/GhInvoicePolicy.php` |
| Service update | `!isDraft()` → `gh.errors.invoice_immutable` | `GhInvoiceService::updateDraft` |
| Issue | Draft only; COMPLETED allowed with pickup | `GhInvoiceIssuanceService::issue` |
| Create draft | WORKING / DELIVERY / **COMPLETED** + `picked_up_at` | `GhInvoiceService::createDraftFromOrder` |
| Mass assignment | Empty `$fillable` on `GhInvoice` | Model |
| Payments | Require `ISSUED`; never rewrite invoice totals | `GhPaymentService::recordPayment` |
| Credit notes | Require `ISSUED`; quantity-share ↓ only | `GhCreditNoteService` |
| FC EXECUTE | Config-gated off by default | `GhFinancialCorrectionService` + `GhLaunchConfig` |

**Answer:** Editing invoices does **not** check order `COMPLETED`. Edit gate = draft. COMPLETED is an **allowed** status for create/issue when pickup is confirmed.

Statuses in code: `DRAFT` | `ISSUED` only. Design mentions of `VOID` / `gh_invoice_corrections` are **not implemented**.

---

## 2. Balance SSOT (must preserve)

`GhPaymentBalanceService::forInvoice`:

```text
G = total_payable (immutable after issue)
C = Σ ISSUED credit notes
N = max(0, G − C)
P = Σ CONFIRMED payments
R = Σ PAID_OUT refunds
V = Σ EXECUTED payment_recording_error corrections
CashNet = max(0, P − R − V)
remaining_due = max(0, N − CashNet)
customer_credit = max(0, CashNet − N)
```

Silent rewrite of issued `G` / VAT would desync CN shares and period KPIs — **forbidden**.

---

## 3. Proposed correction matrix

| Scenario | Safe via existing services TODAY? | Allowed now | Needs new audited document? |
|----------|-----------------------------------|-------------|------------------------------|
| **1. COMPLETED + no invoice** | **Yes** | Create draft → edit → issue → collect | No |
| **2. COMPLETED + DRAFT** | **Yes** | Full draft edit + issue | No |
| **3. COMPLETED + ISSUED unpaid** | **Partial** | Collect payment; CN for over-bill only; **cannot** edit lines | **Yes** if need ↑ amount / add lines / void-reissue |
| **4. COMPLETED + PARTIAL paid** | **Partial** | Collect remaining; CN for over-bill; refund only after credit | Same upward gap |
| **5. COMPLETED + FULLY paid** | **Partial** | CN → customer credit → refund payout; invoice rows immutable | Same; SoD for refunds |
| **6. COMPLETED + refunds / CN / FC** | **Partial** | Further CN until C≤G; refunds ≤ credit; FC request OK, EXECUTE owner-gated | Policy for stacked corrections; upward still missing |

Legend: **Partial** = settlement / over-bill paths exist; **under-bill** does not.

---

## 4. Exact files / services involved

```text
app/Services/GeneralHouse/Invoices/GhInvoiceService.php
app/Services/GeneralHouse/Invoices/GhInvoiceIssuanceService.php
app/Services/GeneralHouse/Invoices/GhInvoiceCalculationEngine.php
app/Services/GeneralHouse/Payments/GhPaymentService.php
app/Services/GeneralHouse/Payments/GhPaymentBalanceService.php
app/Services/GeneralHouse/Payments/GhDriverDeliveryPaymentService.php
app/Services/GeneralHouse/Corrections/GhCreditNoteService.php
app/Services/GeneralHouse/Corrections/GhRefundService.php
app/Services/GeneralHouse/Corrections/GhFinancialCorrectionService.php
app/Services/GeneralHouse/Orders/GhOrderStateService.php          # markDeliveryAfterInvoice
app/Http/Controllers/GeneralHouse/InvoiceController.php
app/Policies/GeneralHouse/GhInvoicePolicy.php
app/Http/Requests/GeneralHouse/UpdateGhInvoiceDraftRequest.php
config/general_house.php                                         # CN/refund ON; FC execute OFF
docs/general-house/GH10_*.md
docs/DATABASE_SCHEMA.md                                          # §GH-5/6/10
```

Correction tables already exist: `gh_credit_notes*`, `gh_refunds*`, `gh_financial_corrections*`  
(`2026_10_31_180000_create_gh_financial_correction_tables.php`).

**Not present:** debit notes, void invoices, supplemental invoices, `gh_invoice_corrections` linkage.

---

## 5. Required migrations (propose only — do not create)

| If owner wants… | Proposed forward migration | Notes |
|-----------------|----------------------------|-------|
| Void + reissue | Invoice `VOID` (or soft flags); `gh_invoice_corrections`; relax `UNIQUE(order_id)` to one active DRAFT/ISSUED | Retain numbers; exclude VOID from G in reports |
| Debit / supplemental | `gh_debit_notes` (+ lines/events) **or** secondary invoice with `parent_invoice_id` | Balance SSOT must add debit into net due |
| Amendment header | Separate amendment referencing issued invoice; never mutate G | Prefer over silent edit |
| FC EXECUTE only | **None** — flip `GH_PAYMENT_CORRECTION_EXECUTE_ENABLED` after policy | Accounting decision |

Also update schema doc, GH10 balance invariants, reporting KPIs, AR/EN keys **when** implementation is approved.

---

## 6. Financial risks

1. Operators may assume COMPLETED “locks” drafts — it does not.
2. Under-billing after ISSUE has no safe in-app fix → pressure for forbidden DB edits.
3. Full CN on paid invoice creates customer credit; refund needs two-manager SoD.
4. FC EXECUTE off → wrong CONFIRMED cash stuck until policy.
5. VAT/FTA treatment of credit notes not certified in-app.
6. Cross-month CN vs invoice issuance timing is an open GH10 reporting topic.
7. Reopen COMPLETED→DELIVERY must never duplicate collections (Phase 3.1 guards; see Task B).

---

## 7. Owner decisions required before Phase 4 implementation

1. Is **under-billing after COMPLETED+ISSUED** in scope? If yes → void-reissue vs debit note vs external journal.
2. Enable **payment_correction_execute**? Accounting treatment of V.
3. Keep **one primary invoice per order** forever?
4. Optionally **block draft edits when order is COMPLETED**? (product choice; not coded today)
5. Who may approve CN / refund / void (manager vs accountant)?
6. Accept current CN VAT math for UAE compliance?

---

## 8. Recommended Phase 4 scope (when approved)

**Do now without new schema (SOP / UI clarity only):**
- Document that COMPLETED + missing/draft invoice uses existing create/edit/issue.
- Surface credit-note / refund entry points from COMPLETED order/invoice screens if missing.

**Do not implement until owner picks a path:**
- Any mutation of issued `total_payable` / line VAT snapshots.
- Void-reissue or debit notes.
- Enabling FC EXECUTE without accounting policy.

---

## Critical guard quotes

```text
# Invoice immutable
if (! $locked->isDraft()) {
    throw … __('gh.errors.invoice_immutable');
}

# Policy
return GhCatalogAccess::mayManage($user) && $invoice->isDraft();

# COMPLETED allowed for draft/issue
in_array($status, [WORKING, DELIVERY, COMPLETED], true)

# Payments need ISSUED
if ($lockedInvoice->status !== ISSUED) { … payment_requires_issued }

# CN needs ISSUED; qty-share reductions only
# FC execute blocked until owner policy
if (! GhLaunchConfig::paymentCorrectionExecuteEnabled()) { … }
```

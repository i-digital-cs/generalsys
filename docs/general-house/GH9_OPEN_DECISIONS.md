# GH-9 — Open Decisions Memo

**Phase:** GH-9 (audit only)  
**Date:** 2026-10-31  
**Audience:** Owner + accountant  
**Rule:** Do **not** treat this document as legally binding tax advice. Classifications require owner/accountant approval.

---

## Decision index

| ID | Topic | Launch blocker? | Can stay disabled? |
|---|---|---|---|
| **C-GH-TAX-MIN** | Min-order surcharge tax class | **Yes** if surcharge used | Fail-closed blocks ISSUE |
| C-GH-CORR | Void / credit note / refund | No | **Yes** (disabled) |
| C-GH-PAY-ONLINE | Gateway confirm | No for cash launch | **Yes** (disabled) |
| C-GH-PAY-BANK | Bank transfer | No | **Yes** |
| C-GH-PAY-DRIVER | Driver collections | No | **Yes** |
| C-GH-PAY-INIT | Online initiation while gateway off | Soft | Recommend disable for launch |
| C-GH-OVERPAY | Overpayment / customer credit | No | Reject overpay (current) |
| C-GH-ACL | Fine-grained GH ACL | No | Manager-only OK |
| C-GH-REL | Release isolation vs Attendance | **Yes** | N/A |
| C-GH-ZERO | Zero-total invoice = PAID | Soft | Confirm intent |

---

## C-GH-TAX-MIN — Minimum-order surcharge tax treatment

### Current behavior

- Config: `general_house.min_order_surcharge_tax_class` / env `GH_MIN_ORDER_SURCHARGE_TAX_CLASS` = **null**.  
- When SURCHARGE adjustment amount > 0 and tax class unresolved → calculation sets `blocks_issuance` with reason `min_order_tax_unresolved` (`GhInvoiceCalculationEngine.php:421–428`).  
- Issuance path respects unresolved class (`GhInvoiceIssuanceService.php:93`).  
- **Fail-closed:** cannot silently issue untaxed/mis-taxed surcharge.

### Available implementation options (examples — not recommendations as law)

| Option | Meaning | Financial implication |
|---|---|---|
| **A. STANDARD_RATED (5%)** | Surcharge taxed like standard services | Increases VAT output; customer pays tax on uplift |
| **B. Inherit dominant line tax class** | Match majority/primary service lines | Complex; mixed invoices ambiguous |
| **C. OUT_OF_SCOPE / EXEMPT** | No VAT on surcharge | Lower customer total; must be defensible under UAE VAT rules |
| **D. Disallow surcharge at launch** | Use VALIDATION_ONLY or waive / no min policy | Avoids decision; may lose min-order economics |
| **E. Keep null** | Keep ISSUE blocked when surcharge > 0 | Safest code default; blocks some orders from invoicing |

### Required approval

- Owner + UAE VAT-qualified accountant sign-off recorded in `DECISION_LOG.md`.  
- Then set env/config **only** after approval.  
- Auditor must **not** independently declare a legally binding classification.

### Recommended safe default (engineering)

**Keep null (Option E) until accountant sign-off**, OR launch with **Option D** (no surcharge issuance path).  
Do **not** default to STANDARD_RATED in production without approval merely to “unblock” ISSUE.

### Blocks LIVE

**Yes** for zones/policies that produce surcharge > 0.  
**No** if launch catalog never triggers SURCHARGE adjustment.

---

## C-GH-CORR — Refunds, credit notes, payment reversals

| Mechanism | Status | Launch |
|---|---|---|
| Void issued invoice | Not implemented | Operate manually / postpone |
| Credit note | Not implemented | Postpone |
| Refund (ledger outbound) | `refunds_enabled=false` | Keep disabled |
| Payment reverse of CONFIRMED | Not a supported happy path | Keep |

**Recommendation:** Launch without in-app corrections; document manual escalation SOP. Future enhancement after rules approved (`FINANCIAL_RULES.md` §7).

**Blocks LIVE:** No (feature remains disabled).

---

## C-GH-PAY-ONLINE / BANK / DRIVER

| Feature | Flag | Recommendation |
|---|---|---|
| Online gateway confirm | `false` | Remain disabled until PSP + webhook design approved |
| Bank transfer | `false` | Remain disabled |
| Driver collection | `false` | Remain disabled (security: drivers see payment **hint** only) |

**Blocks LIVE:** No.

---

## C-GH-PAY-INIT — Online initiation without settlement

**Current:** initiation enabled; confirm disabled → PENDING rows never auto-settle.

**Options:**

1. Disable initiation for launch (cleanest).  
2. Keep initiation for “send payment link later” UX with staff training.  

**Recommendation:** Disable initiation until gateway exists.  
**Blocks LIVE:** Soft / Conditional.

---

## C-GH-OVERPAY

**Current:** `overpayment_allowed=false` — reject.  
**No** customer credit wallet.  
**Recommendation:** Keep reject.  
**Blocks LIVE:** No.

---

## C-GH-ACL

Manager-only GH finance. Accountant/Assistant not granted GH manage.  
**Recommendation:** Accept for single-Manager launch; revisit multi-user.  
**Blocks LIVE:** No.

---

## C-GH-REL — Git release isolation

Ship dedicated GH release; do not assume Attendance RC tip is LIVE-ready.  
See `GH9_RELEASE_ISOLATION_PLAN.md`.  
**Blocks LIVE:** **Yes** until strategy chosen and artifact built.

---

## C-GH-ZERO — Zero-total invoices

`GhPaymentBalanceService` marks PAID when `total_payable == 0`.  
Confirm this matches complimentary / 100% discount business intent.  
**Blocks LIVE:** Soft.

---

## Launch blockers vs disabled vs future

### Launch blockers (must decide / clear)

1. **C-GH-TAX-MIN** (if surcharge in scope)  
2. **C-GH-REL** release isolation  
3. LIVE cron verification (ops)  
4. Payment launch matrix sign-off (cash vs online-init)  
5. Full-suite policy vs DriverRoleTest update  

### May remain disabled at launch

- Gateway confirm, bank transfer, driver collection, refunds, credit notes, voids  

### Future enhancements

- Fine-grained ACL  
- Correction workflows  
- Report N+1 elimination  
- Concurrent soak tests  
- Multi-currency  

---

## Decision request to owner (copy/paste)

Please reply with:

1. `C-GH-TAX-MIN`: A / B / C / D / E (+ accountant name/date if A–C)  
2. Online initiation at launch: keep / disable  
3. Release strategy: A (dedicated from LIVE base) / B (Attendance then GH) / C (combined — discouraged)  
4. Accept no in-app refunds/credit notes at launch: yes / no  
5. Full suite must be green before LIVE: yes / no  

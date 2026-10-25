# General House — Phase 0 Design Change Summary

**Date:** 2026-10-25  
**Owner review verdict:** CONDITIONAL APPROVAL (architecture accepted with mandatory revisions)  
**Code / migrations:** **none** — documentation revision only

---

## Classification legend

| Label | Meaning |
|---|---|
| **APPROVED** | Locked for design; safe to implement when Phase 1+ is authorized |
| **PROPOSED** | Engineering recommendation; not blocking if unchanged |
| **REQUIRES OWNER CONFIRMATION** | Must be answered before the named phase’s financial/ops coding |

---

## APPROVED

| Topic | Decision |
|---|---|
| Module home | General house (`service`); not Mohra Gross; not a separate app |
| Schema | Independent `gh_*` domain; no Gross financial/ops table reuse |
| Patterns | Reuse Form Requests, Policies, sequences pattern, DECIMAL money, Hostinger constraints |
| Preserve | HR, Payroll, Mohra Gross, existing valuation |
| Catalog | Categories → Products → Treatments → **Offerings** (unique product×treatment) + standard prices + B2B contract prices |
| Order catalog span | Architecture must allow **multiple service categories / offerings per order** |
| Drivers | Existing `users` + `DRIVER` role; **independent pickup vs delivery assignment**; full assignment history with responsibility |
| Happy-path states | `LATER → PICKUP → WORKING → DELIVERY → COMPLETED` |
| Eligibility | Pickup eligible from **T−24h** before pickup window start |
| Pickup confirm | → `WORKING` |
| Invoice ISSUE | → `DELIVERY` (draft alone does not) |
| Delivery confirm | → `COMPLETED` |
| Payment vs logistics | Independent |
| Exceptions | Cancel, reschedule, failed pickup/delivery attempts, reassignment — prefer **events**, not extra primary states |
| Currency | **AED** |
| Price mode | **Tax exclusive** |
| Default VAT | **5%** (configurable rate; not a legal opinion) |
| Rounding | **HALF_UP** to 2 dp |
| Money storage | `DECIMAL` only; no float |
| Tax classes | `STANDARD_RATED` \| `ZERO_RATED` \| `EXEMPT` \| `OUT_OF_SCOPE` — valid rules only; not via discount toggle |
| Discount pipeline | qty×price → item discount → sum → invoice discount (allocated) → min-order adj → VAT → payable |
| Min-order policies | Per zone: `VALIDATION_ONLY` \| `SURCHARGE`; overrides `APPLY_DEFAULT` \| `OVERRIDE_AMOUNT` \| `WAIVE` |
| Min-order default | **SURCHARGE** |
| Min threshold base | Eligible service value **after discounts, before VAT** |
| B2B | Restricted catalog; contract price overrides; validity periods; freeze on issue |
| Invoice v1 | **One primary invoice per order**; unique/txn-safe ISSUE; no silent edit of ISSUED |
| Payments | **GH-specific ledger only**; cash + online + partial; no Gross treasury writes; online success only via verified confirmation |

---

## PROPOSED

| Topic | Proposal |
|---|---|
| Product→category | Product still has **one primary category** for admin UX; multi-category **orders** via multiple lines/offerings |
| Route prefix | `/service/gh/...`, names `gh.*` |
| References | Orders `GH-ORD-######`; invoices `GH-INV-YYYYMM-####` |
| ACL | `gh_user_pages` (Gross-like) for non-Manager |
| Timestamps | Store `occurred_at` (operational) separately from `created_at` (record) on events/attempts |
| Corrections design | Tables/status hooks for VOID / CREDIT_NOTE / REFUND — **implement only after detailed rules approved** |
| Online gateway | Integration-ready stubs in Phase 6; no fake PAID |

---

## REQUIRES OWNER CONFIRMATION (blocking)

| ID | Question | Blocks |
|---|---|---|
| **C-GH-TAX-MIN** | Tax class / rate for **minimum-order surcharge** (must not assume exempt). Options: same as predominant line class, always `STANDARD_RATED` @ 5%, or configurable per zone | Phase 5 financial engine |
| **C-GH-CORR** | Detailed **void / credit note / refund** rules (who, tax reverse, numbering, link to original invoice) | Correction workflow implementation (after Phase 5 core) |
| **C-GH-CANCEL** | Who may cancel; from which statuses; whether cancellation is allowed after ISSUE | Phase 4 |
| **C-GH-MULTI-CUR** | Launch **AED-only** vs allow EGP invoices later | Phase 1 defaults / Phase 5 |

Non-blocking reminders: these defaults are **commercial/system configuration**, not legal tax advice.

---

## Docs updated this pass

- `DATABASE_DESIGN.md`
- `STATE_MACHINE.md`
- `FINANCIAL_RULES.md`
- `ARCHITECTURE.md`
- `DECISION_LOG.md`
- `RISK_REGISTER.md`
- `PHASE_0_AUDIT.md` (status footer)
- This file

---

## Execution gate

**STOP.** No migrations, models, controllers, services, or UI until explicit authorization to start **Phase 1**.

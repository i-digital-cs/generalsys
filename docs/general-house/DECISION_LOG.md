# General House — Decision Log (Phase 0, revised)

Status keys: `OPEN` | `PROPOSED` | `APPROVED` | `REJECTED`  
Cross-ref: `DESIGN_CHANGE_SUMMARY.md` (owner CONDITIONAL APPROVAL 2026-10-25).

| ID | Topic | Decision / proposal | Status | Blocks |
|---|---|---|---|---|
| D-GH-001 | Module home | Build under **General house** (`service`); not Mohra Gross; not a separate app | **APPROVED** | — |
| D-GH-002 | Table prefix | Independent `gh_*`; no reuse of Gross financial/ops tables | **APPROVED** | — |
| D-GH-003 | Product–category | Product has **one primary** category for admin UX; **orders** may span multiple categories via lines | **APPROVED** (orders multi-cat); primary category **PROPOSED** | — |
| D-GH-004 | Min-order policy | Per zone: `VALIDATION_ONLY` \| `SURCHARGE`; overrides `APPLY_DEFAULT` \| `OVERRIDE_AMOUNT` \| `WAIVE`; **default SURCHARGE**; threshold = after discounts, before VAT | **APPROVED** (policy shape) | — |
| D-GH-004b | Min-order tax | Tax class of surcharge must be explicit — **not** assumed exempt | **OPEN** → **C-GH-TAX-MIN** | Phase 5 engine |
| D-GH-005 | Calculation order | qty×price → item discount → sum → invoice discount (allocate) → min adj → VAT → payable; one server engine; no negative totals; deterministic rounding | **APPROVED** | — |
| D-GH-006 | VAT / price mode | Tax exclusive; default rate **5%**; currency **AED**; rounding **HALF_UP**; DECIMAL only | **APPROVED** (product defaults — not legal validation) | — |
| D-GH-007 | Tax classification | `STANDARD_RATED` \| `ZERO_RATED` \| `EXEMPT` \| `OUT_OF_SCOPE`; rules-based; **not** via discount toggle | **APPROVED** | — |
| D-GH-008 | Online payments | Initiation ≠ success; verified confirmation only | **APPROVED** | — |
| D-GH-009 | Cash / treasury | **GH payment ledger only**; never write Gross financial tables | **APPROVED** | — |
| D-GH-010 | Drivers | Existing `users` + DRIVER permissions; **independent pickup vs delivery** assignment + history | **APPROVED** | — |
| D-GH-011 | Exception handling | Cancel, reschedule, failed pickup/delivery, reassignment — prefer **events/attempts**, not extra primary states | **APPROVED** (pattern) | Cancel detail: **C-GH-CANCEL** |
| D-GH-012 | Invoice corrections | ISSUED immutable; VOID / CREDIT_NOTE / REFUND designed; **implement only after detailed rules** | Design **APPROVED**; rules **OPEN** → **C-GH-CORR** | Correction workflows |
| D-GH-013 | Multi-currency | Launch AED-only vs later EGP | **OPEN** → **C-GH-MULTI-CUR** | Phase 1/5 defaults |
| D-GH-014 | Rounding | HALF_UP to 2 dp | **APPROVED** | — |
| D-GH-015 | Order ref format | `GH-ORD-000001` (global sequence) | **PROPOSED** | Phase 3 |
| D-GH-016 | Invoice number | `GH-INV-YYYYMM-####` month sequence | **PROPOSED** | Phase 5 |
| D-GH-017 | Pickup eligibility | Eligible from **24h before** pickup window start | **APPROVED** | — |
| D-GH-018 | Happy-path states | `LATER → PICKUP → WORKING → DELIVERY → COMPLETED` with approved triggers | **APPROVED** | — |
| D-GH-019 | Invoice v1 | One primary invoice per order; txn-safe unique ISSUE | **APPROVED** | — |
| D-GH-020 | Catalog offering | Unique product×treatment; standard + B2B contract prices | **APPROVED** | — |

## Blocking confirmations (owner)

| Confirmation ID | Question |
|---|---|
| **C-GH-TAX-MIN** | Tax treatment of minimum-order surcharge |
| **C-GH-CORR** | Void / credit note / refund detailed rules |
| **C-GH-CANCEL** | Cancel eligibility by status / post-ISSUE behavior |
| **C-GH-MULTI-CUR** | AED-only at launch? |

Owner: answer confirmation IDs (or defer with explicit phase gate) before the blocking phase’s coding starts.

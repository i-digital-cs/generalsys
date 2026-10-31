# GH-9.3 — Refund / Credit-Note / Correction Readiness

**Date:** 2026-10-31  
**Scope:** Decision document only. **No** refund, reversal, or credit-note implementation authorized.

Launch flags: `refunds_enabled = false` (fail-closed).

---

## What the application can handle today

| Situation | App capability |
|---|---|
| Draft invoice error before issue | Edit / recreate draft |
| Cancel order before issue (per logistics rules) | Supported when status allows |
| Pending online payment | N/A at launch (initiation disabled); cancel-pending path exists for PENDING records historically |
| Record additional CONFIRMED cash up to remaining | Manager cash collection |
| Reject overpayment | Enforced |
| Immutable issued snapshots | Preserved (no silent rewrite) |

---

## What requires an external approved procedure

| Situation | Gap |
|---|---|
| Incorrect **issued** invoice amounts/lines/VAT | No credit note / reissue workflow |
| Incorrect **CONFIRMED** cash amount | No payment reverse / adjust |
| Customer refund request | No refund posting |
| Customer complaint with money impact | No structured dispute case |

These need **owner + accountant** policy: who may approve, what evidence to keep, how books are corrected **outside** or in a future GH phase.

---

## What cannot be safely handled yet (do not improvise)

1. Editing `gh_payments` CONFIRMED rows by hand.  
2. Editing issued `gh_invoices` money columns.  
3. Deleting payment/invoice history.  
4. Inventing VAT treatment for negative adjustments without tax decision (`C-GH-TAX-MIN` still unresolved for surcharges; refund tax likewise unapproved).

---

## Decision questions for the owner (not answered here)

1. Who is the sole authority to approve a commercial correction (manager vs accountant vs owner)?  
2. Is limited cash-only launch acceptable **without** in-app refunds?  
3. Preferred external process: paper credit note + journal in accounting system, then optional later GH feature?  
4. Required retention: screenshots, payment references, invoice PDF, approval email?

---

## Launch recommendation (product)

**Limited commercial launch can proceed only if** the owner **explicitly accepts** that:

- Wrong confirmed cash / wrong issued invoice / customer refunds are handled **outside** the app under a written SOP.  
- Operators are trained **not** to request DB edits of financial history.

Until that acceptance (or a refunds/credit-notes phase is approved), treat refunds readiness as a **MEDIUM product blocker** for LIVE go-live judgment—not a TEST verification failure.

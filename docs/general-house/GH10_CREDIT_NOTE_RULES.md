# GH-10 — Credit Note Rules

## Eligibility

- Source invoice must be **ISSUED**.
- Feature flag: `GhLaunchConfig::creditNotesEnabled()`.
- At least one line with `quantity_credited ≥ 1`.

## Numbering

- Drafts may have `number = null`.
- On issue: `GH-CN-######` from `gh_sequences.credit_notes` (unique).

## Partial credits

- Quantities are capped by: invoiced qty − already **ISSUED** credited qty for that invoice line.
- Multiple credit notes allowed until cumulative `total_credit ≤ invoice.total_payable`.
- Net obligation after issue: `N = max(0, G − C)` (never negative).

## VAT / tax class

For each credited line, amounts are a **quantity share** of the original invoice line snapshots:

```text
share     = qty_credited / original_qty
line_net  = round(taxable_base × share)
line_vat  = round(tax_amount × share)
line_total = line_net + line_vat
tax_class / tax_rate copied from the invoice line
```

Supported classifications are whatever the original invoice line stored (`STANDARD_RATED`, `ZERO_RATED`, `EXEMPT`, `OUT_OF_SCOPE`, …). Credits do **not** re-infer tax.

**Open (accountant/legal):** UAE e-invoicing / tax invoice field set for credit notes is **not** certified here. Do not claim FTA compliance without an approved checklist.

## Workflow gates

| Action | From | Actor rules |
|---|---|---|
| Submit | DRAFT | Manager |
| Approve | PENDING_APPROVAL | Distinct from submitter when SoD on |
| Reject | PENDING_APPROVAL | Reason required |
| Issue | APPROVED | Assigns number; syncs payment_status |
| Cancel | DRAFT / PENDING / APPROVED | Not after ISSUED |

## Non-effects

- Pending/approved-but-unissued notes do **not** change balances.
- Original invoice rows stay immutable.

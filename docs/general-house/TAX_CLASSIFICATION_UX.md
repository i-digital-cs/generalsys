# Tax classification UX (Owner UAT)

## How tax classes are used today

| Layer | Role |
| --- | --- |
| `gh_product_offerings.tax_class` | Catalog metadata: `STANDARD_RATED`, `ZERO_RATED`, `EXEMPT`, `OUT_OF_SCOPE`. |
| Invoice engine | Applies 5% VAT only when line tax class is `STANDARD_RATED`; prices remain tax-exclusive (AED). |
| Historical invoices | Snapshot tax class / amounts on issue — never rewritten when catalog tax changes. |

Config default: `general_house.default_offering_tax_class` → `STANDARD_RATED`.

## Safe to hide on routine product entry?

Yes for create. Ordinary laundry offerings default to the configured standard taxable class via a hidden field + `StoreGhOfferingRequest` merge. Staff do not pick a class per garment during normal create.

Advanced tax select stays on offering **edit** (collapsed) so exempt / zero-rated / out-of-scope remain available without violating configured rules. Arbitrary free-text tax rates are not allowed — only `GhTaxClass::ALL`.

## VAT visibility

Invoice totals highlight the VAT line (`resources/views/gh/invoices/partials/totals.blade.php`). Line tax class labels remain on invoice show for auditability.

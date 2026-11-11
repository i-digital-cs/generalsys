# GH Card UI conversion progress

**Environment:** TEST only · Updated: 2026-11-11  
**Reference:** Order Board V2.4.2 · Skin: navy `#101044` / yellow `#FFD329` / cream `#F8F7EF`

## Shared system

| Item | Status | Files |
|------|--------|-------|
| Card shell + grid (1→2→3→max4, max ~420px) | Done | `resources/scss/_general-house.scss` (`.gh-card-ui`) |
| Record card component | Done | `resources/views/components/gh/record-card.blade.php` |
| Meta cell component | Done | `resources/views/components/gh/meta-cell.blade.php` |
| Assets | Built | `npm run build` → `public/build/assets/app-*.css` |

## Page checklist

| Route | Previous | New | AR visual | EN visual | Functional | Notes |
|-------|----------|-----|-----------|-----------|------------|-------|
| `gh.orders.index` / driver board | Cards V2.4.2 | REFERENCE | — | — | PASS (OrderBoardV2Test) | Not rebuilt |
| `gh.customers.index` | Table | Cards | PASS (RTL dir + cards) | PASS (3×~358px desktop; 1×~348px mobile) | PASS CardUiConversionTest | Screenshots: `gh-customers-*-{desktop,mobile}*.png` |
| `gh.zones.index` | Table | Cards | PASS (browser) | Pending EN shot | PASS CardUiConversionTest | Create form + edit modal kept |
| `gh.categories.index` | Table | Cards | Code parity | Code parity | Existing catalog tests | |
| `gh.products.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.treatments.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.offerings.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.contracts.index` | Table | Cards | Code parity | Code parity | CustomersZonesContractsTest PASS | |
| `gh.invoices.index` | Table | Cards | PASS (AR browser headings) | Code parity | PASS CardUiConversionTest | Builder/print KEEP_TABLE |
| `gh.payments.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.credit-notes.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.refunds.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.financial-corrections.index` | Table | Cards | Code parity | Code parity | — | |
| `gh.dashboard` recent lists | Tables | Hybrid cards | Code | Code | PASS CardUiConversionTest | KPIs unchanged; lists → cards |
| Reports / invoice builder / import preview / order line editors / history | Tables | KEEP_TABLE | N/A | N/A | N/A | Per audit |

## Automated tests (this change)

```text
php artisan test --filter='CardUiConversionTest|CatalogCrudTest|PaymentTest|FinancialCorrectionsTest'
→ CardUiConversionTest PASS (all converted indexes + dashboard shell)
→ CatalogCrudTest PASS
→ PaymentTest PASS
→ FinancialCorrectionsTest PASS
```

Screenshots skipped per owner request (continue without capture).

## Remaining / owner review

- No commit / push / LIVE deploy until owner approval.
- Optional later: order history / report matrices stay tables unless owner wants mobile card summaries.

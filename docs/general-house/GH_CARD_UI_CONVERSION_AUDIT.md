# GH Card UI conversion audit

**Environment:** TEST only · **Date:** 2026-11-11  
**Visual reference:** Order Board V2.4.2 (navy `#101044` · yellow `#FFD329` · cream `#F8F7EF`)  
**Scope:** Existing General House Laundry pages only — no new features.

## Conversion legend

| Code | Meaning |
|------|---------|
| **CONVERT** | Operational record list table → card grid |
| **REFERENCE** | Already card-based (Order Board V2.4.2) — do not rebuild |
| **KEEP_TABLE** | Table remains (print / line editor / import matrix / dense report) |
| **HYBRID** | Card summary preferred; nested detail table may remain |
| **FORM** | Create/edit form — not a list conversion |
| **N/A** | No record table (settings, download, dashboards KPIs) |

## Inventory (list / index surfaces)

| Page | Route | Roles (typical) | Current layout | Table? | Decision | Notes |
|------|-------|-----------------|----------------|--------|----------|-------|
| Order board (admin) | `gh.orders.index` | Manager/Assistant | Cards + area groups | No | **REFERENCE** | V2.4.2 — preserve DnD rules N/A for admin |
| Driver workspace | `gh.driver.dashboard` | Driver | Cards + Sortable | No | **REFERENCE** | Preserve reorder persistence |
| Customers | `gh.customers.index` | Manager+ | Table | Yes | **CONVERT** | Priority 1 |
| Zones | `gh.zones.index` | Manager+ | Table | Yes | **CONVERT** | Priority 2 |
| Categories | `gh.categories.index` | Manager+ | Table | Yes | **CONVERT** | Catalog |
| Products | `gh.products.index` | Manager+ | Table | Yes | **CONVERT** | Catalog |
| Treatments | `gh.treatments.index` | Manager+ | Table | Yes | **CONVERT** | Catalog |
| Offerings / prices | `gh.offerings.index` | Manager+ | Table | Yes | **CONVERT** | Catalog |
| B2B contracts | `gh.contracts.index` | Manager+ | Table | Yes | **CONVERT** | |
| Invoices | `gh.invoices.index` | Manager/Accountant | Table | Yes | **CONVERT** | Preserve amounts via `<x-money>` |
| Payments | `gh.payments.index` | Manager/Accountant | Table | Yes | **CONVERT** | |
| Credit notes | `gh.credit-notes.index` | Manager/Accountant | Table | Yes | **CONVERT** | |
| Refunds | `gh.refunds.index` | Manager/Accountant | Table | Yes | **CONVERT** | |
| Financial corrections | `gh.financial-corrections.index` | Manager/Accountant | Table | Yes | **CONVERT** | |
| Driver history | `gh.driver.history` | Driver | Cards | Partial | **REFERENCE** | Already card-ish |
| GH dashboard | `gh.dashboard` | Manager | KPI + cards | Hybrid done | **HYBRID** | Recent lists → cards; KPI tiles kept |
| Reports hub | `gh.reports.*` | Manager | Tables | Yes | **KEEP_TABLE** / **HYBRID** | Analytical lists — card summary optional later |
| Invoice builder | `gh.invoices.builder` | Manager | Line-item table | Yes | **KEEP_TABLE** | Data-entry grid |
| Invoice print | `gh.invoices.print` | Manager | Print table | Yes | **KEEP_TABLE** | Printable document |
| Order form lines | `gh.orders.create/edit` | Manager | Line table | Yes | **KEEP_TABLE** | Data-entry |
| Order show lines | `gh.orders.show` | Manager | Detail tables | Yes | **HYBRID** | Header card OK; lines may stay tabular |
| Contract offerings | `gh.contracts.show` | Manager | Nested table | Yes | **HYBRID** | Contract header cards; offerings table OK |
| Credit note / refund create | create views | Manager | Line tables | Yes | **KEEP_TABLE** | Entry grids |
| Import preview | `gh.import.preview` | Manager | Validation matrix | Yes | **KEEP_TABLE** | Spreadsheet preview |
| Settings / VAT | `gh.settings` | Manager | Form + small table | Partial | **N/A** / **KEEP_TABLE** | Config |
| App download / versions | `gh.app.*` | Manager | Forms | No | **N/A** | |
| Customer / product forms | create/edit | Manager | Forms | No | **FORM** | |
| Pickup / delivery workspace | `gh.driver.pickup/delivery` | Driver | Task UI | No | **N/A** | Operational workspace |

## Shared components (implemented)

- `.gh-card-ui` shell + cream wash + container-query grid in `resources/scss/_general-house.scss`
- `<x-gh.record-card>` — `resources/views/components/gh/record-card.blade.php`
- `<x-gh.meta-cell>` — `resources/views/components/gh/meta-cell.blade.php`
- Filters reuse `.gh-board-filters` / existing forms; pager `.gh-card-ui__pager`; empty `.gh-card-ui__empty` + `<x-empty-state>`
- Actions reuse `.gh-board-btn` / `.gh-board-btn--ghost`

## Conversion order (execution)

1. Customers  
2. Zones  
3. Categories → Products → Treatments → Offerings  
4. Contracts  
5. Invoices → Payments → Credit notes → Refunds → Financial corrections  
6. Dashboard recent lists (hybrid)  
7. Revisit KEEP_TABLE pages only if owner requests mobile card summaries  

## Non-goals

- No financial math / lifecycle / driver Sortable changes  
- No LIVE deploy / commit until owner approval  
- No HR / Payroll / Gross redesign  

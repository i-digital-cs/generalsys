# Post-save redirect standardization — audit & report

**Environment:** TEST (`release/generalsys-full-20261108`)  
**Date:** 2026-11-15  
**Policy:** After successful conventional create/update, redirect to the authorized resource index/list.

## Inventory summary

| Metric | Count |
|--------|------:|
| Controllers scanned (`app/Http/Controllers`) | 77 |
| Create/edit-style success redirect methods classified | 86 |
| Already redirected to list | 36 |
| Standardized in this change (show/edit → index) | 14 methods / 9 controllers |
| Specialized workflows preserved | 31 |
| Documented exceptions | 2 methods (1 surface) |

## Controllers modified (STANDARD → index)

| Controller | Methods | Destination |
|---|---|---|
| `Admin\EmployeeController` | `store`, `update` | `admin.employees.index` |
| `GeneralHouse\OrderController` | `store`, `update` | `gh.orders.index` |
| `GeneralHouse\CustomerController` | `store`, `update` | `gh.customers.index` |
| `GeneralHouse\ContractController` | `store`, `update` | `gh.contracts.index` |
| `Gross\ItemController` | `store`, `update` | `gross.items.index` |
| `Gross\CustomerController` | `store` | `gross.customers.index` (update already index) |
| `Gross\ShippingCompanyController` | `store` | `gross.shipping.index` (update already index) |
| `TeamLeader\EvaluationController` | `store` | `team-leader.evaluations` |
| `Valuation\ValuationSheetController` | `store` (success path) | `valuation.sheets.index` |

Nested child saves (contract offerings, customer addresses, employee rest-days/documents) still return to the parent show/edit surface.

## Role-specific handling

| Role / actor | Behavior |
|---|---|
| Manager / Assistant (employee CRUD) | Employees index (`admin.employees.index`) |
| Manager (GH catalog/orders) | GH resource indexes under `gh.*` |
| Manager (Gross) | Gross resource indexes under `gross.*` |
| Team Leader (monthly evaluation) | Evaluations list (`team-leader.evaluations`) |
| Valuation assignee | Module sheet index (`valuation.sheets.index`) |
| Driver | Does **not** use `gh.orders.update` (403). Logistics confirm/fail remain on `gh.driver.pickup` / `gh.driver.delivery`. JSON reorder unchanged. |

GH catalog/order management remains Manager-gated via `GhCatalogAccess::mayManage` (Manager-only today).

## Specialized workflows preserved (do not redirect to list)

- Clock in/out / field photos; clock-out → daily report when required
- Approvals board and attendance time review (`back()` / approvals filters)
- Daily report respond / daily scores thread
- Payroll draft workspace (show), adjustments, approve, generate batch UX
- GH invoice builder, issue, payments, credit notes, refunds, financial corrections
- Gross sales / purchase / treasury document show workflows
- Nested addresses, contract offerings, employee documents / rest days
- Driver logistics redirects + JSON reorder
- Auth login / impersonation via `PostLoginRedirector`
- Settings / VAT / global list-view mode toggle
- Import wizards (preview step)

## Documented exceptions

| Surface | Why |
|---|---|
| `ProductExpenseController::{upsertBudget,storePayment}` | No `product.expenses.index`; show is the monthly workspace |

## Already list-oriented (no code change)

GH products/categories/treatments/zones/offerings; Gross suppliers/categories/attributes/expense-types/receipt-purposes/stock; Admin advances/penalties; TL shifts/rest-days/vacation-credit; Employee requests tabs; most Gross update paths already on index.

## Tests

- New: `tests/Feature/Navigation/PostSaveRedirectTest.php`
- Updated expectations: Employee CRUD/photo, Assistant role, Gross user pages, Monthly evaluation, Valuation sheets, GH Orders/Customers/Contracts, Gross item store

## Test evidence (2026-11-15)

```text
php artisan test --filter='PostSaveRedirectTest|EmployeeCrudTest|EmployeePhotoTest|AssistantRoleTest|GrossUserPagePermissionTest|MonthlyEvaluationTest|ValuationModuleTest|OrdersTest|CustomersZonesContractsTest|ItemCodeAutoGenerateTest|ShippingCompanyCrudTest|CustomerCrudTest|DriverWorkspaceTest|ItemFormUxTest|ItemImageTest|RestDayTest|EmployeeDocumentTest|TableFilterUiTest|DualListViewTest'
→ 180 passed (1000 assertions)
php -l (all touched controllers): clean
vendor/bin/pint --dirty: passed
php artisan route:list: OK (379 routes)
```

DATABASE CHANGES: NO  
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE  
DATA MIGRATION OR BACKFILL: NOT APPLICABLE  

## Final status

**PASS** — full controller inventory completed; 14 standard create/update workflows redirected to authorized indexes; specialized/exception workflows documented and preserved; focused + regression tests green.

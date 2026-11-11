# UI Dual View progress (Classic + Cards)

**Environment:** TEST only · **Updated:** 2026-11-11  
**Decision:** Default = **Classic / النظام القديم**. Card view = optional opt-in.  
**No LIVE / commit / push** until owner visual approval.

## Architecture

| Piece | Location |
|-------|----------|
| Preference helper | `app/Support/Ui/ListViewMode.php` |
| Toggle endpoint | `POST /ui/list-view/{page}` → `ui.list-view` |
| Toggle component | `<x-ui.list-view-toggle page="…" />` |
| Persistence | Session `ui.list_view.{userId}` + `settings.key = ui.list_view.u{userId}` (JSON map) — **no new migration** |
| Recovery script | `scripts/build-dual-list-views.php` (extracts HEAD → `_mode-legacy`, working tree → `_mode-cards`) |

Modes: `legacy` | `cards`. Invalid / missing → `legacy`.

## Git baseline for legacy recovery

| Item | Value |
|------|--------|
| Repo HEAD (recovery source) | `23b59e84f18ef29b6a295bf25d585ba56d64ce8f` |
| Method | Selective `git show HEAD:<view>` into `_mode-legacy.blade.php` only |
| Not restored | Controllers/services/business logic; Order Board; driver Sortable; payroll math |

## Page checklist

Legend: ✅ verified in automated tests and/or browser · ⬜ pending owner visual

| Route | Page key | Baseline | Legacy | Cards | Toggle | Pref | Notes |
|-------|----------|----------|--------|-------|--------|------|-------|
| `admin.employees.index` | `admin.employees` | HEAD | ✅ table-hr | ✅ | ✅ | ✅ | DualListViewTest |
| `admin.payroll.index` | `admin.payroll` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `admin.advances.index` | `admin.advances` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `employee.salary` | `employee.salary` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `employee.advances` | `employee.advances` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `employee.evaluation` | `employee.evaluation` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `team-leader.evaluations` | `team-leader.evaluations` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `team-leader.shifts` | `team-leader.shifts` | HEAD | ✅ | ✅ | ✅ | ✅ | Roster dual; assign form in both |
| `team-leader.approvals` | `team-leader.approvals` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `valuation.sheets.index` | `valuation.sheets` | HEAD | ✅ | ✅ | ✅ | ✅ | `_list-table` also dual |
| `gh.customers.index` | `gh.customers` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.zones.index` | `gh.zones` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.categories.index` | `gh.categories` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.products.index` | `gh.products` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.treatments.index` | `gh.treatments` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.offerings.index` | `gh.offerings` | HEAD | ✅ | ✅ aligned form | ✅ | ✅ | Aligned create form = cards mode |
| `gh.contracts.index` | `gh.contracts` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.invoices.index` | `gh.invoices` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.payments.index` | `gh.payments` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.credit-notes.index` | `gh.credit-notes` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.refunds.index` | `gh.refunds` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.financial-corrections.index` | `gh.financial-corrections` | HEAD | ✅ | ✅ | ✅ | ✅ | |
| `gh.dashboard` | `gh.dashboard` | HEAD | ✅ | ✅ | ✅ | ✅ | |

## Explicitly **no** dual toggle

| Surface | Reason |
|---------|--------|
| `gh.orders.index` / driver board | Already Order Board V2.4.2 REFERENCE cards |
| Driver pickup/delivery/history | Operational workspace cards |
| Attendance history | Never converted (KEEP_TABLE) |
| Payroll show / forms / reports / penalties | Forms or KEEP_TABLE |
| `team-leader.evaluations.show` | Detail form (not list conversion target) |

## Automated tests

```text
php artisan test --filter='DualListViewTest|CardUiConversionTest|HrCardUiConversionTest|TableFilterUiTest|EvaluationCardUiTest'
→ PASS (incl. default legacy + opt-in cards + preference isolation)
```

## Remaining limitations

- Toggle sits above the page body (shared wrapper); does not restyle legacy chrome.
- TL/Accountant still cannot load private employee photos in card mode (existing policy).
- Preference uses settings key/value (additive); cleared only if that setting row is deleted.
- Owner visual AR/EN 390/1366 screenshot sign-off still required.

## TEST preview URLs

- https://generalsys.idigital-cs.com/admin/employees  
- https://generalsys.idigital-cs.com/admin/payroll  
- https://generalsys.idigital-cs.com/service/gh/customers  
- https://generalsys.idigital-cs.com/service/gh/offerings  
- https://generalsys.idigital-cs.com/service/gh/products  
- https://generalsys.idigital-cs.com/team-leader/approvals  

Use **النظام القديم | النظام الجديد** (or Classic / Card View) at the top of each page.

# HR Card UI conversion progress

**Environment:** TEST only · **Updated:** 2026-11-11  
**Stop point:** Owner visual approval required before commit / LIVE.

## Phase status

| Phase | Status | Notes |
|-------|--------|-------|
| 1 GH Offerings form alignment | **DONE** | `.gh-offerings-create` CSS grid; tax hint full-row |
| 2 Image-aware cards | **DONE** | Product square via `imageUrl()`; employee circle via `employees.photo` |
| 3 HR inventory audit | **DONE** | `docs/hr/HR_CARD_UI_CONVERSION_AUDIT.md` |
| 4–5 Page conversions | **DONE** (core lists) | KEEP_TABLE pages documented for later |
| 6 Business logic | **PRESERVED** | UI-only; eager-load profile only |
| 7 QA / screenshots | **READY FOR OWNER** | Automated tests green; stop for visual approval |

## Converted pages

| Route | Marker | Images |
|-------|--------|--------|
| `admin.employees.index` | `data-eval-card-ui="hr-employees"` | Circle photo / initial |
| `admin.payroll.index` | `data-eval-card-ui="hr-payroll"` | Circle photo / initial |
| `admin.advances.index` | `data-eval-card-ui="hr-advances"` | Circle photo / initial |
| `employee.salary` | `data-eval-card-ui="hr-salary"` | N/A |
| `employee.advances` | `data-eval-card-ui="hr-my-advances"` | N/A |
| `team-leader.evaluations` | `data-eval-card-ui="tl-index"` | Circle photo / initial |
| `employee.evaluation` | `data-eval-card-ui="employee"` | Prior |
| `team-leader.shifts` roster | `data-eval-card-ui="hr-shifts-roster"` | Circle photo / initial |
| `team-leader.approvals` permissions | `data-eval-card-ui="hr-approvals-permissions"` | Circle photo / initial |
| `team-leader.approvals` vacations | `data-eval-card-ui="hr-approvals-vacations"` | Circle photo / initial |
| `gh.products.index` | `data-gh-card-ui="products"` | Square product image |
| `gh.offerings.index` create form | `.gh-offerings-create` | N/A (form) |

## Deferred / KEEP_TABLE

See audit doc. Next optional card passes (owner request only): attendance history mobile summary, employee request history, gaps/OT decision cards.

## Files touched (this initiative)

- Views: admin employees/payroll/advances; employee salary/advances; TL evaluations/shifts/approvals; GH offerings/products; `components/gh/record-card`
- SCSS: `_general-house.scss` offerings grid + thumb media
- Services: PayrollController eager load; AdvanceService; Permission/Vacation list; ShiftAssignmentService; EvaluationService
- Docs: `docs/hr/HR_CARD_UI_CONVERSION_AUDIT.md`, `docs/hr/HR_CARD_UI_CONVERSION_PROGRESS.md`

## Photo authorization note

`EmployeePolicy::view` allows Manager/Assistant or the employee themselves. Team Leaders and Accountants see **initial placeholders** on cards (no photo URL emitted) so private files are never linked without authorization.

## Automated tests (TEST)

```text
php artisan test --filter='HrCardUiConversionTest|TableFilterUiTest|CardUiConversionTest'
→ 18 passed
```

## TEST preview URLs

- https://generalsys.idigital-cs.com/service/gh/offerings  
- https://generalsys.idigital-cs.com/service/gh/products  
- https://generalsys.idigital-cs.com/admin/employees  
- https://generalsys.idigital-cs.com/admin/payroll  
- https://generalsys.idigital-cs.com/admin/advances  
- https://generalsys.idigital-cs.com/team-leader/evaluations  
- https://generalsys.idigital-cs.com/team-leader/approvals  
- https://generalsys.idigital-cs.com/team-leader/shifts  
- https://generalsys.idigital-cs.com/employee/salary (employee role)  

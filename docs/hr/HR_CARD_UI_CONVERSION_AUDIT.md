# HR Card UI conversion audit

**Environment:** TEST only · **Date:** 2026-11-11  
**Visual reference:** GH Order Board V2.4.2 (navy `#101044` · yellow `#FFD329` · cream `#F8F7EF`) via `.eval-card-ui` / `<x-gh.record-card>`  
**Scope:** Existing HR routes and pages only — no invented pages, no business-rule changes.

## Conversion legend

| Code | Meaning |
|------|---------|
| **CONVERT** | Record list → card grid |
| **DONE** | Converted in this initiative |
| **KEEP_TABLE** | Dense analytical / decision matrix / printable — table retained |
| **HYBRID** | Cards for record lists; nested forms/tables remain |
| **FORM** | Create/edit / clock / settings — not a list conversion |
| **N/A** | KPI dashboard / single-record / no list |

## Image fields (discovered — not invented)

| Entity | Source | Serve via |
|--------|--------|-----------|
| Employee | `employee_profiles.personal_photo_path` (local disk) | `route('employees.photo', $user)` authorized |
| GH product | product image path + `GhProduct::imageUrl()` | `route('gh.products.image', $product)` |
| Attendance punch photos | attendance photo storage | `route('attendance.photos.show', …)` — detail modal only |

Missing photos use circular/square placeholder initials or Bootstrap icon — never broken `<img>`.

## Inventory

| Page | Route | Roles | Current UI | Images | Filters / pager | Decision | Notes |
|------|-------|-------|------------|--------|-----------------|----------|-------|
| Admin dashboard | `admin.dashboard` | Manager/Assistant | KPI + contract table | No list photo | Client filter on contracts | **KEEP_TABLE** | Hours/contracts matrix |
| Employees | `admin.employees.index` | Manager/Assistant | Cards | Circle photo | Server `q`/`status` + paginate | **DONE** | View/Edit/impersonate preserved |
| Employee create/edit | `admin.employees.create/edit` | Manager/Assistant | Form | Upload | — | **FORM** | |
| Payroll list | `admin.payroll.index` | Manager/Accountant | Cards | Circle photo | Month/year/status + client toolbar + paginate | **DONE** | Review/Approve; TL still 403 |
| Payroll show | `admin.payroll.show` | Manager/Accountant | Detail tabs | — | — | **KEEP_TABLE** / **FORM** | Adjustments / attendance review grid |
| Advances (admin) | `admin.advances.index` | Manager/Accountant | Cards | Circle photo | Server user/status + paginate | **DONE** | |
| Advance create/edit | `admin.advances.create/edit` | Manager/Accountant | Form | — | — | **FORM** | |
| Penalty rules | `admin.config.penalties` | Manager | Table | None | Client toolbar | **KEEP_TABLE** | Config list, not people records |
| Settings | `admin.settings` | Manager | Form | — | — | **N/A** | |
| Annual report | `admin.reports.annual` | Manager | Report | — | — | **KEEP_TABLE** | Analytical |
| Employee dashboard | `employee.dashboard` | Employee+ | KPI + tables | — | — | **KEEP_TABLE** / **N/A** | Operational summary |
| Profile | `employee.profile` | Self | Profile form | Photo | — | **FORM** | |
| My salary | `employee.salary` | Employee+ | Cards | N/A (self) | Client filter + paginate | **DONE** | |
| Payslip show | `employee.salary.show` | Employee+ | Detail | — | — | **FORM** / print | |
| My advances | `employee.advances` | Employee+ | Cards | N/A | Client filter + paginate | **DONE** | |
| Clock | `employee.clock` | Staff | Punch UI | Selfie capture | — | **FORM** | |
| Daily report | `employee.report` | Staff | Form | — | — | **FORM** | |
| Requests (leave) | `employee.requests` | Staff | Forms + history tables | — | Tabs | **HYBRID** | Forms kept; history card pass optional later |
| My evaluation | `employee.evaluation` | Employee | Cards | — | Client filter | **DONE** (prior) | |
| TL dashboard | `team-leader.dashboard` | TL/Manager/Assistant | KPI | — | — | **N/A** | |
| Shifts | `team-leader.shifts` | TL/Manager/Assistant | Roster cards + assign form + assignment list | Circle photo on roster | Client filter on roster | **HYBRID** / **DONE** roster | Assign form kept |
| Attendance history | `team-leader.attendance` | TL/Manager/Assistant | Dense table + detail modals | Punch evidence in modal | Server filters + paginate | **KEEP_TABLE** | Metric badges / OT / force clock-out |
| Approvals | `team-leader.approvals` | TL/Manager (+Assistant gaps) | Permissions+vacations cards; gaps/OT tables | Circle photo on leave cards | Server pills + filters | **HYBRID** | Gaps/OT keep decision forms in tables |
| Evaluations list | `team-leader.evaluations` | TL/Manager | Cards | Circle photo | Month/year + client filter | **DONE** | |
| Evaluation show | `team-leader.evaluations.show` | TL/Manager | Form + cards | — | — | **FORM** / **HYBRID** | |
| Vacation credit | `team-leader.vacation-credit` | TL/Manager/Assistant | Form + table | — | — | **KEEP_TABLE** / **FORM** | Balance matrix |
| Rest days | `team-leader.rest-days` | TL/Manager/Assistant | Table | — | Client filter | **KEEP_TABLE** | Calendar grid-ish list |
| TL reports | `team-leader.reports` | TL | Report table | — | Server filters | **KEEP_TABLE** | |
| Valuations (daily) | `valuation.sheets.index` | Assigned | Cards | — | Client filter | **DONE** (prior) | Service module, HR-adjacent |
| Valuation assignments | `valuation.assignments.index` | Manager | Table | — | Client filter | **KEEP_TABLE** | Assignment matrix |

## Shared presentation

- Shell: `.eval-card-ui` (same container-query grid as GH `.gh-card-ui`)
- Card: `<x-gh.record-card>` with optional `imageUrl` / `imageVariant` (`circle`|`square`) / placeholder
- Meta: `<x-gh.meta-cell>`
- Money: `<x-money>` unchanged
- No HR business logic inside GH services

## Intentionally retaining tables

1. Attendance history — multi-metric day rows + modal tooling  
2. Approvals gaps + overtime — inline note + dual-action decision forms  
3. Payroll show — editable draft / adjustment / attendance review  
4. Penalty config, annual/TL reports, rest-day & vacation-credit matrices  
5. Admin dashboard contract/hours tables  
6. Employee request create forms + (for now) history tables  

## Non-goals

- No payroll / attendance / leave calculation changes  
- No destructive migrations / photo backfills  
- No LIVE deploy / commit / push until owner approval  

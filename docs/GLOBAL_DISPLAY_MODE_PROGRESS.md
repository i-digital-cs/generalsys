# Global Display Mode — Progress

**Environment:** TEST only · **Updated:** 2026-11-11  
**Audit:** `docs/GLOBAL_DISPLAY_MODE_AUDIT.md`  
**Supersedes:** per-page Dual View toggles

## Status

| Phase | Status |
|-------|--------|
| 1 Audit | DONE |
| 2 Header control | DONE — `الجداول \| الكروت` / `Tables \| Cards` in navbar |
| 3 Global preference | DONE — `ui.display_mode.u{id}` (+ session); old per-page keys unused |
| 4 Legacy preserved | DONE — `_mode-legacy` untouched content from prior recovery |
| 5 Modern coverage | DONE for prior dual pages + attendance history + report review list |
| 6 Design system | Reused navy/yellow/cream card shell |
| 7–8 Tests | DualListViewTest + card/attendance regressions PASS |
| 9 Owner approval | **STOP HERE** |

## Behavior

1. No preference → Classic tables everywhere eligible.  
2. Header **الكروت / Cards** → all eligible lists use `_mode-cards`.  
3. Preference persists logout/login (settings key).  
4. Per-user isolation.  
5. No per-page toggles remain.  
6. `redirect()->back()` keeps filters/query string.

## New Modern coverage this pass

| Route | Classic | Modern |
|-------|---------|--------|
| `team-leader.attendance` | Dense table + shared modals | Summary cards + same modals |
| `team-leader.reports` | Table | Cards (show/reply unchanged) |

## Documented exceptions (Classic grid always / no false cards)

- Order Board / driver workspace (already card-native)
- Payroll show, invoice builder/print, import preview
- GH analytical reports, annual HR report
- Vacation credit / rest-day matrices, penalty config
- Approvals gaps/OT decision forms (remain tabular inside Modern approvals page)

## Automated tests

```text
php artisan test --filter='DualListViewTest|CardUiConversionTest|HrCardUiConversionTest|TableFilterUiTest|EvaluationCardUiTest|AttendanceHistoryTest'
→ 39 passed
```

## TEST preview URLs

- https://generalsys.idigital-cs.com/admin/employees  
- https://generalsys.idigital-cs.com/admin/payroll  
- https://generalsys.idigital-cs.com/team-leader/attendance  
- https://generalsys.idigital-cs.com/team-leader/reports  
- https://generalsys.idigital-cs.com/service/gh/customers  
- https://generalsys.idigital-cs.com/service/gh/offerings  

Use the header control **الجداول | الكروت** (not on-page toggles).

## Files (key)

- `app/Support/Ui/ListViewMode.php` — global mode  
- `app/Http/Controllers/Ui/ListViewModeController.php`  
- `resources/views/layouts/partials/navbar.blade.php`  
- `resources/views/components/ui/list-view-toggle.blade.php`  
- Dual wrappers + `_mode-*` partials; attendance/reports new cards  
- `docs/GLOBAL_DISPLAY_MODE_AUDIT.md`, `docs/GLOBAL_DISPLAY_MODE_PROGRESS.md`  
- Tests updated for global preference  

**No commit / push / LIVE.**

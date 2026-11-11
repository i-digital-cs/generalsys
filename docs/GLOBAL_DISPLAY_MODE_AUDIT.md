# Global Display Mode — Audit & Migration Plan

**Environment:** TEST only · **Date:** 2026-11-11  
**Supersedes:** per-page Dual View (`docs/UI_DUAL_VIEW_PROGRESS.md`)  
**Owner decision:** ONE header control · Classic default · Modern optional · legacy untouched

## Current state (what exists today)

| Area | Finding |
|------|---------|
| Header | `layouts/partials/navbar.blade.php` — theme, locale, notifications, user menu, module tabs. **No display-mode control.** |
| Per-page toggles | `<x-ui.list-view-toggle>` injected on every dual-wrapped index (misaligned UX) |
| Preference | `ListViewMode` stores **per-page** JSON map in `settings.key = ui.list_view.u{id}` + session |
| Legacy partials | `_mode-legacy.blade.php` from Git HEAD `23b59e8` |
| Card partials | `_mode-cards.blade.php` from prior conversion work |
| Shared cards | `<x-gh.record-card>`, `.gh-card-ui` / `.eval-card-ui`, navy/yellow/cream |

## Pages with BOTH Classic + Modern already

| Route | Partial pair | Notes |
|-------|--------------|-------|
| `admin.employees.index` | yes | |
| `admin.payroll.index` | yes | |
| `admin.advances.index` | yes | |
| `employee.salary` | yes | |
| `employee.advances` | yes | |
| `employee.evaluation` | yes | |
| `team-leader.evaluations` | yes | |
| `team-leader.shifts` | yes | Roster dual; assign form shared |
| `team-leader.approvals` | yes | Gaps/OT tables inside cards mode still hybrid |
| `valuation.sheets.index` | yes | + `_list-table` dual |
| `gh.customers` … `gh.financial-corrections` | yes | Catalog + finance indexes |
| `gh.offerings` | yes | Aligned create form only in Modern |
| `gh.dashboard` | yes | KPI unchanged; recent lists dual |

## Pages needing Modern (mandatory / high priority)

| Route | Classic today | Modern plan |
|-------|---------------|-------------|
| `team-leader.attendance` | Dense metric table + modals | Card summaries + same detail modals |
| `team-leader.reports` | Report-review list table | Card list; show/respond form stays FORM |
| `team-leader.rest-days` | Table | Optional card pass (operational list) |
| `team-leader.vacation-credit` | Form + matrix | **EXCEPTION** — keep grid |
| `admin.config.penalties` | Config table | **EXCEPTION** — config list |
| `admin.reports.annual` | Analytical | **EXCEPTION** |
| `gh.reports.*` | Analytical | **EXCEPTION** |
| Invoice builder / print / import preview | Entry/print grids | **EXCEPTION** |

## Surfaces that stay card-native (no Classic/Modern fork)

| Surface | Reason |
|---------|--------|
| `gh.orders.index` / driver board | Order Board V2.4.2 REFERENCE |
| Driver pickup / delivery / history | Operational cards |
| Clock / forms / payroll show | Not record-list presentation |

## Safe migration plan (no blanket reset)

1. **Audit complete** (this file).
2. Replace `ListViewMode` API with **one global** value per user (`ui.display_mode.u{id}`); leave old `ui.list_view.u{id}` keys unused (do not delete).
3. Add compact **الجداول \| الكروت** control to authenticated header chrome.
4. Remove all per-page `<x-ui.list-view-toggle>` blocks; wrappers keep `@include` of `_mode-legacy` / `_mode-cards` driven by global mode.
5. Do **not** edit `_mode-legacy` contents (legacy parity).
6. Extend Modern for attendance history + report review lists.
7. Update tests: global preference, no per-page toggles, isolation, regressions.
8. Document exceptions; stop for owner visual approval.

## Acceptance criterion

> One header toggle controls presentation across the ERP, while the original legacy UI remains completely untouched and fully functional.

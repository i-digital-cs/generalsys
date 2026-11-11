# Evaluation card UI redesign

**Environment:** TEST only · **Date:** 2026-11-11  
**Visual identity:** Order Board V2.4.2 tokens (navy `#101044` · yellow `#FFD329` · cream `#F8F7EF`)  
**Scope:** HR Evaluation UI only — no scoring / workflow changes.  
**Status:** Implemented on TEST working tree — awaiting owner visual approval (no commit/push/LIVE).

## 1. Inventory (before → after)

| Surface | Route | Controller | View | Before | After |
|---------|-------|------------|------|--------|-------|
| My Evaluation | `employee.evaluation` `/evaluation` | `Employee\EvaluationController@show` | `employee/evaluation.blade.php` | Score panels + **history table** | Cream shell + detail cards + **history cards** (search kept) |
| TL team list | `team-leader.evaluations` | `TeamLeader\EvaluationController@index` | `team-leader/evaluations/index.blade.php` | **Team table** | Card grid (name, period, total %, saved/draft, Open) |
| TL detail | `evaluations.show` / `store` | `TeamLeader\EvaluationController` | `team-leader/evaluations/show.blade.php` | Bootstrap cards + form | Same form/POST; ops card skin |

**Criteria (unchanged):** performance, behavior, productivity, interest_return, attendance → total via `EvaluationService`.  
**Actions preserved:** Open, Save monthly text (once), month/year filters, client search + status filter.

## 2. Tables converted

- Employee history `table-hr` → stacked history cards (`data-filterable-item`)
- TL team `table-hr` → `eval-card-ui__grid` cards

## 3. Files changed

- `resources/views/employee/evaluation.blade.php`
- `resources/views/team-leader/evaluations/index.blade.php`
- `resources/views/team-leader/evaluations/show.blade.php`
- `resources/scss/_general-house.scss` (`.eval-card-ui` shares ops tokens/grid with `.gh-card-ui`; container `ops-card-ui`)
- `tests/Feature/Employee/EvaluationCardUiTest.php`
- `docs/evaluation/EVALUATION_CARD_UI.md`
- Built: `public/build/assets/app-*.css` (JS bundle unchanged — scroll fix preserved)

## 4. Screenshots (browser QA)

Captured in Cursor browser session:

| File | Case |
|------|------|
| `eval-ar-mobile-390.png` | `/evaluation` AR mobile |
| `eval-en-mobile-390.png` | `/evaluation` EN mobile |
| `eval-ar-desktop-1366.png` | `/evaluation` AR desktop |
| `eval-en-desktop-1366.png` | `/evaluation` EN desktop |
| `eval-tl-ar-desktop-1366.png` | TL list AR desktop (3×~358px cards) |
| `eval-tl-en-desktop-1366.png` | TL list EN desktop |
| `eval-tl-ar-mobile-390.png` | TL list AR (cards + Open) |

## 5. Automated tests

```text
php artisan test --filter='MyEvaluationTest|MonthlyEvaluationTest|EvaluationCardUiTest|ScrollIntoScrollerSourceTest'
→ 14 passed
```

## 6. Scroll regression

- `ScrollIntoScrollerSourceTest` PASS (no document `scrollIntoView`)
- Live `/evaluation` after load: `scrollY=0` (AR + EN), heading visible, cream shell present

## 7. TEST preview URLs

- https://generalsys.idigital-cs.com/evaluation  
- https://generalsys.idigital-cs.com/team-leader/evaluations  

## 9. Valuation Rating list (owner URL)

**URL:** https://generalsys.idigital-cs.com/service/valuations  

| Item | Detail |
|------|--------|
| Route | `valuation.sheets.index` (`/{module}/valuations`) |
| Views | `valuation/sheets/index.blade.php`, `_list-table.blade.php` |
| Before | `table-hr` list |
| After | `.eval-card-ui` card grid — person, date, status, KPI, amount, View/PDF/Edit/Delete |
| Tests | `ValuationModuleTest` + `TableFilterUiTest` PASS |

## 10. Remaining limitations

- Admin `/evaluation` may show empty month (no self-evaluation rows) — card empty state is intentional.
- Score bars remain inside detail cards (not invented criteria chips).
- Valuation form/show/PDF stay as before (list converted only).
- No commit / push / LIVE until owner approval.

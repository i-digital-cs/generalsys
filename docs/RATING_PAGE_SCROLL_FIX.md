# Rating / Evaluation page auto-scroll fix

**Environment:** TEST only · **Date:** 2026-11-11  
**Status:** Fixed in working tree (not committed)

## Page

| Item | Value |
|------|--------|
| Route | `GET /evaluation` → `employee.evaluation` |
| Controller | `App\Http\Controllers\Employee\EvaluationController` |
| View | `resources/views/employee/evaluation.blade.php` |
| Related | TL `team-leader.evaluations` / `evaluations.show` (same global JS) |
| Module | HR (not General House) |

## Root cause

`resources/js/app.js` called `Element.scrollIntoView({ behavior: 'smooth', … })` on the active global module tab (`.hp-module-tab--active` inside `[data-hp-module-switcher]`).

On mobile / Android WebView (and sometimes desktop), browsers scroll **ancestor viewports including the document**, not only the header’s `overflow-x` scroller. The deferred app bundle runs after first paint, so the page appears at the top and then **smoothly jumps downward**.

`resources/js/pages/gh-order-board.js` used the same `scrollIntoView` pattern for GH stage tabs (same class of bug on board pages).

Not caused by: `autofocus`, evaluation Blade focus, URL hash, or global `history.scrollRestoration` changes.

## Fix

1. Added `resources/js/scroll-into-scroller.js` — adjusts only the scroller via `scrollBy({ left })` from getBoundingClientRect deltas (RTL/LTR safe).
2. Module switcher and GH board tabs call that helper with `behavior: 'auto'`.
3. No `window.scrollTo(0,0)` workaround; intentional `#fragment` navigation unchanged.
4. Built asset: `public/build/assets/app-Dq37mfAw.js` (no `scrollIntoView` in bundle).

## Verification (TEST)

| Case | Result |
|------|--------|
| Direct `/evaluation` AR mobile 390 | `scrollY=0`, heading `تقييمي` visible |
| EN mobile 390 | `scrollY=0`, heading `My Evaluation` visible |
| Desktop 1366 reload | `scrollY=0` after settle |
| Source guard test | `ScrollIntoScrollerSourceTest` PASS |
| Built JS | `rg scrollIntoView public/build/assets/app-*.js` → none |

## Files

- `resources/js/scroll-into-scroller.js` (new)
- `resources/js/app.js`
- `resources/js/pages/gh-order-board.js`
- `tests/Unit/Frontend/ScrollIntoScrollerSourceTest.php`
- `public/build/assets/app-Dq37mfAw.js` (+ manifest)

# Attendance UI tooltip refinement (TEST)

**Date:** 2026-10-22  
**Scope:** Presentation only · **Calculations unchanged** · **No LIVE deploy** · **No historical/payroll rewrite**

## Before / after (Reda-class row)

| Before | After |
|---|---|
| Separate danger badges: “Credited hours need review”, “Shift review required”, “Late vs planned window (review)” | **One** badge: **Review required** / **يحتاج مراجعة** |
| Reasons buried in competing labels | Reasons listed in **one tooltip** (narrative + structured codes) |
| Metrics without help | Info icon tooltips on Attended, Late, hours, OT, auto checkout, outside office |
| Late minutes still visible | Late minutes remain visible as a metric (not only in tooltip) |

## Components changed

| File | Role |
|---|---|
| `AttendanceCalculationResult::reviewReasonCodes()` / `needsReview()` | Structured flags from SSOT result |
| `AttendanceReviewPresentation` | Localized tooltip assembly (dynamic window/check-in placeholders) |
| `AttendanceHistoryService` | `needs_review`, `review_reason_codes`, `review_tooltip` |
| `x-hp-tooltip` | Reusable accessible info button + Bootstrap tooltip |
| `x-attendance-review-indicator` | Single review badge |
| `x-attendance-metric-badge` | Metric + optional tooltip |
| `attendance-punch-range` | Removed duplicate shift-review badge; auto-checkout tooltip |
| `team-leader/attendance/index` | Deduplicated list + modal |
| `attendance-location-badges` | Outside-office tooltip |
| `resources/js/app.js` | Tooltip init (`hover focus click`, `container: body`) |
| `resources/scss/_hr-pro.scss` | `.hp-tooltip` max-width / focus ring |
| `lang/en|ar/attendance.php` | Review + tooltip strings |

## Tooltip content inventory

| Key | Purpose |
|---|---|
| `attendance.tooltips.attended` | Presence ≠ punctuality |
| `attendance.tooltips.late` | Grace-aware late meaning |
| `attendance.tooltips.recorded_hours` | Punch span may be unverified |
| `attendance.tooltips.verified_worked_hours` | Employee-verified duration |
| `attendance.tooltips.credited_hours` | Policy counted ≠ payroll approved |
| `attendance.tooltips.late_vs_planned_window` | Window-relative late |
| `attendance.tooltips.auto_checkout` | System-generated out |
| `attendance.tooltips.eligible_overtime` | Surplus eligible, not approved |
| `attendance.tooltips.approved_overtime` | Workflow-approved OT |
| `attendance.tooltips.outside_office` | Outside geofence radius |
| `attendance.review.narrative.shift_auto_policy` | Dynamic `:window_start`, `:window_end`, `:clock_in` |
| `attendance.review.reasons.*` | Structured reason bullets |

No employee names, attendance IDs, or fixed minute values are hardcoded in UI logic.

## Accessibility

- Tooltip trigger is a `<button type="button">` with `aria-label`
- Keyboard: focus shows tooltip (Bootstrap `focus` trigger)
- Touch: `click` trigger
- Focus-visible outline on `.hp-tooltip-trigger`
- Tooltips attach to `body` to avoid clipping in tables/modals
- Max width ~18rem; `pre-line` for multi-reason text
- RTL/LTR: same components; copy via AR/EN lang files

## Regression

```bash
php artisan test --filter='…RC1|SSOT|Portal|Historical|FinalTargeted|AttendanceUiTooltipRefinement…'
```

**Result: 212 passed (988 assertions)** — includes 5 new tooltip refinement tests.

SSOT assertions: late/credited for fabricated fixture unchanged (`AttendanceUiTooltipRefinementTest::test_ssot_late_unchanged_for_fabricated_fixture`).

## Asset note

JS/SCSS changes require a local `vite build` (or `npm run build`) before TEST UI shows tooltip behavior in browser.

## Browser verification (2026-10-22 TEST)

| Check | Result |
|---|---|
| Path / DB | `/home/d14098/generalsys.idigital-cs.com` · `d14098_general` · `https://generalsys.idigital-cs.com` |
| `npm run build` | PASS → `app-BBdTcOWU.js`, `app-gX4Nzyks.css`, `app-DTRGYXEQ.css` |
| Bundle contains tooltip init | `hover focus click` + `hp-tooltip` in JS; `.hp-tooltip` in CSS |
| Caches | `view:clear` / `cache:clear` / `config:clear` |
| Reda 680 | One **Review required**; tooltip narrative 00:00–12:00 / 23:03 + all reasons; late **1383** visible; no duplicate shift/credited review badges |
| Amir 681 | No review badge; verified **12.70h**; late **183**; eligible OT **42**; employee checkout |
| RTL AR / LTR EN | Verified both locales |
| Tooltip clipping | Attached to `body`; in viewport |
| Console errors | None observed |
| SSOT unchanged | 680 late 1383 / credited 12 / AUTO_STALE; 681 late 183 / verified 12.7 / OT el 42 ap 0 |
| Regression | **212 passed (988 assertions)** |

## Verdict

**CONDITIONAL** — TEST browser + build verified; LIVE blocked pending owner approval. Calculations/history/payroll untouched. No push/merge/deploy performed.

# HR-Pro — UI Framework Specification (Blade + Bootstrap 5)

## 1. Stack & Constraints

| Decision | Value | Why |
|----------|-------|-----|
| Rendering | **Blade (server-side)** | No SPA — Hostinger shared hosting, no Node runtime on server |
| CSS | **Bootstrap 5.3** (RTL build) | User decision; Arabic-first UI |
| JS | **Bootstrap bundle + small vanilla JS** | No framework; keeps hosting simple |
| Assets | **Vite build locally → upload `public/build`** | Build step never runs on server |
| Fonts | **Cairo** UI (self-hosted `public/fonts`; preload + `font-display: block`). **Tajawal** TTF for payslip PDF only | No CDN; reduces hard-refresh font flash |
| Direction | `dir="rtl"` default, `lang="ar"`; `dir="ltr"` when `language_preference = en` | AR/EN toggle |
| Icons | Bootstrap Icons (self-hosted SVG sprite) | No CDN |

> HARD RULE: no CDN links in production layouts, no npm runtime on server, no Livewire/Inertia (keep memory footprint low on shared hosting).

## 2. Directory Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php            ← main shell (navbar, sidebar, flash, @yield)
│   ├── auth.blade.php           ← login shell (centered card, language toggle)
│   └── partials/
│       ├── navbar.blade.php
│       ├── sidebar.blade.php    ← role-filtered menu
│       ├── flash.blade.php      ← success/error toasts
│       └── breadcrumbs.blade.php
├── components/                   ← Blade components (<x-...>)
│   ├── stat-card.blade.php
│   ├── data-table.blade.php      ← paginated table wrapper
│   ├── status-badge.blade.php    ← attendance/request status pills
│   ├── money.blade.php           ← formats DECIMAL + currency symbol
│   ├── score-bar.blade.php       ← 0-100 progress bar for evaluations
│   ├── confirm-modal.blade.php
│   └── empty-state.blade.php
├── employee/                     ← employee screens
├── team-leader/                  ← TL screens
├── admin/                        ← manager screens
└── errors/ (403, 404, 500)
```

## 3. Layout Contract

`layouts/app.blade.php` sets `<html lang dir>` from `auth()->user()->language_preference` and exposes:

- `@yield('title')` — page title (translated key, e.g. `__('payroll.title')`)
- `@yield('content')`
- `@stack('scripts')` — page-specific vanilla JS, **always at bottom, always deferred**
- Sidebar menu rendered from a single `config/menu.php` array filtered by role — **menu items for payroll are never rendered for TEAM_LEADER** (privacy rule also in UI, not just backend).

Flash messages: `session('success'|'error')` → Bootstrap toast, auto-dismiss 4s.

## 4. Design Tokens (Bootstrap overrides in `resources/scss/app.scss`)

```scss
$primary:   #0d6efd;   // default, brand override TBD
$font-family-sans-serif: 'Cairo', system-ui, sans-serif;
$border-radius: .5rem;
// RTL: import "bootstrap/dist/css/bootstrap.rtl.min.css" when dir=rtl
```

**Compact density (modern scale)** — defined once in `resources/scss/_hr-pro.scss` as plain CSS so it applies to both builds: base type 14px (`--bs-body-font-size: .875rem`), buttons/inputs/tables at .875rem with tighter padding, card spacers .85rem/1rem, card radius .65rem, headings one step smaller, navbar padding .45rem. Sidebar width 210px; main content padding `p-3`.

- Currency display: `<x-money :amount="$record->net_pay" :currency="$record->currency"/>` → `2,808.00 ج.م` / `2,808.00 AED`.
- Dates via Carbon `->translatedFormat()` so Arabic month names render.
- Status colors: PRESENT=success, LATE=warning, ABSENT=danger, PENDING=secondary, FORCED=dark.

## 5. Screen Map (routes → Blade views)

### Employee (mobile-first, `employee/`)
| Screen | View | Key components |
|--------|------|----------------|
| Login | `auth/login` | language toggle, RTL card |
| Dashboard | `employee/dashboard` **(shipped T-2.4 + T-2.13)** | today status/sessions; month planned/spent stats; day list planned vs actual; calendar markers (shift + hours) |
| Clock | `employee/clock` **(T-3.5 flags)** | FT/Online always (until used today); EXTRA/OT if assigned; preferred = TL assignment or default; banner; live timer; big CTA; Friday/rest/holiday work badges |
| Daily Report | `employee/report` **(shipped T-2.5)** | date picker; one report/day on first shift after clock-out; locked after TL reply; response read-only escaped |
| My Requests | `employee/requests` **(shipped T-2.7 + T-2.9 + T-2.10)** | permissions + vacations; **Force** on REJECTED permissions |
| My Salary | `employee/salary` | payslip card (APPROVED/PAID only), PDF download |
| My Evaluation | `employee/evaluation` **(shipped T-3.4)** | 5 `<x-score-bar>` + monthly TL text (read-only); history list |

### Team Leader (`team-leader/`)
| Screen | View | Key components |
|--------|------|----------------|
| Team Dashboard | `team-leader/dashboard` **(shipped T-2.13)** | today who’s-in board + %; month team totals; employee filter; day list planned vs actual; calendar (selected employee, shift+hours) |
| Pending Approvals | `team-leader/approvals` **(shipped T-2.8 + T-2.9 + T-2.11)** | permissions Accept/Reject/Force + vacations Accept/Reject with rest-day swap; team-scoped; **no money** |
| Reports Review | `team-leader/reports` **(shipped T-2.6 + T-3.1)** | pending/replied/all queue; detail + reply form; locks employee report; daily 4-score inputs + auto attendance % |
| Monthly Evaluation | `team-leader/evaluations` **(shipped T-3.3)** | 5 `<x-score-bar>` averages from daily + monthly text (editable); TL team / Manager |
| Rest Days | `team-leader/rest-days` **(shipped)** | add/remove specific rest dates per team member |
| Shift Assignment | `team-leader/shifts` **(shipped T-2.14)** | **My team** roster for date; assign form; list with **Edit** + Cancel; edit page for SCHEDULED |

> TL layout **never** shows money. Sidebar has no payroll item. Any URL hack → 403 page.
> Manager may also open `/team-leader/shifts` (role middleware) to assign any employee; item is not on Manager sidebar.

### Admin/Manager (`admin/`)
| Screen | View | Key components |
|--------|------|----------------|
| Company Dashboard | `admin/dashboard` | dept stat cards, today's attendance |
| Employee Management | `admin/employees/*` | CRUD wizard: account → profile → contract → documents → rates |
| Penalty Rules | `admin/config/penalties` | per-currency editable grid |
| Holidays | `admin/config/holidays` | date + AR/EN name list |
| Payroll Generation | `admin/payroll/*` | month picker → draft review table → per-employee breakdown modal → approve → export PDF/Excel |
| Annual Reports | `admin/reports/annual` | contract dates, per-month grid, year evaluation average |
| Settings | `admin/settings` | language/currency defaults |

## 6. Component Examples

**Stat card**
```blade
<x-stat-card title="{{ __('dashboard.team_attendance') }}" value="94%" icon="bi-people" color="success"/>
```

**Data table (always paginated; use `.table-hr` + toolbar)**
```blade
<div data-table-filter-root>
    <x-table-toolbar :search-placeholder="__('general.search_placeholder')" />
    <div class="table-responsive">
        <table class="table table-hr table-sm" data-table-filterable>
            <thead>
                <tr>
                    <th>{{ __('…') }}</th>
                    <th class="col-num">{{ __('…') }}</th>{{-- numbers/times: same class on th+td --}}
                </tr>
            </thead>
            <tbody>
                <tr data-filter-text="…">
                    <td>…</td>
                    <td class="col-num"><span class="num-ltr">12.50h</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
{{ $rows->links() }}
```

Rules: never put `dir="ltr"` on a whole `th`/`td` (breaks RTL column alignment). Wrap numerals in `.num-ltr`. Text columns stay start-aligned; numeric columns use `.col-num` on **both** header and cell.
**Payslip money row**
```blade
<tr><td>{{ __('payroll.net') }}</td>
    <td><x-money :amount="$payroll->net_pay" :currency="$payroll->currency"/></td></tr>
```

## 9. UI task hard rules — Translate · RTL/LTR · Responsive

Every task that ships UI must pass all three (also in `docs/TASKS.md` UI/UX gate and `AGENTS.md` §8):

1. **Translate:** `__('key')` only; keys in **both** `lang/ar` and `lang/en` in the same PR/change.
2. **RTL + LTR:** test Arabic RTL and English LTR before `done`. Layout uses Bootstrap RTL/LTR CSS; prefer `ms-*` / `me-*` / logical alignment.
3. **Responsive:** verify **375px · 768px · 1280px**. No clipped primary actions; tables scroll or stack on small viewports.

## 10. Page-Level JS Rules

- One small `resources/js/pages/<name>.js` per interactive page, imported via `@vite` in that view only.
- Allowed: clock timer, confirm modals, inline approve/reject (fetch + CSRF), language toggle.
- All fetch calls hit the same web routes with session auth — no token juggling in Blade.
- No jQuery. No heavy libraries. Total page JS budget: **< 50 KB** gzipped.
- User-facing JS strings must use translated values passed from Blade (`@json(__('…'))`) — not hardcoded English/Arabic in JS.

## 11. Accessibility & Performance Budget (shared hosting)

- Server renders full HTML — first paint fast; keep each view < 30 queries (debugbar in dev).
- Images: documents served via `Storage` with authorization; photos resized at upload (Intervention Image), max 400px avatar.
- Every page passes: lang/dir set, labels on inputs, `aria-*` on icon-only buttons.
- Pagination everywhere (25/page default), `cache` headers on static assets via `.htaccess` in `public/build`.
- Touch-friendly controls on mobile (min ~44px height for primary buttons on employee clock/approvals).
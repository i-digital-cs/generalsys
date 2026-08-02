# HR-Pro — Task Checklists

**How to use**

1. Check off a task only when **all nested boxes** under it are checked.
2. Start a task only when every task listed in **Depends on** is checked.
3. Follow `AGENTS.md` §0 · gates in `docs/BUILD_AND_TEST_GATES.md` · UI rules in `docs/UI_FRAMEWORK.md` §9.

Related: `MASTER_CONTEXT.md` · `AGENTS.md`

---

## UI/UX gate (copy under every UI task — skip if N/A)

- [ ] Translate: no hardcoded copy (`__()` only)
- [ ] Translate: keys in `lang/ar` **and** `lang/en`
- [ ] Translate: language toggle shows new strings
- [ ] RTL (AR): layout correct
- [ ] LTR (EN): layout correct
- [ ] Responsive ~375px
- [ ] Responsive ~768px
- [ ] Responsive ~1280px
- [ ] Flow: reachable from previous screen
- [ ] Feedback: flash / validation visible
- [ ] Roles: wrong role 403 or hidden menu
- [ ] Money: never shown to TEAM_LEADER (if applicable)
- [ ] Empty state OK (if list screen)

---

## Phase 0 — Scaffold

### T-0.1 — Laravel 11 + `.env` + Hostinger-safe queue/cache
- Depends on: —
- Unlocks: T-0.2
- [x] Task complete
  - [x] Code done
  - [x] Gate 0.1–0.2 tests PASS
  - [x] UI/UX: N/A

### T-0.2 — Layouts (`app`, `auth`), Vite, Cairo/Tajawal, Bootstrap 5 RTL/LTR
- Depends on: T-0.1
- Unlocks: T-0.3
- [x] Task complete
  - [x] Code done
  - [x] Gate 0.3–0.4 tests PASS
  - [x] UI/UX gate (login shell AR/EN toggle) — all boxes above

### T-0.3 — `config/menu.php` role-filtered sidebar skeleton
- Depends on: T-0.2
- Unlocks: T-1.1
- [x] Task complete
  - [x] Code done
  - [x] Feature: menu keys by role PASS
  - [x] UI/UX gate (sidebar per role)

---

## Phase 1 — Foundation (Gate 1)

### T-1.1 — Migrations: users, profiles, quals, docs, contacts, contracts, holidays, settings
- Depends on: T-0.3
- Unlocks: T-1.2, T-2.1, T-4.1
- [x] Task complete
  - [x] Code done
  - [x] `migrate:fresh --seed` (scratch) PASS
  - [x] UI/UX: N/A

### T-1.2 — Auth login/logout + language preference
- Depends on: T-1.1
- Unlocks: T-1.3, T-2.3, T-2.7
- [x] Task complete
  - [x] Code done
  - [x] Gate 1.3 tests PASS
  - [x] UI/UX gate (login → dashboard; AR↔EN)

### T-1.3 — Policies + middleware for 3 roles
- Depends on: T-1.2
- Unlocks: T-1.4
- [x] Task complete
  - [x] Code done
  - [x] Feature 403 matrix smoke PASS
  - [x] UI/UX gate (wrong role blocked)

### T-1.4 — Admin: Employee CRUD (account + profile + contract)
- Depends on: T-1.3
- Unlocks: T-1.5, T-1.7, T-1.8
- [x] Task complete
  - [x] Code done
  - [x] Gate 1.4 tests PASS
  - [x] UI/UX gate (create/edit/list; pagination; validation)

### T-1.5 — Admin: rates + currency per employee
- Depends on: T-1.4
- Unlocks: T-1.6
- [x] Task complete
  - [x] Code done
  - [x] Gate 1.5 tests PASS
  - [x] UI/UX gate (Admin rates; TL never sees money)

### T-1.6 — Employee documents upload/download (authorized)
- Depends on: T-1.5
- Unlocks: T-1.7
- [x] Task complete
  - [x] Code done
  - [x] Gate 1.6 tests PASS
  - [x] UI/UX gate (upload/reject mime/download)

### T-1.7 — Admin company dashboard (basic stats)
- Depends on: T-1.4
- Unlocks: T-2.1
- [x] Task complete
  - [x] Code done
  - [x] Feature smoke PASS
  - [x] UI/UX gate (cards; empty state)

### T-1.8 — Employee self profile view (read-only)
- Depends on: T-1.4
- Unlocks: T-2.1
- [x] Task complete
  - [x] Code done
  - [x] Feature self-only PASS
  - [x] UI/UX gate (own data only)

---

## Phase 2 — Attendance & requests (Gate 2)

### T-2.1 — Migrations: attendance, permissions, vacations, balances, credit logs, shift_assignments
- Depends on: T-1.1
- Unlocks: T-2.2, T-2.7, T-2.10, T-2.12, T-2.14
- [x] Task complete
  - [x] Code done
  - [x] migrate scratch PASS
  - [x] UI/UX: N/A

### T-2.2 — `AttendanceService` clock-in/out + overnight + day flags
- Depends on: T-2.1
- Unlocks: T-2.3, T-3.5, T-4.3
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.1–2.2 tests PASS
  - [x] UI/UX: N/A

### T-2.3 — Employee Clock screen (FT/Online; OT only if assigned)
- Depends on: T-2.2, T-1.2 _(OT UX also needs T-2.14)_
- Unlocks: T-2.4, T-2.5, T-2.13
- [ ] Task complete **in_progress**
  - [ ] Code done
  - [ ] Gate 2.3–2.5 tests PASS
  - [ ] UI/UX gate (big CTA; timer; mobile; linked from dashboard)

### T-2.4 — Employee dashboard (today status + clock CTA)
- Depends on: T-2.3
- Unlocks: T-2.5, T-5.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature tests PASS
  - [ ] UI/UX gate (CTA → clock)

### T-2.5 — Daily report submit + view TL response
- Depends on: T-2.3
- Unlocks: T-2.6
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.10 tests PASS
  - [ ] UI/UX gate (form + flash; escaped response)

### T-2.6 — TL: respond to daily reports
- Depends on: T-2.5
- Unlocks: T-2.7, T-3.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.10–2.11 tests PASS
  - [ ] UI/UX gate (queue → reply → employee sees it)

### T-2.7 — Employee: permission request (leave/late)
- Depends on: T-2.1, T-1.2
- Unlocks: T-2.8, T-5.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature create/list PASS
  - [ ] UI/UX gate (form + status badges; My Requests)

### T-2.8 — TL: approve/reject permissions
- Depends on: T-2.7
- Unlocks: T-2.9, T-5.5
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.6 tests PASS
  - [ ] UI/UX gate (Accept/Reject clear)

### T-2.9 — TL: Mark as forced (double penalty)
- Depends on: T-2.8
- Unlocks: T-2.10
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.7 tests PASS
  - [ ] UI/UX gate (button after Reject; confirm; FORCED)

### T-2.10 — Employee: vacation request
- Depends on: T-2.1
- Unlocks: T-2.11
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.8 tests PASS
  - [ ] UI/UX gate (remaining credit on form)

### T-2.11 — TL: approve/reject vacation (see credit)
- Depends on: T-2.10
- Unlocks: T-2.12, T-5.5
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.8 tests PASS
  - [ ] UI/UX gate (credit visible before decision)

### T-2.12 — TL: add vacation credit + history
- Depends on: T-2.1
- Unlocks: T-2.13
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.9 tests PASS
  - [ ] UI/UX gate (add-credit modal; balance updates)

### T-2.13 — TL: team attendance dashboard + %
- Depends on: T-2.3
- Unlocks: T-3.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.11 tests PASS
  - [ ] UI/UX gate (team list; no money)

### T-2.14 — TL: pre-assign EXTRA_SHIFT / OVERTIME
- Depends on: T-2.1
- Unlocks: _(required before T-2.3 OT UX is complete)_
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature assignment PASS
  - [ ] UI/UX gate (form; shown on employee clock)

---

## Phase 3 — Evaluation & multipliers (Gate 3)

### T-3.1 — Daily scores on attendance (4 TL + auto attendance)
- Depends on: T-2.6
- Unlocks: T-3.2
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 3.1, 3.4 tests PASS
  - [ ] UI/UX gate (4 inputs + auto attendance shown)

### T-3.2 — `EvaluationService` monthly avg of 5 + monthly text
- Depends on: T-3.1
- Unlocks: T-3.3, T-4.3
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 3.2–3.3 tests PASS
  - [ ] UI/UX: N/A

### T-3.3 — TL monthly evaluation screen (5 bars + text)
- Depends on: T-3.2
- Unlocks: T-3.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 3.5 tests PASS
  - [ ] UI/UX gate (averages + text; AR/EN)

### T-3.4 — Employee: My Evaluation view
- Depends on: T-3.3
- Unlocks: T-4.1, T-5.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 3.6 tests PASS
  - [ ] UI/UX gate (5 score-bars + text; read-only)

### T-3.5 — Friday / day-off flags on attendance (display)
- Depends on: T-2.2
- Unlocks: T-4.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Unit flags PASS
  - [ ] UI/UX gate (badge visible when applicable)

---

## Phase 4 — Payroll (Gate 4)

### T-4.1 — Migrations: penalty_rules, payroll_records, adjustments, advances
- Depends on: T-1.1
- Unlocks: T-4.2, T-4.3
- [ ] Task complete
  - [ ] Code done
  - [ ] migrate scratch PASS
  - [ ] UI/UX: N/A

### T-4.2 — Admin: penalty rules UI (per currency, per minute)
- Depends on: T-4.1
- Unlocks: T-4.3
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature CRUD PASS
  - [ ] UI/UX gate (grid; TL cannot open)

### T-4.3 — `PayrollCalculator` + Excel fixture net 2808.00
- Depends on: T-4.1, T-2.2, T-3.2
- Unlocks: T-4.4, T-4.8, T-5.6
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 4.1–4.5 tests PASS
  - [ ] UI/UX: N/A

### T-4.4 — Admin: generate draft payroll (queued/chunked)
- Depends on: T-4.3
- Unlocks: T-4.5, T-4.6
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 4.6, 4.10–4.11 tests PASS
  - [ ] UI/UX gate (month picker → draft table)

### T-4.5 — Admin: review / edit draft / approve
- Depends on: T-4.4
- Unlocks: T-4.7, T-5.1, T-5.2
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 4.6 tests PASS
  - [ ] UI/UX gate (breakdown; approve CTA; flash)

### T-4.6 — TL payroll blocked (routes + menu)
- Depends on: T-4.4
- Unlocks: T-4.7, T-5.6
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 4.7, 4.9 tests PASS
  - [ ] UI/UX gate (no menu; URL → 403)

### T-4.7 — Employee: My Salary (APPROVED/PAID only)
- Depends on: T-4.5
- Unlocks: T-4.8, T-5.2, T-5.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 4.8 tests PASS
  - [ ] UI/UX gate (payslip; DRAFT hidden; `<x-money>`)

### T-4.8 — Advances (سلف) admin CRUD + payroll deduction
- Depends on: T-4.3
- Unlocks: T-5.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Unit + feature PASS
  - [ ] UI/UX gate (admin form; on draft)

---

## Phase 5 — Reports & polish (Gate 5)

### T-5.1 — Admin annual report screen
- Depends on: T-3.4, T-4.5
- Unlocks: T-5.2
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 5.1 tests PASS
  - [ ] UI/UX gate (year grid; admin menu)

### T-5.2 — PDF / Excel export (payslip + payroll)
- Depends on: T-4.7, T-4.5
- Unlocks: T-5.3
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 5.2 tests PASS
  - [ ] UI/UX gate (download buttons; auth only)

### T-5.3 — Full AR/EN pass on all shipped screens
- Depends on: all UI tasks above
- Unlocks: T-5.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 5.3 tests PASS
  - [ ] UI/UX gate (toggle every screen; no missing keys)

### T-5.4 — Mobile polish employee flows
- Depends on: T-2.4, T-2.7, T-4.7
- Unlocks: T-5.5
- [ ] Task complete
  - [ ] Code done
  - [ ] Manual UI review PASS
  - [ ] UI/UX gate (clock/requests/salary on phone)

### T-5.5 — Notifications (email) for pending approvals — optional
- Depends on: T-2.8, T-2.11
- Unlocks: —
- [ ] Task complete **or** [ ] Deferred (N/A)
  - [ ] Code done (if in scope)
  - [ ] Feature fake mail PASS (if in scope)
  - [ ] UI/UX: N/A or gate if UI added

### T-5.6 — Regression: Gate 4 fixtures + TL 403
- Depends on: T-4.6, T-4.3
- Unlocks: —
- [ ] Task complete
  - [ ] Gate 5.5 / payroll fixtures PASS
  - [ ] UI/UX spot-check Admin vs TL payroll

---

## Dependency map

```text
T-0.1 → T-0.2 → T-0.3
                 ↓
              T-1.1 → T-1.2 → T-1.3 → T-1.4 → T-1.5 → T-1.6
                                   ↘ T-1.7 / T-1.8
                 ↓
              T-2.1 → T-2.2 → T-2.3 → T-2.4 → T-2.5 → T-2.6 → T-3.1 → T-3.2 → T-3.3 → T-3.4
                 ├→ T-2.7 → T-2.8 → T-2.9
                 ├→ T-2.10 → T-2.11 → T-2.12
                 ├→ T-2.13
                 └→ T-2.14 (before OT clock UX complete)
                 ↓
              T-4.1 → T-4.2
                   → T-4.3 → T-4.4 → T-4.5 → T-4.6 / T-4.7 → T-4.8
                 ↓
              T-5.1 → T-5.2 → T-5.3 → T-5.4 → T-5.6
```

---

## Progress log

- [x] 2026-08-02 T-0.1 PASS — Laravel 11.55 installed (merged into web root without touching existing files), .env (sqlite fallback, queue=database, cache/SESSION=file), Sanctum + Pint, phpunit sqlite :memory:, `artisan about` + migrate OK
- [x] 2026-08-02 T-0.2 PASS — Vite builds LTR+RTL SCSS + JS (24.6 KB gz); Bootstrap 5.3 + bootstrap-icons self-hosted; Cairo/Tajawal woff2 in public/fonts (no CDN); layouts app/auth + partials; lang ar+en; login shell + /locale/{locale} toggle + SetLocale middleware
- [x] 2026-08-02 T-0.3 PASS — config/menu.php (MANAGER/TEAM_LEADER/EMPLOYEE; payroll only under MANAGER); MenuConfigTest + SmokeTest green (7 tests, 133 assertions)
- [x] 2026-08-02 T-1.1 PASS — users migration replaced per schema doc §1; new migrations: profiles/quals/docs/contacts, contracts/holidays/settings; `settings` table added to schema doc §19; thin models + User/Profile/Contract factories; DatabaseSeeder (admin/leader/radwa/mona@hrpro.test, password `password`); `migrate:fresh --seed` PASS on sqlite dev DB
- [x] 2026-08-02 T-1.2 PASS — LoginRequest (RateLimiter 5 attempts) + AuthenticatedSessionController; role redirect via PostLoginRedirector (MANAGER→admin.dashboard, TL→/dashboard, EMPLOYEE→/profile); language_preference applied to session on login; AuthTest green
- [x] 2026-08-02 T-1.3 PASS — EnsureRole middleware (`role:` alias), EmployeePolicy (viewAny/create/update/updateRates/view/downloadDocument) registered in AppServiceProvider; RoleAccessTest 403 matrix green
- [x] 2026-08-02 T-1.4 PASS — admin.employees CRUD (index paginate 25 + search name/code + status filter + empty state; create/edit wizard form: account+profile+contract+qualifications+contacts in one DB transaction via EmployeeService); StoreEmployeeRequest/UpdateEmployeeRequest; EmployeeCrudTest green
- [x] 2026-08-02 T-1.5 PASS — rates+currency section on admin employee form; RatesPrivacyTest: MANAGER sees rates, TL/EMPLOYEE 403, self profile renders no money fields, policy denies updateRates to non-managers
- [x] 2026-08-02 T-1.6 PASS — DocumentService upload (whitelist jpg/jpeg/png/pdf, 5MB max, random filename, `local` disk) on employee edit; authorized download route (MANAGER or owner); EmployeeDocumentTest green (bad mime/oversize/type rejected, 403 matrix)
- [x] 2026-08-02 T-1.7 PASS — admin.dashboard with 4 `<x-stat-card>` aggregates (total/active/inactive staff, contracts expiring ≤60d) via DashboardStatsService (aggregate SQL only); DashboardTest green incl. empty state
- [x] 2026-08-02 T-1.8 PASS — employee self profile (/profile + /employees/{user}/profile guarded by policy view) read-only: profile, contract, qualifications, contacts, document downloads; no rates; EmployeeProfileTest green (A→B 403, TL 403)
- [x] 2026-08-02 Gate 1 PASS — 57 tests / 304 assertions green; route:list + config:clear clean; pint clean; php -l clean; admin.settings screen (language/currency defaults) added to make settings table used; navbar user dropdown (profile+logout); lang AR/EN parity test
- [x] 2026-08-02 Phase-1 revisions PASS (amends T-1.1/T-1.4/T-1.6/T-1.8 — boxes stay checked):
  1. **Rest days = monthly quota per date** (was `dynamic_day_off` weekday enum — removed everywhere): docs first (schema §20 `rest_days`, users +`work_start_time`/`work_end_time`; MASTER_CONTEXT §2.1/§2.18/§11-r6; API doc). New `rest_days` migration + `RestDay` model; admin Rest Days section on employee edit (add/list/delete, `RestDayService`, duplicate rejected via whereDate closure — sqlite stores date casts with time); work-schedule time inputs on form; seeded 10:00–18:00. Zero `dynamic_day_off` hits repo-wide.
  2. **Multi-file documents**: `documents[]` batch (type applies to batch), per-file whitelist ≤5MB, `DocumentService::storeMany` in one transaction; tests: 3-files batch stored, one-bad-file rejects whole batch.
  3. **Personal photo**: jpg/png ≤2MB → `employee_profiles.personal_photo_path` (local disk, random name) on create/edit; avatar served via authorized `employees.photo` route (MANAGER or self) and shown on admin edit + self profile; religion + work times + rest days added to self profile (field audit vs `docs/Employee-inputes.xlsx` — all present; vacation fields deferred to Phase 2 by design).
  Result: 78 tests / 372 assertions green; migrate:fresh --seed, route:list, config:clear, pint, php -l all clean.
- [x] 2026-08-02 Phase-1 revisions (cont.) PASS (amends T-1.4/T-1.6): dynamic add/remove rows for qualifications + emergency contacts on employee create/edit — bracket names `qualifications[][degree]` / `contacts[][name]` (no JS reindexing), server-rendered rows from old()/DB (one blank row when none), hidden `<template>` cloned by new vanilla page JS `resources/js/pages/employee-form.js` (registered as Vite input, loaded only on create/edit via `@vite`+`@push('scripts')`; no-JS fallback intact). EmployeeService drops fully-blank rows (all fields empty); `required_with` rules force degree on touched qualification rows and name+phone on touched contact rows. Document delete: `DELETE admin.employees.documents.destroy` (scoped 404 cross-employee), `DocumentService::delete` removes file+row in one transaction, confirm-on-submit button in `_documents`. Lang keys AR+EN (`employees.rows.*`, `documents.delete/confirm_delete/flash.deleted`). Also restored `px-3` on sidebar nav-link (parallel agent's test expects `nav-link px-3 active`). Result: 87 tests / 405 assertions green; `npm run build` OK (employee-form.js 0.39 kB); migrate/pint/php -l/route:list clean.
- [x] 2026-08-02 Validation UX fix PASS (closes Kimi unfinished todo): `EmployeeRequest::prepareForValidation` strips fully blank qual/contact placeholder rows before `required_with`; AR+EN `lang/*/validation.php` custom messages + friendly attributes (no raw `qualifications.1.degree`); `autocomplete=off` on dynamic row inputs; ValidationLangTest + blank-placeholder store test green.
- [x] 2026-08-02 Dynamic-row PHP array bug PASS: `qualifications[][degree]` / `contacts[][name]` caused PHP to allocate a new index per field (one visual row → 3 partial rows after validation redirect). Fixed to indexed names `qualifications[0][degree]` + JS reindex on add/remove; create-form assertion guards against empty `[]` names.
- [x] 2026-08-02 T-2.1 PASS — Phase 2 schema migration `2026_08_02_000004_create_attendance_and_requests_tables`: attendance, permissions, vacations, vacation_balances, vacation_credit_logs, shift_assignments per DATABASE_SCHEMA §7–§11/§17; thin models + factories; User relations; AttendanceSchemaTest (tables/uniques/overnight clock_out/relations); `migrate:fresh --seed` PASS; 96 tests / 450 assertions green.
- [x] 2026-08-02 T-2.2 PASS — `ShiftConfig` (8/4/12 planned hours) + `AttendanceService` clockIn/clockOut: Friday/rest_days/official_holiday flags; overnight real datetimes (no +12h hack); late/early minutes vs work_start/end or shift_assignment; EXTRA/OT require SCHEDULED assignment; one open punch; attendance_score; AttendanceException for rule breaks; AttendanceServiceTest Gate 2.1–2.2 green. 105 tests / 478 assertions.

<!--
Example:
- 2026-08-02 T-0.1 PASS — migrate/boot OK
-->

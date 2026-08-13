# HR-Pro — Task Checklists

**How to use**

1. Check off a task only when **all nested boxes** under it are checked.
2. Start a task only when every task listed in **Depends on** is checked.
3. Follow `AGENTS.md` §0 · gates in `docs/BUILD_AND_TEST_GATES.md` · UI rules in `docs/UI_FRAMEWORK.md` §9.
4. **Role chain (`AGENTS.md` §0b):** if a feature is Employee↔TL, **ask** whether TL↔Admin should match before implementing that path (except TL money privacy — never weaken without an explicit product change).

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
- [ ] Role chain: asked user if TL↔Admin applies; implemented only per their answer (AGENTS.md §0b)
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
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.3–2.5 tests PASS
  - [x] UI/UX gate (big CTA; timer; mobile; linked from dashboard)

### T-2.4 — Employee dashboard (today status + clock CTA)
- Depends on: T-2.3
- Unlocks: T-2.5, T-5.4
- [x] Task complete
  - [x] Code done
  - [x] Feature tests PASS
  - [x] UI/UX gate (CTA → clock)

### T-2.5 — Daily report submit + view TL response
- Depends on: T-2.3
- Unlocks: T-2.6
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.10 tests PASS
  - [x] UI/UX gate (form + flash; escaped response)

> Locked rules (2026-08-03): report only after clock-out; **one report per day** on the day’s first (earliest `clock_in`) attendance row; locked once `team_leader_response` is set.

### T-2.6 — TL: respond to daily reports
- Depends on: T-2.5
- Unlocks: T-2.7, T-3.1
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.10–2.11 tests PASS
  - [x] UI/UX gate (queue → reply → employee sees it)

### T-2.7 — Employee: permission request (leave/late)
- Depends on: T-2.1, T-1.2
- Unlocks: T-2.8, T-5.4
- [x] Task complete
  - [x] Code done
  - [x] Feature create/list PASS
  - [x] UI/UX gate (form + status badges; My Requests)

### T-2.8 — TL: approve/reject permissions
- Depends on: T-2.7
- Unlocks: T-2.9
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.6 tests PASS
  - [x] UI/UX gate (Accept/Reject clear)

### T-2.9 — Mark as forced (double penalty)
- Depends on: T-2.8
- Unlocks: T-2.10
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.7 tests PASS
  - [x] UI/UX gate (Force on REJECTED: employee self, TL team, Manager; confirm; FORCED)

### T-2.10 — Employee: vacation request
- Depends on: T-2.1
- Unlocks: T-2.11
- Note: TL↔Admin self-service **yes** (locked). Rules: auto days editable; all types share balance; allow 0 credit; reject overlap; notify on submit.
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.8 tests PASS
  - [x] UI/UX gate (remaining credit on form; Employee + TL + Admin)

### T-2.11 — TL: approve/reject vacation (see credit)
- Depends on: T-2.10
- Unlocks: T-2.12
- Note: Admin decides TL/Manager vacation requests; TL decides team employees only (AGENTS.md §0b locked).
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.8 tests PASS
  - [x] UI/UX gate (credit visible before decision)

### T-2.12 — TL: add vacation credit + history
- Depends on: T-2.1
- Unlocks: T-2.13
- Note: **Retired 2026-08-04** — vacation cost is rest-day swap; credit UI/routes removed. Schema tables may remain unused until annual report needs them.
- [x] Task complete _(retired — product superseded)_
  - [x] Code done
  - [x] Gate 2.9 tests PASS _(then UI removed)_
  - [x] UI/UX gate (add-credit modal; balance updates) _(removed)_

### T-2.13 — TL: team attendance dashboard + %
- Depends on: T-2.3
- Unlocks: T-3.1
- [x] Task complete
  - [x] Code done
  - [x] Gate 2.11 tests PASS
  - [x] UI/UX gate (team list; no money)

### T-2.14 — TL: pre-assign daily rotation + EXTRA / OVERTIME
- Depends on: T-2.1
- Unlocks: _(completes roster UX for T-2.3)_
- [x] Task complete
  - [x] Code done
  - [x] Feature assignment PASS (FT/Online/EXTRA/OT)
  - [x] UI/UX gate (form; preferred on employee clock)

---

## Phase 3 — Evaluation & multipliers (Gate 3)

### T-3.1 — Daily scores on attendance (4 TL + auto attendance)
- Depends on: T-2.6
- Unlocks: T-3.2
- [x] Task complete
  - [x] Code done
  - [x] Gate 3.1, 3.4 tests PASS
  - [x] UI/UX gate (4 inputs + auto attendance shown)

### T-3.2 — `EvaluationService` monthly avg of 5 + monthly text
- Depends on: T-3.1
- Unlocks: T-3.3, T-4.3
- [x] Task complete
  - [x] Code done
  - [x] Gate 3.2–3.3 tests PASS
  - [x] UI/UX: N/A

### T-3.3 — TL monthly evaluation screen (5 bars + text)
- Depends on: T-3.2
- Unlocks: T-3.4
- [x] Task complete
  - [x] Code done
  - [x] Gate 3.5 tests PASS
  - [x] UI/UX gate (averages + text; AR/EN)

### T-3.4 — Employee: My Evaluation view
- Depends on: T-3.3
- Unlocks: T-4.1, T-5.1
- [x] Task complete
  - [x] Code done
  - [x] Gate 3.6 tests PASS
  - [x] UI/UX gate (5 score-bars + text; read-only)

### T-3.5 — Friday / day-off flags on attendance (display)
- Depends on: T-2.2
- Unlocks: T-4.1
- [x] Task complete
  - [x] Code done
  - [x] Unit flags PASS
  - [x] UI/UX gate (badge visible when applicable)

---

## Phase 4 — Payroll (Gate 4)

### T-4.1 — Migrations: penalty_rules, payroll_records, adjustments, advances
- Depends on: T-1.1
- Unlocks: T-4.2, T-4.3
- [x] Task complete
  - [x] Code done
  - [x] migrate scratch PASS
  - [x] UI/UX: N/A

### T-4.2 — Admin: penalty rules UI (per currency, per minute)
- Depends on: T-4.1
- Unlocks: T-4.3
- [x] Task complete
  - [x] Code done
  - [x] Feature CRUD PASS
  - [x] UI/UX gate (grid; TL cannot open)

### T-4.3 — `PayrollCalculator` + Excel fixture net 2808.00
- Depends on: T-4.1, T-2.2, T-3.2
- Unlocks: T-4.4, T-4.8, T-5.6
- [x] Task complete
  - [x] Code done
  - [x] Gate 4.1–4.5 tests PASS
  - [x] UI/UX: N/A

### T-4.4 — Admin: generate draft payroll (queued/chunked)
- Depends on: T-4.3
- Unlocks: T-4.5, T-4.6
- [x] Task complete
  - [x] Code done
  - [x] Gate 4.6, 4.10–4.11 tests PASS
  - [x] UI/UX gate (month picker → draft table)

### T-4.5 — Admin: review / edit draft / approve
- Depends on: T-4.4
- Unlocks: T-4.7, T-5.1, T-5.2
- [x] Task complete
  - [x] Code done
  - [x] Gate 4.6 tests PASS
  - [x] UI/UX gate (breakdown; approve CTA; flash)

### T-4.6 — TL payroll blocked (routes + menu)
- Depends on: T-4.4
- Unlocks: T-4.7, T-5.6
- [x] Task complete
  - [x] Code done
  - [x] Gate 4.7, 4.9 tests PASS
  - [x] UI/UX gate (no menu; URL → 403)

### T-4.7 — Employee: My Salary (APPROVED/PAID only)
- Depends on: T-4.5
- Unlocks: T-4.8, T-5.2, T-5.4
- [x] Task complete
  - [x] Code done
  - [x] Gate 4.8 tests PASS
  - [x] UI/UX gate (payslip; DRAFT hidden; `<x-money>`)

### T-4.8 — Advances (سلف) admin CRUD + payroll deduction
- Depends on: T-4.3
- Unlocks: T-5.1
- [x] Task complete
  - [x] Code done
  - [x] Unit + feature PASS
  - [x] UI/UX gate (admin form; on draft)

---

## Phase 5 — Reports & polish (Gate 5)

### T-5.1 — Admin annual report screen
- Depends on: T-3.4, T-4.5
- Unlocks: T-5.2
- [x] Task complete
  - [x] Code done
  - [x] Gate 5.1 tests PASS
  - [x] UI/UX gate (year grid; admin menu)

### T-5.2 — PDF / Excel export (payslip + payroll)
- Depends on: T-4.7, T-4.5
- Unlocks: T-5.3
- [x] Task complete
  - [x] Code done
  - [x] Gate 5.2 tests PASS
  - [x] UI/UX gate (download buttons; auth only)

### T-5.3 — Full AR/EN pass on all shipped screens
- Depends on: all UI tasks above
- Unlocks: T-5.4
- [x] Task complete
  - [x] Code done
  - [x] Gate 5.3 tests PASS
  - [x] UI/UX gate (toggle every screen; no missing keys)

### T-5.4 — Mobile polish employee flows
- Depends on: T-2.4, T-2.7, T-4.7
- Unlocks: —
- [x] Task complete
  - [x] Code done
  - [x] Manual UI review PASS
  - [x] UI/UX gate (clock/requests/salary on phone)

### T-5.6 — Regression: Gate 4 fixtures + TL 403
- Depends on: T-4.6, T-4.3
- Unlocks: —
- [x] Task complete
  - [x] Gate 5.5 / payroll fixtures PASS
  - [x] UI/UX spot-check Admin vs TL payroll

---

## Phase 6 — Simple roster / clock / manual penalties (2026-08-04 product lock)

### T-6.1 — Clock must match day’s plan
- Depends on: T-2.14, T-2.2
- Unlocks: T-6.3
- [x] Task complete
  - [x] Code: no free FT↔Online switch when base assignment exists; no plan → `shift_type_default` only; EXTRA/OT if assigned
  - [x] Unit/feature tests PASS
  - [x] UI/UX gate (clock banner + available types AR/EN)

### T-6.2 — Payroll auto money penalties off (facts only)
- Depends on: T-4.3, T-4.5
- Unlocks: T-6.3
- [x] Task complete
  - [x] `payroll.auto_penalties` default false; calculator zeros late/absence/permission/forced/hours EGP
  - [x] Fixture/regression tests enable auto when asserting Excel 2808
  - [x] Payslip still shows fact fields (minutes/days/hours)
  - [x] UI/UX: N/A (behavior) + copy if needed

### T-6.3 — Hours planned vs actual on payroll review
- Depends on: T-6.1, T-6.2, T-2.13
- Unlocks: T-6.4
- [x] Task complete
  - [x] Admin payroll show: planned / actual / shortfall list (reuse `HoursComparisonService`)
  - [x] Feature test PASS
  - [x] UI/UX gate (AR+EN, RTL/LTR, responsive)

### T-6.4 — Dashboard tables: search/filter + column alignment
- Depends on: T-6.3, T-1.7, T-2.4, T-2.13
- Unlocks: T-6.5
- [x] Task complete
  - [x] Shared `.table-hr` + `<x-table-toolbar>` + client filter JS
  - [x] Admin / TL / Employee dashboard tables: search + filters; headers align with cells (RTL/LTR)
  - [x] Feature tests PASS
  - [x] UI/UX gate (AR+EN, RTL+LTR, 375/768/1280)

### T-6.5 — All remaining tables: search/filter + column alignment
- Depends on: T-6.4
- Unlocks: —
- [x] Task complete
  - [x] Every list table (employees, payroll, advances, penalties, annual, approvals, reports, evaluations, shifts, requests, salary, rest days, …) uses `.table-hr` + toolbar where rows can grow
  - [x] Detail/KV tables use `.table-hr` + `col-num`/`num-ltr` (no cell `dir=ltr`)
  - [x] Feature smoke tests PASS
  - [x] UI/UX gate (AR+EN, RTL+LTR, responsive)


### T-6.6 — Optional multi photos on clock-in and clock-out
- Depends on: T-6.1, T-2.3, T-2.13
- Unlocks: —
- [x] Task complete
  - [x] Schema `attendance_photos` + docs
  - [x] Optional multi jpg/jpeg/png/webp ≤50MB each (**no count limit**) on clock-in **and** clock-out
  - [x] Authorized view: self · TL (team) · Admin (employees + TLs) — role chain **yes**
  - [x] Shown on clock screen + TL/Admin attendance (today board + hours day list) + employee sessions
  - [x] Unit/feature tests PASS
  - [x] UI/UX gate (AR+EN, RTL/LTR, responsive)

---


### T-7.1 — ACCOUNTANT role (payroll + advances; Manager approves)
- Depends on: T-4.5, T-4.8, T-1.3
- Unlocks: —
- [x] Task complete
  - [x] Role `ACCOUNTANT` in schema/User/menu/seed (`accountant@hrpro.test`)
  - [x] Self-service + payroll (view/generate/edit) + advances CRUD
  - [x] Approve payroll Manager-only; no TL; Manager decides leave
  - [x] AccountantRoleTest + MenuConfigTest green
  - [x] UI/UX: AR+EN role label; approve button hidden for accountant

### T-7.2 — Required punch GPS + soft office geofence (1 office / 1 km)
- Depends on: T-2.3, T-6.6, T-1.x settings
- Unlocks: —
- [x] Task complete
  - [x] attendance punch lat/lng/accuracy/distance/outside flags
  - [x] Admin Settings: office lat/lng + radius (default 1000 m)
  - [x] Browser GPS required on clock in/out (block if denied); soft warn if outside
  - [x] Notify employee + TL + Admin; show Maps link like photos
  - [x] TL↔Admin self-clock yes; tests + UI/UX AR+EN RTL/LTR responsive

### T-7.3 — Dual default shifts (FULL_TIME + ONLINE_HALF)
- Depends on: T-1.4, T-2.3, T-4.x payroll hours
- Unlocks: —
- [x] Task complete
  - [x] `shift_types_default` JSON; migrate ONLINE_FULL → both
  - [x] Admin create/edit: two Assign-style rows (type + start/end); clock allows both same day
  - [x] Planned hours = sum of defaults on workdays
  - [x] Payroll Hours tab: required / actual / absent per shift; one salary
  - [x] DualDefaultShiftsTest + related suite green; UI AR+EN

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
                 ↓
              T-6.1 → T-6.3 → T-6.4 → T-6.5
              T-6.2 → T-6.3
              T-6.1 + T-2.3 + T-2.13 → T-6.6
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
- [x] 2026-08-02 T-2.3 PASS — Employee clock screen (`/clock`): FT/Online½/Online-full always selectable; EXTRA/OT only when SCHEDULED assignment for today; big CTA + live elapsed timer; ClockController thin + ClockInRequest Rule::in(available); AttendanceException → ValidationException (session errors / 422 JSON); ClockTest Gate 2.3–2.5 (happy path, second open rejected, OT without assign rejected, FT→Online after out, TL/Manager 403); AR+EN `lang/*/clock.php`. 113 tests / 522 assertions; `npm run build` OK.
- [x] 2026-08-02 Rotation rules (user lock) — TL assigns day shift; no assign → default or choose; employee may still switch FT↔Online; EXTRA/OT still need assignment. Docs: MASTER_CONTEXT §1.7, schema §17 + migrate `000005`. Clock prefers assignment then `shift_type_default`; banner + AR/EN hint. Unit/feature coverage for prefer/switch/assigned hours.
- [x] 2026-08-02 T-2.14 PASS — TL/Manager shift roster at `/team-leader/shifts`: `ShiftAssignmentService` assign/cancel/list (team-scoped); Policy; StoreShiftAssignmentRequest; create FT/Online/EXTRA/OT + cancel SCHEDULED; duplicate blocked; employee 403; assigned FT preferred on employee clock. AR+EN `lang/*/shifts.php`. ShiftAssignmentTest Gate 3.7. 131 tests / 583 assertions.
- [x] 2026-08-02 Docs sync — MASTER_CONTEXT §1.7/§2.10; DATABASE_SCHEMA §7/§17 notes; API §3/§6 web routes + rotation rules; UI_FRAMEWORK clock/shifts screens; BUILD_AND_TEST_GATES Gate 2.1–2.5 + 3.1/3.7 status; Credential smoke path; PAYROLL_CALCULATION ShiftConfig row.
- [x] 2026-08-02 T-2.4 PASS — Employee dashboard `/employee/dashboard`: status + preferred/assigned shift + hours/sessions today + CTA → clock; login lands on dashboard; AR+EN; EmployeeDashboardTest. 135 tests / 598 assertions.
- [x] 2026-08-03 T-2.14 amend — TL shifts: **My team** roster + **Edit** SCHEDULED assignments; Mona seeded under leader; 137 tests / 606 assertions.
- [x] 2026-08-03 Dashboard UX — Employee **My assigned shifts** list + month calendar on employee / TL / admin dashboards (shifts + holidays markers).
- [x] 2026-08-03 T-2.5 PASS — Employee daily report `/report`: after clock-out only; one/day on first shift; locked after TL response; XSS escaped; DailyReportTest Gate 2.10. 146 tests / 643 assertions.
- [x] 2026-08-03 T-2.6 PASS — TL report queue `/team-leader/reports`: pending/replied/all filters; reply locks employee report; team-scoped (other team 403); Manager can reply any; XSS escaped; ReportReviewTest Gate 2.10–2.11. 153 tests / 677 assertions.
- [x] 2026-08-03 T-2.13 PASS — Planned vs actual hours on employee + TL/admin dashboards: day list, calendar (shift+hours), month totals; profile work-window fallback; today who’s-in board; team-scoped; HoursComparisonService + TeamAttendanceDashboardTest. 160 tests / 699 assertions.
- [x] 2026-08-03 T-2.7 PASS — Employee My Requests `/requests`: leave/late permission form + paginated list + status badges; vacations tab placeholder; PermissionRequestTest. 168 tests / 727 assertions.
- [x] 2026-08-03 T-2.8 PASS — TL Approvals `/team-leader/approvals`: Accept → ACCEPTED/0; Reject → REJECTED + penalty_amount (config rates); team-scoped; no money in UI; Gate 2.6. PermissionApprovalTest. 177 tests / 757 assertions.
- [x] 2026-08-03 T-2.9 PASS — Force: REJECTED→FORCED, penalty×2, `is_double_penalty`; employee self + Manager; TL 403; Force button + confirm on My Requests; Gate 2.7. PermissionForceTest. 183 tests / 777 assertions.
- [x] 2026-08-03 T-2.9 follow-up — TL may force team REJECTED (same ×2); Force on Approvals for TL; actor skipped in force notify; MASTER_CONTEXT matrix updated.
- [x] 2026-08-03 Self-service follow-up — TL + Admin: Clock, Daily report, My Requests; Manager decides TL permissions & replies to TL reports; StaffSelfServiceTest.
- [x] 2026-08-03 Rule locked — AGENTS.md §0b / MASTER_CONTEXT §1.7d: Employee↔TL flows must also exist TL↔Admin (except payroll money).
- [x] 2026-08-03 Rule updated — §0b: agents must **ask** before applying Employee↔TL pattern to TL↔Admin (yes/no/partial); payroll money still never for TL.
- [x] 2026-08-03 Fix — rest days (`rest_days`) show on dashboard calendar + day table; planned=0 unless shift assigned; legend AR/EN.
- [x] 2026-08-03 UX — TL/Admin dashboard focus **Me** (own calendar + day table by default); switch to team member via picker.
- [x] 2026-08-03 T-2.10 PASS — Vacation request on My Requests: credit card; ANNUAL/SICK/EMERGENCY; auto days editable; allow 0 remaining; overlap reject; pending on balance; notify TL+Admin; TL/Admin self-service. VacationRequestTest. Gate 2.8.
- [x] 2026-08-03 Rest weekdays — per-employee ISO weekdays on `users.rest_weekdays`; drop date `rest_days`; Admin form + TL `/team-leader/rest-weekdays`; calendar/clock use weekly pattern. Suite green 202 tests / 937 assertions (HoursComparisonServiceTest cleared for Friday default rest).
- [x] 2026-08-03 T-2.11 PASS — Approvals vacations section: remaining credit visible; Accept → pending→used; Reject → pending released; TL team / Manager all (incl. TL self); VacationDecisionNotification; VacationApprovalTest. Gate 2.8. 208 tests / 967 assertions.
- [x] 2026-08-03 T-2.12 PASS — Vacation Credit `/team-leader/vacation-credit`: add days modal; accrued/remaining update; credit log history; TL team / Manager employees+TLs; employee sees remaining on My Requests; VacationCreditTest. Gate 2.9. 216 tests / 998 assertions.
- [x] 2026-08-03 Product lock — Rest days back to **date-based** (`rest_days`); remove `rest_weekdays`. Vacation/emergency = **swap** one rest date for one day off (approve deletes rest row; calendar shows vacation). 214 tests / 989 assertions.
- [x] 2026-08-04 Cleanup — remove Vacation Credit UI/routes/menu (obsolete after rest-day swap). Rest Days = the pool.
- [x] 2026-08-04 Vacation Force (A) PASS — REJECTED→FORCED keeps rest day; day off still vacation; Force on Approvals + My Requests (self/TL/Manager); notify; VacationForceTest. Schema FORCED.
- [x] 2026-08-04 T-4.1 PASS — Migrations `penalty_rules`, `payroll_records`, `payroll_adjustments`, `advances`; thin models/factories; PenaltyRuleSeeder; PermissionPenaltyCalculator reads DB rules (config fallback); PayrollSchemaTest.
- [x] 2026-08-04 T-4.2 PASS — Admin `/admin/config/penalties` grid CRUD; Policy Manager-only; TL/employee 403; cached rules; AR+EN; `<x-money>`; PenaltyRuleTest.
- [x] 2026-08-04 T-3.1 PASS — Daily scores on report review: 4 TL inputs (0–100) + read-only auto `attendance_score`; TL team / Manager any (same as reply); DailyScoreService; DailyScoreTest; Gate 3.1+3.4.
- [x] 2026-08-04 UX — Employee Daily Report: remove date+Load; open on today; past days via history list only.
- [x] 2026-08-04 UX — TL Daily reports queue: default **All reports**; filter by employee + status; paginated list.
- [x] 2026-08-04 T-3.2 PASS — `evaluations` table; `EvaluationService::monthlyFromDaily` (/5) + legacy four-metric **0.97375**; `saveMonthly` text+avgs; EvaluationServiceTest. Gate 3.2–3.3. Unlocks T-3.3 + T-4.3.
- [x] 2026-08-04 T-3.3 PASS — `/team-leader/evaluations`: month picker + team list; detail 5 score-bars + monthly text; TL team / Manager; `<x-score-bar>`; MonthlyEvaluationTest. Gate 3.5.
- [x] 2026-08-04 T-3.4 PASS — `/evaluation` My Evaluation: read-only 5 bars + TL text; history; own only; MyEvaluationTest. Gate 3.6.
- [x] 2026-08-04 T-3.5 PASS — Friday/rest/holiday work badges via `<x-attendance-flags>` on clock + dashboard sessions + hours day table; AttendanceFlagsDisplayTest.
- [x] 2026-08-04 T-4.3 PASS — `PayrollCalculator` + Input/Result DTOs; `config/payroll.php` multipliers; absence day multipliers from `penalty_rules` (seed 1×/3×); Excel net **2808.00**; Gate 4.1–4.5 unit tests. Unlocks T-4.4, T-4.8, T-5.6.
- [x] 2026-08-04 T-4.4 PASS — `PayrollAggregator` + `PayrollGenerator` + `GeneratePayrollJob` (chunk ≤100); `/admin/payroll` month picker → draft table + approve; coverage Gate 4.10; `PayrollPolicy` TL 403. Unlocks T-4.5, T-4.6.
- [x] 2026-08-04 T-4.5 PASS — Draft show breakdown; edit counts + recalculate; adjustments CRUD; approve locks edit; `PayrollDraftService`; PayrollDraftTest. Gate 4.6. Unlocks T-4.7, T-5.1, T-5.2.
- [x] 2026-08-04 T-4.6 PASS — PayrollPrivacyTest: TL 403 on all payroll routes; sidebar has no payroll; Manager menu keeps it. Gate 4.7/4.9. Unlocks T-4.7, T-5.6.
- [x] 2026-08-04 T-4.7 PASS — `/salary` My Salary: APPROVED/PAID list + read-only breakdown; DRAFT + others 403; `<x-money>`; MySalaryTest. Gate 4.8. Unlocks T-4.8, T-5.2, T-5.4.
- [x] 2026-08-04 T-4.8 PASS — Advances CRUD (`/admin/advances`); employee `/advances/my`; generate pulls monthly deduction; approve decrements remaining → FULLY_DEDUCTED; AdvanceTest. Phase 4 complete. Unlocks T-5.1.
- [x] 2026-08-04 T-5.1 PASS — `ReportService::annualEvaluation` (AVG total_score) + annual year grid; `/admin/reports/annual`; contract/vacation/penalties/bonuses/net; AnnualReportTest. Gate 5.1. Unlocks T-5.2.
- [x] 2026-08-04 T-5.2 PASS — Payslip PDF (Dompdf); monthly Excel (PhpSpreadsheet, queued/chunked); Manager + employee own APPROVED/PAID; TL 403; PayrollExportTest. Gate 5.2. Unlocks T-5.3.
- [x] 2026-08-04 T-5.6 PASS — PayrollRegressionTest: Excel net 2808 + TL 403 on payroll/exports/advances/annual. Gate 5.5.
- [x] 2026-08-04 Removed T-5.5 — Email notifications out of scope; in-app DB notifications remain.
- [x] 2026-08-04 T-5.3 PASS — LangParity all AR/EN files; LocaleToggleTest RTL/LTR; localized `<x-money>` + payslip PDF keys. Gate 5.3.
- [x] 2026-08-04 T-5.4 PASS — Mobile polish: salary stacked cards, scrollable request tabs, larger clock CTA; MobileEmployeeUiTest. Phase 5 complete.
- [x] 2026-08-04 Payroll UX — monthly 5 scores (Performance/Behavior/Productivity/Interest Return/Attendance + total) on Admin payroll + Employee My Salary breakdown; draft recalc refreshes base salary from employee; generate allows incomplete attendance with warning.
- [x] 2026-08-04 Phase 6 locked — clock matches plan (FT + Online ½ + Online full + EXTRA/OT); penalties manual-only (`auto_penalties` default off); hours facts informational.
- [x] 2026-08-04 T-6.1 PASS — Clock enforces day’s plan; default only when unassigned; EXTRA/OT if assigned. ClockTest + AttendanceServiceTest.
- [x] 2026-08-04 T-6.2 PASS — `payroll.auto_penalties=false` by default; Excel 2808 tests force true; advances still auto.
- [x] 2026-08-04 T-6.3 PASS — Admin payroll Review shows planned vs actual hours + shortfall by date/shift/time.
- [x] 2026-08-04 T-6.4 PASS — Dashboard tables (Admin/TL/Employee): `.table-hr` alignment (no `dir=ltr` on cells), `<x-table-toolbar>` search+filters, shared filter JS; AR/EN keys; DashboardTest + TeamAttendance + EmployeeDashboard + LangParity green.
- [x] 2026-08-05 UX — Dynamic payroll multipliers in Admin Settings (OT/Friday/rest/holiday/extra_pay); SettingService cached; calculator reads settings with config fallback.
- [x] 2026-08-05 UX — Manual Excel «إضافي»: `extra_days` + `extra_pay` on draft (amount or days×day-rate); OT 8h→250 explained/tested; PDF/salary show include extra_pay.
- [x] 2026-08-05 UX — Payslip PDF Arabic: Dompdf glyph shaping via `khaled.alshamaa/ar-php` + `DompdfArabic`; RTL table + Tajawal TTF (`resources/fonts`); PayrollExportTest + DompdfArabicTest.
- [x] 2026-08-04 T-6.5 PASS — System-wide tables: Admin (employees/payroll/advances/penalties/annual/show), TL (approvals/reports/evaluations/shifts/rest-days), Employee (salary/requests/advances/profile/evaluation history); payroll partials aligned; `TableFilterUiTest` green.
- [x] 2026-08-05 UX — Payroll review tabs + rest-day pool feeds `rest_days` on Generate/draft open (Rest Days screen → aggregator); advances sync on open.
- [x] 2026-08-03 UX — Approvals RTL: replaced misaligned table with labeled cards; in-app DB notifications (permission submit/decision, report submit/reply) + navbar bell; NotificationTest. 187 tests / 793 assertions.
- [x] 2026-08-05 UX — TL `/team-leader/rest-days` redesign: per-member cards (avatar initial + upcoming-count badge), dates split Upcoming vs collapsed Past, pending-vacation swaps show locked **Pending vacation** badge instead of a failing Remove (new `RestDay::pendingReservation`, eager loaded); validation errors surfaced in top alert; AR/EN keys; `RestDayTest` + reserved-badge case. 309 tests / 3156 assertions.
- [x] 2026-08-05 UX — Admin `/admin/employees` list: profile photo (authorized `employees.photo` route, initials fallback) in name cell; new **Phone** (`profile.phone`) + **Default Shift Type** columns; phone added to toolbar filter text; AR/EN reuse existing keys; `EmployeeCrudTest` column case. 310 tests / 3159 assertions.
- [x] 2026-08-05 UX — Admin `/admin/employees` dedup filters: removed redundant JS `<x-table-toolbar>` (client-side filter hid matches on other pages); server-side GET form (q + status, paginated) is the single filter; `TableFilterUiTest` asserts no `data-table-filter-root` on this route. 311 tests / 3161 assertions.
- [x] 2026-08-05 UX — Employee create/edit: changing **Default Shift Type** prefills work start/end from new `ShiftConfig::DEFAULT_TIME_WINDOWS` (FT 10–18, Online ½ 06–10, Online full 10–22); times stay editable; map passed via `data-shift-times` on the select; `employee-form.js` change handler; vite rebuilt; `EmployeeCrudTest` map-render case. 312 tests / 3166 assertions.
- [x] 2026-08-05 UX — Duplicate filters removed system-wide: JS `<x-table-toolbar>` dropped from every paginated list that already filters server-side — Admin advances (GET employee+status), TL reports (GET employee+status), TL approvals (status pills, both permissions + vacations tables); `data-filter-*` attrs + orphan empty rows cleaned; `TableFilterUiTest` now asserts server-side-only filtering on all four pages (employees from earlier + these three). Month/date/year pickers (payroll, evaluations, shifts, dashboards) kept — dataset navigation, not duplicate filters. 314 tests / 3196 assertions.
- [x] 2026-08-05 Config — App timezone locked to **Africa/Cairo** (`config/app.php` default + `APP_TIMEZONE` in `.env`/`.env.example`; was UTC). No hardcoded UTC in code. Existing data converted one-time: every stored DATETIME/TIMESTAMP (attendance clock_in/out, created_at, etc. — 40 columns across 20 tables) shifted +3h (Aug 2026 = UTC+3 DST); DATE/TIME-only columns untouched; sqlite backup at `database/database.sqlite.bak-20260805-utc`. Suite green under Cairo. 320 tests / 3277 assertions.
- [x] 2026-08-05 UX — Employee `/profile` redesign: `welcome-hero` identity header (photo/initials, AR+EN names, chips for code/role/shift/work hours); flat `<dl>` replaced by icon item grid via new `<x-profile-item>`; new Work Information section (`profile.work_info` AR/EN); section icons on every card; rest-day chips show weekday + date. No SCSS/JS changes (existing styles reused). 321 tests / 3282 assertions.
- [x] 2026-08-05 UX — Employee `/profile` round 2: two-column layout — sticky identity sidebar (photo, name, chips, email/phone/team-leader facts) + details column; rest days grouped by month (F Y headings); documents as file-type icon rows (type→icon map) replacing table + toolbar; all sections use `<x-empty-state>` icons. 339 tests / 3404 assertions.
- [x] 2026-08-05 Feature — Clock-out routes to daily report: after successful `employee.clock.out`, if the day's main attendance has no `daily_report`, employee is redirected to `/report?date=…` with a "write your daily report" flash (`clock.flash.clocked_out_report` AR/EN); already-filled report keeps the old redirect to `/clock`. `ClockTest` happy path updated + pre-filled case added. 345 tests / 3454 assertions.
- [x] 2026-08-05 Feature — Daily report enforcement: `DailyReportService::latestUnreportedClosedDay` (latest closed day's main attendance with blank report); `AttendanceService::clockIn` throws `clock.errors.report_required` when that date is before the punch date (same-day extra punches unaffected); warning banner with "write it now" link on `/clock` + employee dashboard (`clock.report_missing`/`report_write` AR/EN). Unit block/allow-after-submit test + ClockTest banner/422 case. 347 tests / 3467 assertions.
- [x] 2026-08-05 UX — Excel draft deductions: `excused_absence_days` / `unexcused_absence_days` / `hours_deduction` editable on Admin payroll draft with live money preview; calculator always applies those three (late/permission still behind `auto_penalties`); PDF + employee salary show `hours_deduction_amount`. PayrollCalculatorTest + PayrollDraftTest green.
- [x] 2026-08-05 Fix — `OTHER_DEDUCTION` no longer inflates `advance_deductions` (hero «السلف»); Other lines listed separately on Pay tab; OT UI single hours field.
- [x] 2026-08-05 UX — Clock: show scheduled start/end before clock-in; store + show late minutes immediately after clock-in (AR/EN). ClockTest + AttendanceServiceTest green.
- [x] 2026-08-05 UX — Arabic copy: «وردية» → «دوام» / مواعيد العمل across clock, nav, shifts, dashboard, report, employees, settings, payroll (EN aligned to work type/schedule).
- [x] 2026-08-05 UX — Font flash: preload Cairo (locale-aware) + `font-display: block`; drop unused Tajawal from web `fonts.css` (PDF still uses Tajawal TTF). SmokeTest green.
- [x] 2026-08-05 Feature — Auto clock-out: after expected end + 60m grace, force close at end+grace (hours count); `is_auto_clock_out` badge; notify employee+TL+managers; `attendance:auto-clock-out` every 5m. AttendanceServiceTest green.
- [x] 2026-08-05 Fix — Shift windows match Excel sheet: FT 10:00–18:00 (8h), Online½ **06:00–10:00** (4h, was wrongly 10–14/2pm), Online full 10:00–22:00 (12h). Late/auto clock-out use **shift-type** window (not only profile times).

<!--
Example:
- 2026-08-02 T-0.1 PASS — migrate/boot OK
-->
- [x] 2026-08-12 T-6.6 PASS — Optional multi punch photos on clock-in **and** clock-out (jpg/jpeg/png/webp ≤50MB, max 5); `attendance_photos` + AttendancePhotoService; authorized view self·TL(team)·Manager (TL↔Admin **yes**); UI on `/clock`, employee sessions, TL/Admin today board + hours day list, report review; AR+EN; AttendancePhotoTest + schema/Clock/LangParity green.
- [x] 2026-08-12 Amend T-6.6 — removed punch-photo **count** limit (keep ≤50MB each); AR+EN hint updated; AttendancePhotoTest accepts 8 files.
- [x] 2026-08-12 UX — Admin payroll show: second button **Export full review PDF** (`export-review.pdf`) covers all tabs (pay / edit draft / attendance / evaluation / hours); Admin-only (TL 403); PayrollExportTest green.
- [x] 2026-08-12 T-7.1 PASS — ACCOUNTANT role: self-service + payroll (no اعتماد) + advances CRUD; `team_leader_id` null → Manager decides leave; seed accountant@hrpro.test; TL still money-blocked.
- [x] 2026-08-13 T-7.2 PASS — Required punch GPS + soft 1-office geofence (Admin Settings lat/lng, default 1 km): store lat/lng/accuracy/distance/outside; accept outside with warn + notify employee/TL/Admin; Maps links like photos; TL↔Admin self-clock yes; ClockLocationTest + GeofenceServiceTest green.
- [x] 2026-08-13 T-7.3 PASS — Dual defaults FULL_TIME+ONLINE_HALF; every workday both expected; payroll Hours shows required/actual/absent per shift; ONLINE_FULL legacy→both; Admin form two dropdown+time rows (not checkboxes); DualDefaultShiftsTest green.

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
- Unlocks: T-6.7
- [x] Task complete
  - [x] Schema `attendance_photos` + docs
  - [x] Optional multi jpg/jpeg/png/webp ≤50MB each (**no count limit**) on clock-in **and** clock-out
  - [x] Authorized view: self · TL (team) · Admin (employees + TLs) — role chain **yes**
  - [x] Shown on clock screen + TL/Admin attendance (today board + hours day list) + employee sessions
  - [x] Unit/feature tests PASS
  - [x] UI/UX gate (AR+EN, RTL/LTR, responsive)

### T-6.7 — Description per punch photo
- Depends on: T-6.6
- Unlocks: —
- [x] Task complete
  - [x] `attendance_photos.description` + schema doc
  - [x] Clock-in/out: optional caption per selected image; shown on viewers
  - [x] AttendancePhotoTest + LangParity

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
- Unlocks: T-7.7
- [x] Task complete
  - [x] `shift_types_default` JSON; migrate ONLINE_FULL → both
  - [x] Admin create/edit: two Assign-style rows (type + start/end); clock allows both same day
  - [x] Planned hours = sum of defaults on workdays
  - [x] Payroll Hours tab: required / actual / absent per shift; one salary
  - [x] DualDefaultShiftsTest + related suite green; UI AR+EN

### T-7.7 — Online full day as employee default shift
- Depends on: T-7.3
- Unlocks: —
- [x] Task complete
  - [x] `ONLINE_FULL` in `User::DEFAULT_SHIFT_OPTIONS` (create/edit up to 3 rows)
  - [x] Clock / hours / payroll use profile defaults (12h window 10→22); AR+EN
  - [x] DualDefaultShiftsTest + EmployeeCrudTest + LangParity

### T-7.4 — Job title on every user (employees, TLs, accountants, managers)
- Depends on: T-1.4, T-1.8
- Unlocks: —
- [x] Task complete
  - [x] `users.job_title_ar` / `users.job_title_en`; migrate backfill from role if blank
  - [x] Admin create/edit + list + search; self profile + payslip
  - [x] Blank form titles default to role label so every user has one
  - [x] EmployeeCrudTest + EmployeeProfileTest + LangParity; UI AR+EN RTL/LTR
  - [x] UI/UX gate (create/edit/list/profile; AR+EN keys; table-responsive)

### T-7.5 — ASSISTANT role (Admin operations helper)
- Depends on: T-1.4, T-2.6, T-2.14, T-7.1
- Unlocks: —
- [x] Task complete
  - [x] Role `ASSISTANT`; seed `assistant@hrpro.test`; reports to Admin
  - [x] Employee data entry including salary/rates; shifts + rest days company-wide
  - [x] Follow all daily reports (view-only) + missing-report alerts + admin dashboard
  - [x] Self-service clock/report/requests/profile/own salary; no payroll/approvals/evaluations
  - [x] AssistantRoleTest + MenuConfigTest + RoleAccessTest; UI AR+EN

### T-7.6 — Attendance history (person / dates / absent / photos / location)
- Depends on: T-2.13, T-6.6, T-7.2, T-7.5
- Unlocks: —
- [x] Task complete
  - [x] Dedicated list: employee, date range, day type, photos, GPS
  - [x] Manager + Assistant company-wide; TL team-only (same as dashboard hours; TL↔Admin already yes)
  - [x] AttendanceHistoryTest + LangParity; UI AR+EN RTL/LTR
  - [x] UI/UX gate (list + filters; table-responsive; 375/768/1280)

### T-7.7 — Admin login-as-user (impersonation)
- Depends on: T-1.2, T-1.4, T-7.5
- Unlocks: —
- [x] Task complete
  - [x] Settings toggle `impersonation_enabled` (default off)
  - [x] Manager Login as from employee list/edit; Return to admin (no password)
  - [x] Nested switch blocked; Assistant/TL/employee 403; logout ends session
  - [x] ImpersonationTest + SettingsTest + LangParity; UI/UX AR+EN RTL/LTR
  - [x] UI/UX gate (settings toggle + list button + banner; 375/768/1280)

### T-7.8 — DRIVER role (clock + payroll photos)
- Depends on: T-7.2, T-7.5, T-6.6, T-6.7, T-8.2
- Unlocks: —
- [x] Task complete
  - [x] Role `DRIVER`; seed `driver@hrpro.test`; up to 2 TLs like employees
  - [x] Self-service **clock + daily report** (no leave/salary menus); report gate same as employee
  - [x] Punch photos required in+out (≥1 + description); **GPS required every punch**
  - [x] Admin payroll includes Drivers; Manager+Assistant CRUD
  - [x] DriverRoleTest + MenuConfigTest + PortalStats + LangParity; UI AR+EN

### T-7.9 — Multi-punch same shift + gap approval + time review
- Depends on: T-7.6, T-7.8, T-8.4
- Unlocks: —
- [x] Task complete
  - [x] Schema: `attendance_segments`, `attendance_gaps`, credited/raw hours, late override, overtime TL→Admin
  - [x] Employee/Driver: clock in → out → in again same shift; gaps notify TL then Admin queue
  - [x] TL/Admin: gap approve/reject on `/team-leader/approvals`; time review on attendance history
  - [x] Early/late + credited vs raw visible on clock + ops history; cap 8h unless OT approved
  - [x] AttendanceMultiPunchTest + updated ClockTest/DualDefaultShiftsTest; 542 tests PASS

### T-7.10 — Driver mid-day field uploads + force-close on clock-in
- Depends on: T-7.8, T-7.9
- Unlocks: —
- [x] Task complete
  - [x] R5: stale cross-day open punch force-closed on `/clock` + clock-in (≥1h before shift start; **no cron**)
  - [x] R6b: DRIVER mid-day FIELD photos while shift open — GPS + photo + description required; linked to open segment
  - [x] Schema `attendance_photos.kind` + FIELD GPS columns; DriverRoleTest field upload cases
  - [x] R2: system recommended vs payroll-credited side-by-side (`x-attendance-time-breakdown`); TL reset to system; clock + hours dashboard + attendance history
  - [x] R3: daily report reminder banner after clock-out (late/early hints)
  - [x] R4: full breakdown everywhere — raw/credited/late/early/OT/gaps via metrics + breakdown components; clock, dashboard, report, history, hours list
  - [x] R7: gap approval polish — pending/decided/all filters; TL note + Admin note; notifications w/ URLs; gap status component; dual-TL scope
  - [x] R9: shift window validation on employee save + TL assign; reject windows longer than planned+1h; repair migration for leftover mismatches
  - [x] R8: overtime workflow polish — TL recommend w/ system-suggested minutes; Admin/Assistant approve/reject; status badges + credited cap; approvals queue + notifications
  - [x] R10: full suite green; AttendanceFlagsDisplayTest frozen to punch day (R5 stale-close); T-7.10 closed

### T-7.11 — Supervisor end-shift (late clock-out)
- Depends on: T-7.10
- Unlocks: —
- [x] Task complete
  - [x] TL (team) / Manager / Assistant can End shift on open punch at **actual now** (no GPS/photos)
  - [x] After close: existing overtime TL recommend → Admin/Assistant approve (actual hours visible)
  - [x] UI: today board, hours day list, attendance history; notify employee + other TLs/admins
  - [x] AttendanceForceClockOutTest + LangParity; UI AR+EN
  - [x] Role chain: TL applies overtime; Admin approves (same as R8). Assistant may end shift like Admin ops.

---

## Phase 8 — Dual team leaders (max 2) + attributed evaluations

**Locked 2026-08-18 (user confirmed):**
1. Max **two** TLs per employee; **one required**, second optional. Both equal for view/ops (not primary-only).
2. Employee appears on **both** TL dashboards / lists / history / alerts.
3. **Either** TL may act (approve, score, reply, assign shift/rest). The acting user is stored on that row (`*_by_id`). No silent overwrite of the other TL’s evaluation.
4. Daily scores: **per TL** (new table), not one shared overwrite on `attendance`. Attendance % stays system-wide.
5. Monthly evaluation: **one row per (employee, TL, month, year)** — that TL’s % averages + that TL’s text. Employee “My Evaluation” shows both. Annual/payroll combined = average of the two `total_score` values when both exist.
6. Daily report: **one official reply still locks** the report; store `responded_by_id`. Second TL can read, not a second thread (unless user later asks).
7. Notify **both** TLs + existing Admin/Assistant copies. TL still **never** sees money.
8. Assistant/Admin assign the two TLs on the employee form. TL cannot assign themselves onto someone else’s employee.

### T-8.1 — Schema: `employee_team_leaders` + drop single `users.team_leader_id`
- Depends on: T-7.6
- Unlocks: T-8.2
- [x] Task complete
  - [x] Table `employee_team_leaders`: `user_id`, `team_leader_id`, `assigned_by`, unique(user, tl); app rule max 2; both must be `TEAM_LEADER`
  - [x] Backfill from `users.team_leader_id`; then drop `users.team_leader_id` (no unused column)
  - [x] `TeamMembershipService` is the only “is this TL on this employee?” API (`leads`, `leaderIds`, `visibleEmployeeIds`)
  - [x] Schema doc + User relations; DualTeamLeaderSchemaTest; `php -l` + Pint

### T-8.2 — Employee form + lists: two TLs
- Depends on: T-8.1
- Unlocks: T-8.3
- [x] Task complete
  - [x] Admin/Assistant create/edit: two TL selects (1 required, 2 optional, not the same person)
  - [x] Employee list/profile/payslip show both names; search still works
  - [x] EmployeeCrudTest + LangParity; UI/UX gate AR+EN RTL/LTR 375/768/1280

### T-8.3 — Scope every ops query through membership (by who)
- Depends on: T-8.2
- Unlocks: T-8.4
- [x] Task complete
  - [x] Replace every `where('team_leader_id', $actor->id)` / `$employee->team_leader_id === $actor->id` with `TeamMembershipService`
  - [x] Shifts, rest days, reports, missing-report alerts, hours board, attendance history, photos policy, clock GPS notify
  - [x] Persist actor on writes that already have an assigner (`assigned_by_tl_id`, `created_by`) — do not invent unused columns
  - [x] DualTeamScopeTest: TL-A sees member, TL-B sees same member, TL-C 403; outsider still hidden

### T-8.4 — Approvals / requests: either TL, record decider
- Depends on: T-8.3
- Unlocks: T-8.5
- [x] Task complete
  - [x] Permissions + vacations: both TLs in queue; add `decided_by` (FK users) on accept/reject/force
  - [x] Submit still allowed if employee has ≥1 TL; snapshot is membership not a single id
  - [x] UI: show submitted-for-team + **Decided by** name; no money to TL
  - [x] PermissionApprovalTest + VacationApprovalTest dual-TL cases; UI/UX gate

### T-8.5 — Evaluations: per-TL % and text (no overwrite)
- Depends on: T-8.3
- Unlocks: T-8.6
- [x] Task complete
  - [x] `attendance_daily_scores` (attendance_id, team_leader_id, 4 metrics, unique pair); `scored_by` = that TL. Attendance % stays on `attendance`
  - [x] `evaluations` unique becomes `(user_id, team_leader_id, month, year)`; keep `evaluation_text` + 5 % + total
  - [x] Report review: each TL saves **their** daily %; UI shows the other TL’s scores read-only
  - [x] Monthly eval screen: each TL edits **their** text; % = avg of **their** daily scores + system attendance avg
  - [x] My Evaluation: both TLs’ bars + texts labeled by name
  - [x] Annual report combined = average of existing TL `total_score`s for that month
  - [x] EvaluationServiceTest (legacy 0.97375 still on one-TL fixture) + MonthlyEvaluationTest dual; UI/UX gate

### T-8.6 — Notifications + payroll read-path + leftover `team_leader_id`
- Depends on: T-8.4, T-8.5
- Unlocks: —
- [x] Task complete
  - [x] Notify both TLs (clock/report/leave/geofence/missing report)
  - [x] Payroll generate filter “by TL” = membership, not `users.team_leader_id`
  - [x] Grep: zero remaining employee-scoping via `users.team_leader_id`; permissions/vacations `team_leader_id` either migrated to membership or documented as submit snapshot + `decided_by`
  - [x] NotificationTest + PayrollGenerateTest dual-TL; LangParity

## Phase 9 — Company portal (Service / Product / HR)

### T-9.1 — Module home + empty Service/Production shells
- Depends on: T-1.2
- Unlocks: Service/Product screens (next step, not started)
- [x] Task complete
  - [x] Login → `/home` for every role; navbar tabs Service · Production · HR
  - [x] HR tab → existing role dashboard; HR sidebar only inside HR
  - [x] Service + Production empty shells (fill next)
  - [x] PortalModuleTest + AuthTest; LangParity; UI/UX gate

### T-9.2 — Portal home statistics + charts
- Depends on: T-9.1
- Unlocks: —
- [x] Task complete
  - [x] KPI cards + 14-day attendance chart + roles + hours + module bars
  - [x] Company-wide except TL (team only); no payroll money
  - [x] PortalStatsServiceTest + PortalModuleTest; UI/UX AR+EN

### T-9.3 — Settings tab outside HR
- Depends on: T-9.1
- Unlocks: —
- [x] Task complete
  - [x] Manager navbar + home card: Settings; no HR sidebar on that page
  - [x] Removed from Manager HR sidebar; other roles still 403
  - [x] PortalModuleTest + MenuConfigTest + DashboardTest; UI/UX AR+EN

### T-9.4 — Daily Valuation (Service + Production)
- Depends on: T-9.1
- Unlocks: —
- [x] Task complete
  - [x] Admin assigns any user to Service and/or Production
  - [x] Each assignee fills own daily sheet; formulas from Valuation 07-2026.xlsx
  - [x] View: filler + Admin only; ValuationCalculatorTest + ValuationModuleTest

### T-9.5 — Service/Product shell like HR + Product rename + valuation form layout
- Depends on: T-9.4
- Unlocks: —
- [x] Task complete
  - [x] Service and Product open a dashboard with the same sidebar shell as HR (not a dump of the form)
  - [x] Sidebar **Valuation** → `/service/valuations` and `/product/valuations`
  - [x] Rename Production → Product (`/product`; `/production` redirect); module value `product`
  - [x] Valuation form layout: valid Bootstrap grid, King/Mart side by side, sales_spread labeled
  - [x] PortalModuleTest + ValuationModuleTest + MenuConfigTest + LangParity; UI/UX AR+EN

### T-9.6 — Valuation target/execution Excel layout + percents
- Depends on: T-9.5
- Unlocks: —
- [x] Task complete
  - [x] التارجت والتنفيذ as Excel rows: general / B2B / B2C × target · execution · percentage
  - [x] Formulas from Valuation 07-2026 (H=E/B, KPI=E66/D70); display 37.78% / 52.26% etc.
  - [x] ValuationCalculatorTest + ValuationModuleTest + LangParity

### T-9.7 — Score 0–100, stars, editable general target
- Depends on: T-9.6
- Unlocks: —
- [x] Task complete
  - [x] Department/sales scores are 0–100 (not 0–1); 100=5★ 80=4★ (Excel ROUND)
  - [x] التارجت العام is a typed input (count + amount), not a locked sum
  - [x] ValuationCalculatorTest + ValuationModuleTest + LangParity

### T-9.8 — Valuation show page is the full sheet
- Depends on: T-9.7
- Unlocks: —
- [x] Task complete
  - [x] `/service/valuations/{id}` shows market, King/Mart, sales, departments, targets, providers
  - [x] ValuationModuleTest + LangParity

### T-9.9 — Valuation sheet PDF
- Depends on: T-9.8
- Unlocks: T-9.10
- [x] Task complete
  - [x] Dompdf A4 download of the full daily sheet; filler + Manager; other users 403
  - [x] AR glyph shaping + Tajawal; ValuationModuleTest + LangParity

### T-9.10 — Valuation month stats (Hamad-Gross)
- Depends on: T-9.9
- Unlocks: T-9.11
- [x] Task complete
  - [x] Service/Product home + valuation index: MTD gross, target, gap, B2B/B2C, KPI from daily sheets
  - [x] SQL JSON aggregates; own sheets unless Manager; ValuationStatsServiceTest + ValuationModuleTest + LangParity

### T-9.11 — Compact valuation month strip
- Depends on: T-9.10
- Unlocks: T-9.12
- [x] Task complete
  - [x] User asked to restore dashboard **stat cards** (same tile style as `/home`)
  - [x] One-row table reverted; still no capital/inventory/ads; ValuationModuleTest + LangParity

### T-9.12 — Valuation drivers: employee + orders + over target
- Depends on: T-9.11
- Unlocks: —
- [x] Task complete
  - [x] Dynamic “Add driver” rows (like employee contacts): select employee **or** type name + orders
  - [x] Orders > 25 → show over target X (orders − 25); keep score/notes
  - [x] Form + show + PDF; AR+EN; ValuationCalculatorTest + ValuationModuleTest + LangParity

### T-9.13 — Light / dark theme (localStorage)
- Depends on: T-0.2
- Unlocks: —
- [x] Task complete
  - [x] Bootstrap `data-bs-theme` + `localStorage` key `hr-pro-theme` (no DB)
  - [x] Navbar toggle next to language; login shell unchanged
  - [x] HR-Pro SCSS themed for dark; ThemeToggleTest + LangParity; UI/UX gate

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
              T-7.6 → T-8.1 → T-8.2 → T-8.3 → T-8.4 → T-8.6
                                      ↘ T-8.5 ↗
              T-1.2 → T-9.1 → T-9.2
                       T-9.1 → T-9.3
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

- [x] 2026-08-18 T-8.1 PASS — `employee_team_leaders` pivot (max 2, TEAM_LEADER only); backfill then drop `users.team_leader_id`. `TeamMembershipService` + User `ledBy` / factory mutator. DualTeamLeaderSchemaTest + full suite 437 green.
- [x] 2026-08-18 T-8.2 PASS — Employee form two TL selects (1 required, 2 optional, distinct); list/profile/payslip show both names; search via pivot. EmployeeCrudTest + LangParity.
- [x] 2026-08-18 T-8.3 PASS — Ops scope via `TeamMembershipService::leads()` / `ledBy`: shifts, rest days, reports, missing-report alerts, hours board, attendance history, punch photos; clock GPS + auto clock-out + report submitted/missing notify both TLs. Actor still `assigned_by_tl_id` / `created_by`. DualTeamScopeTest. Approvals T-8.4; per-TL eval T-8.5. Full suite 463 tests / 4562 assertions.
- [x] 2026-08-18 T-8.4 PASS — Either membership TL may accept/reject/force leave + vacation; `decided_by` on both tables. Queue uses `ledBy`, not the first-TL snapshot. Submit requires ≥1 TL. UI: Submitted for team + Decided by (AR+EN); no money to TL. PermissionApprovalTest + VacationApprovalTest dual. TL self-requests still Manager-only (no new TL↔Admin path). Full suite 465 tests / 4618 assertions.
- [x] 2026-08-18 T-8.5 PASS — Per-TL daily scores (`attendance_daily_scores`) + monthly eval unique `(user, TL, month, year)`. Own scores/text editable; other TL read-only labeled by name. Attendance % stays on `attendance`. Combined month = avg of TL totals (annual = avg of those months). Legacy 0.97375 kept. Dual DailyScore/MonthlyEvaluation/MyEvaluation tests. UI/UX AR+EN RTL/LTR 375/768/1280. Full suite 475 tests / 4671 assertions. Next: T-8.6.
- [x] 2026-08-18 T-8.6 PASS — Both membership TLs notified on leave/vacation/report reply (actor skipped); clock geofence/auto clock-out/report submitted/missing already both. Payroll generate-by-TL = `ledBy` + that TL’s own row. `permissions`/`vacations`.`team_leader_id` documented as submit snapshot; queue/notify/scope use membership. NotificationTest + PayrollGenerateTest dual. Phase 8 complete. Full suite 481 tests / 4700 assertions.
- [x] 2026-08-18 UX T-8.2 — Employee form: choosing team leader 1 hides that person from team leader 2 (and the reverse). Server still rejects the same id twice. AR+EN hints. EmployeeCrudTest + LangParity.
- [x] 2026-08-18 UX T-8.2 — Employees list: separate columns for team leader 1 and team leader 2 (empty → none).
- [x] 2026-08-18 UX T-8.2 — Hide TL selects when role is TEAM_LEADER (also Accountant/Assistant); posted ids ignored so a TL cannot be assigned another TL.
- [x] 2026-08-18 T-7.7 PASS — Admin login-as-user: Settings `impersonation_enabled` (default off). Manager Login as from Employees + Return to admin banner. Nested switch blocked; Assistant/TL 403. ImpersonationTest + SettingsTest + LangParity green. Role chain N/A (Manager-only).

<!--
Example:
- 2026-08-02 T-0.1 PASS — migrate/boot OK
-->
- [x] 2026-08-12 T-6.6 PASS — Optional multi punch photos on clock-in **and** clock-out (jpg/jpeg/png/webp ≤50MB, max 5); `attendance_photos` + AttendancePhotoService; authorized view self·TL(team)·Manager (TL↔Admin **yes**); UI on `/clock`, employee sessions, TL/Admin today board + hours day list, report review; AR+EN; AttendancePhotoTest + schema/Clock/LangParity green.
- [x] 2026-08-12 Amend T-6.6 — removed punch-photo **count** limit (keep ≤50MB each); AR+EN hint updated; AttendancePhotoTest accepts 8 files.
- [x] 2026-08-20 T-6.7 PASS — Optional description per punch photo (clock-in/out); schema + viewers; AttendancePhotoTest + LangParity green.
- [x] 2026-08-20 T-7.7 PASS — Online full day (`ONLINE_FULL`) selectable on employee create/edit (up to 3 defaults); clock/hours/payroll. DualDefaultShiftsTest + EmployeeCrudTest + LangParity green.
- [x] 2026-08-20 Amend T-7.7 — ClockTest updated for real ONLINE_FULL default (legacy no longer expands to FT+½); ClockTest + DualDefault + AttendanceService + LangParity green (84).
- [x] 2026-08-12 UX — Admin payroll show: second button **Export full review PDF** (`export-review.pdf`) covers all tabs (pay / edit draft / attendance / evaluation / hours); Admin-only (TL 403); PayrollExportTest green.
- [x] 2026-08-12 T-7.1 PASS — ACCOUNTANT role: self-service + payroll (no اعتماد) + advances CRUD; `team_leader_id` null → Manager decides leave; seed accountant@hrpro.test; TL still money-blocked.
- [x] 2026-08-23 Amend T-7.2 / clock — GPS required for FULL_TIME only; free early/late/after-end clock-in with notify employee+TL+Admin; auto clock-out removed. ClockTest + ClockLocationTest + DualDefault + AttendanceServiceTest + LangParity green.
- [x] 2026-08-23 T-7.8 PASS — DRIVER role: clock-only menu; required punch photos+descriptions in/out; TL membership; Admin payroll includes drivers; Manager+Assistant create. Seed driver@hrpro.test. DriverRoleTest + MenuConfigTest + PortalStats + LangParity green.
- [x] 2026-08-23 T-7.9 PASS — Multi-punch same shift (segments+gaps); TL recommend + Admin final on breaks; credited/raw hours, late/early, OT review; clock + approvals + attendance history UI. AttendanceMultiPunchTest + full suite 542 PASS.
- [x] 2026-08-23 T-7.10 (R5+R6b) PASS — Force-close stale open punch on clock screen/in (no cron); DRIVER mid-day FIELD uploads (GPS+photo+description). DriverRoleTest field cases green.
- [x] 2026-08-23 T-7.10 (R2) PASS — System recommended vs payroll-credited time breakdown component; TL/Admin review form + reset to system; employee clock + hours day list. AttendanceTimeSummaryTest + AttendanceMultiPunchTest green; LangParity green.
- [x] 2026-08-23 T-7.10 (R4) PASS — Full time metrics (raw, credited, late, early, gap min, OT +/-) on clock, employee dashboard, report, TL attendance history, today board, hours day list. AttendanceTimeBreakdownTest green; LangParity green.
- [x] 2026-08-23 T-7.10 (R7) PASS — Gap approval polish: pending/decided/all filters, TL+Admin notes, notification inbox titles/URLs, dual-TL team scope, status badges on clock + approvals. AttendanceGapApprovalTest green; LangParity green.
- [x] 2026-08-23 Amend R7 — Assistant may open `/team-leader/approvals` (gaps only) and finalize gaps after TL recommend; leave/vacation still TL+Manager. Menu + AssistantRoleTest + AttendanceGapApprovalTest green.
- [x] 2026-08-23 T-7.10 (R9) PASS — ShiftWindowValidator blocks FT 09:00→01:00 typos on employee create/edit + TL assign; true overnight 22:00→06:00 OK; repair migration. ShiftWindowValidatorTest + EmployeeCrudTest + ShiftAssignmentTest green.
- [x] 2026-08-23 T-7.10 (R8) PASS — Overtime TL recommend (system-suggested min) → Admin/Assistant approve/reject; `x-attendance-overtime-status` + `x-attendance-overtime-review`; credited cap on breakdown/metrics; approvals queue + inbox notifications. AttendanceOvertimeReviewTest green; LangParity green.
- [x] 2026-08-23 T-7.10 (R10) PASS — Full suite **583** PASS; AttendanceFlagsDisplayTest setTestNow on punch day so R5 stale force-close does not clear open-shift flags; T-7.10 marked complete.
- [x] 2026-08-23 Amend T-7.8 — Driver: GPS required every punch; daily report required same as employee (menu + gate + post clock-out). DriverRoleTest + MenuConfigTest green.
- [x] 2026-08-13 T-7.3b PASS — Payroll Hours: per-day required / present (موجود) / absent (غائب) per shift; month totals summed from exact day lines; PDF aligned.
- [x] 2026-08-18 T-7.4 PASS — Job title AR+EN on `users` (covers employees + TLs + accountants + managers; no duplicate on profiles). Admin create/edit + list column + search; self profile + payslip; blank titles default to role. Existing rows backfilled. EmployeeCrudTest + EmployeeProfileTest + LangParity green.
- [x] 2026-08-18 T-7.5 PASS — ASSISTANT role (مساعد إداري): company-wide employee data entry, shifts, rest days, view-all daily reports (no reply/scores), missing-report alerts, admin dashboard, self-service. No payroll/approvals/evaluations/settings. Seed assistant@hrpro.test. AssistantRoleTest green.
- [x] 2026-08-18 Amend T-7.5 — Assistant may add/edit/see employee salary and rates (user: yes). Payroll generate/review, advances admin, penalties, settings still blocked. TL still never sees money. AssistantRoleTest + RatesPrivacyTest.
- [x] 2026-08-18 T-7.6 PASS — Attendance history `/team-leader/attendance`: employee + date range (≤92d) + present/absent/rest/vacation + photos/location/outside; Manager/Assistant all, TL team; Dashboard shortcut. AttendanceHistoryTest + LangParity green.
- [x] 2026-08-18 Fix T-7.6 — pagination: Bootstrap 5 links, keep filters on page 2, clamp out-of-range page; showing X–Y of Z.
- [x] 2026-08-18 Plan T-8 — Dual team leaders (max 2) + per-TL evaluations with actor on every write. **Not started** until user confirms the lock box in `docs/TASKS.md` Phase 8.
- [x] 2026-08-18 Fix — Employee salary/rates: `type=number` in ar/RTL rejected 5500 (thousands grouping) while 600 worked. Text + `inputmode=decimal` + wider fields; normalize `5,500` / Arabic-Indic digits. EmployeeCrudTest green.
- [x] 2026-08-18 Fix — Dual defaults: list shows each shift + window (not one merged label). Clock keeps both types when TL assigns only one; morning prefers the in-window shift; closed-window copy if the other shift was missed. DualDefaultShiftsTest + ClockTest green.
- [x] 2026-08-19 T-9.2 PASS — `/home` system overview: staff/present/late/open/missing-report cards; 14-day clock-in bars; role doughnut; month hours; HR vs empty Service/Production. TL team-scoped, no payroll money. PortalStatsServiceTest + PortalModuleTest + LangParity green.
- [x] 2026-08-20 Amend T-9.2 — Company role donut lists all five roles (EMP/TL/Assistant/Accountant/Manager); staff cards stay EMP+TL. PortalStatsServiceTest green.
- [x] 2026-08-19 T-9.4 PASS — Daily Valuation on Service + Production: Admin assigns users per module; each person fills own Excel-parity sheet (0–1 scores, B2B/B2C, KPI/cash-flow labels). Filler + Admin view only. ValuationCalculatorTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.5 PASS — Service/Product same sidebar shell as HR; Valuation at `/service/valuations` and `/product/valuations`; Production renamed Product (`/production` → `/product`); valuation form grid (sales_spread + King/Mart). PortalModuleTest + ValuationModuleTest + MenuConfigTest + LangParity green.
- [x] 2026-08-19 T-9.6 PASS — Target/execution block matches Excel rows (general/B2B/B2C × target · execution · %); KPI = amount total / 2500 → 52.26%; bands خسائر/سالب/low. ValuationCalculatorTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.7 PASS — Scores 0–100 with Excel stars (100=5, 80=4); التارجت العام is an input. ValuationCalculatorTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.8 PASS — Valuation show page renders the full sheet (market through providers), not only the target block. ValuationModuleTest green.
- [x] 2026-08-19 T-9.9 PASS — Valuation PDF (`/{module}/valuations/{id}/export.pdf`) full sheet, filler+Manager, 403 others; AR glyphs. ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.10 PASS — Hamad-Gross style MTD cards on Service/Product dashboard + valuation index (gross/target/gap/B2B/B2C/KPI from daily sheets). ValuationStatsServiceTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.11 PASS — Compact one-row valuation KPI strip (no capital/inventory/ads). ValuationModuleTest + LangParity green.
- [x] 2026-08-19 T-9.11 amend — Restored `/home`-style stat cards for month Gross (user: tab/card style). LangParity + ValuationModuleTest green.
- [x] 2026-08-20 T-9.12 PASS — Valuation Drivers: Add driver rows (employee select + orders); >25 shows over target X; form/show/PDF. ValuationCalculatorTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-20 T-9.12 amend — Driver row: select employee **or** type manual name; over target unchanged. ValuationModuleTest + LangParity green.
- [x] 2026-08-20 T-9.12 amend — Per-driver score % (0–100) + notes; section average for stars; form/show/PDF. ValuationCalculatorTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-23 Fix valuation — Manager Service/Product home lists all employees’ month sheets; stat-card RTL money alignment; LangParity + ValuationModuleTest + build.
- [x] 2026-08-19 Fix T-9.9/T-9.10 — Valuation PDF: keep AR mixed Latin/digits/brackets (`(0–100)`, B2B, King); gold ★ stars instead of `****-`; `/service/valuations` list is a card table with badges/actions. DompdfArabicTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-19 UX T-6.5 — Valuation sheets + assign lists use the same `.table-hr` + `<x-table-toolbar>` search/filters as HR lists. TableFilterUiTest + ValuationModuleTest + LangParity green.
- [x] 2026-08-24 Amend T-1.5 — Dropped `online_half_day_rate` / `online_full_day_rate` / `overtime_hourly_rate` from users + employee form. Payroll always uses base÷30 formulas; draft OT multiplier unchanged. PayrollCalculatorTest + RatesPrivacy + Assistant + EmployeeCrud + PayrollDraft + LangParity green.
- [x] 2026-08-24 T-9.13 PASS — Light/dark via Bootstrap `data-bs-theme` + `localStorage` `hr-pro-theme` (no DB). Navbar toggle next to language; head boot script (no flash); login unchanged. HR-Pro SCSS body/sidebar/cards/calendar/tabs themed. ThemeToggleTest + LangParity; `npm run build`; UI/UX AR+EN RTL/LTR.
- [x] 2026-08-24 UX — Employee sees assigned team leader(s) on dashboard + profile (name, email, phone). EmployeeDashboardTest + EmployeeProfileTest + LangParity.
- [x] 2026-08-24 UX — Clock in/out CTA colors vs schedule (green ±5, yellow 15 before–30 after, orange 16+ early / 31+ late; red if no time). Live on clock + dashboard. ClockCtaColorTest + ClockTest + EmployeeDashboardTest.

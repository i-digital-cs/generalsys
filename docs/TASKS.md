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
- [ ] Task complete
  - [ ] Code done
  - [ ] `migrate:fresh --seed` (scratch) PASS
  - [ ] UI/UX: N/A

### T-1.2 — Auth login/logout + language preference
- Depends on: T-1.1
- Unlocks: T-1.3, T-2.3, T-2.7
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 1.3 tests PASS
  - [ ] UI/UX gate (login → dashboard; AR↔EN)

### T-1.3 — Policies + middleware for 3 roles
- Depends on: T-1.2
- Unlocks: T-1.4
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature 403 matrix smoke PASS
  - [ ] UI/UX gate (wrong role blocked)

### T-1.4 — Admin: Employee CRUD (account + profile + contract)
- Depends on: T-1.3
- Unlocks: T-1.5, T-1.7, T-1.8
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 1.4 tests PASS
  - [ ] UI/UX gate (create/edit/list; pagination; validation)

### T-1.5 — Admin: rates + currency per employee
- Depends on: T-1.4
- Unlocks: T-1.6
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 1.5 tests PASS
  - [ ] UI/UX gate (Admin rates; TL never sees money)

### T-1.6 — Employee documents upload/download (authorized)
- Depends on: T-1.5
- Unlocks: T-1.7
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 1.6 tests PASS
  - [ ] UI/UX gate (upload/reject mime/download)

### T-1.7 — Admin company dashboard (basic stats)
- Depends on: T-1.4
- Unlocks: T-2.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature smoke PASS
  - [ ] UI/UX gate (cards; empty state)

### T-1.8 — Employee self profile view (read-only)
- Depends on: T-1.4
- Unlocks: T-2.1
- [ ] Task complete
  - [ ] Code done
  - [ ] Feature self-only PASS
  - [ ] UI/UX gate (own data only)

---

## Phase 2 — Attendance & requests (Gate 2)

### T-2.1 — Migrations: attendance, permissions, vacations, balances, credit logs, shift_assignments
- Depends on: T-1.1
- Unlocks: T-2.2, T-2.7, T-2.10, T-2.12, T-2.14
- [ ] Task complete
  - [ ] Code done
  - [ ] migrate scratch PASS
  - [ ] UI/UX: N/A

### T-2.2 — `AttendanceService` clock-in/out + overnight + day flags
- Depends on: T-2.1
- Unlocks: T-2.3, T-3.5, T-4.3
- [ ] Task complete
  - [ ] Code done
  - [ ] Gate 2.1–2.2 tests PASS
  - [ ] UI/UX: N/A

### T-2.3 — Employee Clock screen (FT/Online; OT only if assigned)
- Depends on: T-2.2, T-1.2 _(OT UX also needs T-2.14)_
- Unlocks: T-2.4, T-2.5, T-2.13
- [ ] Task complete
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

<!--
Example:
- 2026-08-02 T-0.1 PASS — migrate/boot OK
-->

# HR-Pro — Build & Test Gates

**Rule:** finish a step → prove it with **automated tests** + **UI/UX** (if the task has a screen) → only then start the next task.  
Task board with dependencies: **`docs/TASKS.md`**.  
Engineering rules (all AIs): **`AGENTS.md`**.  
Source of truth for behavior: `MASTER_CONTEXT.md` · math: `docs/PAYROLL_CALCULATION.md`.

Every agent (Cursor, Kimi, or other) must follow `AGENTS.md` §0 and this file — not IDE-only rule folders.

---

## Global commands (every step)

| Check | Command | Pass criteria |
|-------|---------|---------------|
| Syntax | `php -l` on every changed `.php` file | exit 0 |
| Style | `vendor/bin/pint --dirty` | no remaining dirty style issues |
| Routes | `php artisan route:list` | no exceptions |
| Config | `php artisan config:clear` | no exceptions |
| Tests | `php artisan test --filter=<StepSuite>` then broader if needed | all green |
| Schema (if migrated) | `php artisan migrate:fresh --seed` on **scratch** DB | completes; seeders ok |

Never run tests against production.

---

## Gate 0 — Project scaffold

**Build:** Laravel 11 app, env, Sanctum, queue=`database`, cache=`file`/`database`, Vite build local-only.

| # | Must verify |
|---|-------------|
| 0.1 | App boots: `php artisan about` |
| 0.2 | `phpunit.xml` uses sqlite `:memory:` or dedicated test DB |
| 0.3 | Smoke feature test: `GET /` or login page returns 200 |
| 0.4 | `npm run build` succeeds locally (assets not built on server) |

**Exit:** Gate 0 tests green → may start Gate 1.

---

## Gate 1 — Foundation (Phase 1)

**Build:** migrations for schema tables used in Phase 1 · auth · 3 roles · employee CRUD · rates · documents.

| # | Must verify |
|---|-------------|
| 1.1 | `migrate:fresh --seed` on scratch DB |
| 1.2 | Unit: factories create MANAGER / TEAM_LEADER / EMPLOYEE |
| 1.3 | Feature: login success + bad credentials |
| 1.4 | Feature: MANAGER can CRUD employees; EMPLOYEE cannot create employees (403) |
| 1.5 | Feature: rates visible to MANAGER + ASSISTANT; **hidden / 403 for TEAM_LEADER** |
| 1.6 | Feature: document upload mime/size rejection |
| 1.7 | Lang keys AR + EN for screens touched |

**Exit:** all Gate 1 tests green → may start Gate 2.

---

## Gate 2 — Attendance core (Phase 2)

**Build:** clock in/out · daily report + TL reply · permissions · vacations + credit · shift assignment check for OT/extra.

| # | Must verify | Status |
|---|-------------|--------|
| 2.1 | Unit: `AttendanceService` planned/actual hours; overnight clock-out next day | ✅ T-2.2 |
| 2.2 | Unit: late_minutes / early_departure_minutes | ✅ T-2.2 |
| 2.3 | Feature: clock-in / clock-out happy path; second open clock-in → 422 | ✅ T-2.3 |
| 2.4 | Feature: EXTRA_SHIFT / OVERTIME without `shift_assignments` → 422 | ✅ T-2.3 |
| 2.5 | Feature: FT → Online selectable at clock-in by employee | ✅ T-2.3 |
| 2.6 | Feature: permission accept → normal penalty path; reject → no force yet | ✅ T-2.8 |
| 2.7 | Feature: **Mark as forced** → `FORCED` + double penalty flag | ✅ T-2.9 (employee self / TL team / Manager) |
| 2.8 | Feature: vacation request shows remaining credit; allow submit with 0 remaining; reject date overlaps; **approve moves pending→used / reject releases pending** (credit visible on Approvals) | ✅ T-2.10 + T-2.11 |
| 2.9 | Feature: TL can add vacation credit; employee sees updated remaining | ❌ Retired — vacation cost = rest-day swap; credit UI removed |
| 2.10 | Feature: daily report + TL response (XSS escaped in Blade) | ✅ T-2.5 + T-2.6 |
| 2.11 | Feature: team-scoped lists — TL cannot see other teams | ✅ T-2.6 reports, T-2.13 hours board, T-2.14 shifts |

**Also covered with clock (2026-08-02):** same `shift_type` twice same day → 422; used types hidden on `/clock`; rotation preferred = TL assignment then default.

**Exit:** all Gate 2 tests green → may start Gate 3.

---

## Gate 3 — Evaluation & shifts (Phase 3)

**Build:** daily 4 TL scores + Interest Return · daily auto attendance % · monthly text · monthly avg of **5** · Friday / day-off flags · OT pre-assign.

| # | Must verify | Status |
|---|-------------|--------|
| 3.1 | Unit: daily `attendance_score = min(100, actual/planned*100)` | ✅ (via T-2.2 clock-out + T-3.1 50% fixture) |
| 3.2 | Unit: `EvaluationService::monthlyFromDaily` averages 5 metrics; `total = sum/5` | ✅ T-3.2 |
| 3.3 | Unit: Excel legacy 4-metric sample still averages correctly where applicable (0.97375 on those four) | ✅ T-3.2 |
| 3.4 | Feature: TL PUT daily scores `{ performance, behavior, productivity, interest_return }` | ✅ T-3.1 |
| 3.5 | Feature: monthly evaluation creates text + computed averages | ✅ T-3.3 |
| 3.6 | Feature: employee can view own evaluation; cannot edit scores | ✅ T-3.4 |
| 3.7 | Feature: TL shift assignment create/cancel; employee cannot self-assign OT | ✅ T-2.14 (also FT/Online rotation) |

**Exit:** all Gate 3 tests green → may start Gate 4.

---

## Gate 4 — Payroll engine (Phase 4)

**Build:** penalty rules · calculator · generator (queued/chunked) · advances · admin approve · employee payslip.

| # | Must verify |
|---|-------------|
| 4.1 | Unit: Excel fixture **net = 2808.00** (`docs/PAYROLL_CALCULATION.md` §9) |
| 4.2 | Unit: excused absence 1× day; unexcused **3×** (from `penalty_rules`) |
| 4.3 | Unit: per-minute penalties; forced = ×2 |
| 4.4 | Unit: Friday / day-off multipliers per MASTER_CONTEXT §1.2 |
| 4.5 | Unit: online rates use per-employee fields (fallback documented default) |
| 4.6 | Feature: MANAGER generate draft + approve |
| 4.7 | Feature: **TEAM_LEADER → every payroll route 403** (API + web) |
| 4.8 | Feature: EMPLOYEE sees only own APPROVED/PAID; DRAFT hidden |
| 4.9 | Feature: UI sidebar has **no** payroll menu for TL |
| 4.10 | Feature: incomplete month coverage warns; drafts still created (strict block only if `require_full_month_coverage`) |
| 4.11 | Job: payroll generation chunks ≤ 100 employees; completes under test fakes |

**Exit:** all Gate 4 tests green → may start Gate 5.

---

## Gate 5 — Reports & polish (Phase 5)

**Build:** annual report · Excel/PDF export · full AR/EN · currency display.

| # | Must verify |
|---|-------------|
| 5.1 | Feature: annual report average evaluation |
| 5.2 | Feature: PDF/Excel export authorized (MANAGER; employee own payslip only) |
| 5.3 | Feature: language toggle AR/EN; RTL/LTR layout |
| 5.4 | Feature: `<x-money>` for EGP and AED |
| 5.5 | Regression: re-run Gate 4 payroll fixture + TL 403 |

**Exit:** Gate 5 green → release candidate checklist in `AGENTS.md` §9.

---

## Step report template (paste after every step)

```text
Gate / Step: <e.g. 2.3 clock-in>
Commands run:
  - php artisan test --filter=AttendanceClockTest
  - vendor/bin/pint --dirty
Result: PASS
Failures: none
Next step allowed: yes
```

If **FAIL**: fix code or tests (contract change only) → re-run → do **not** open the next step.

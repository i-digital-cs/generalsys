# AGENTS.md — HR-Pro Engineering Rules

These rules are **mandatory** for every contributor — human or **any AI agent** (Cursor, Kimi, ChatGPT, etc.).  
**Do not put project rules only in IDE-specific folders** (e.g. `.cursor/rules`). Keep rules here and under `docs/` so every tool reads the same source of truth.

If a rule blocks your task, stop and ask — do not silently violate it.

- System spec: `MASTER_CONTEXT.md` · Schema: `docs/DATABASE_SCHEMA.md` · API: `docs/API_DOCUMENTATION.md` · UI: `docs/UI_FRAMEWORK.md` · Payroll math: `docs/PAYROLL_CALCULATION.md` · Build gates: `docs/BUILD_AND_TEST_GATES.md` · Tasks: `docs/TASKS.md`
- Stack: **PHP 8.2 / Laravel 11 · Blade + Bootstrap 5 (RTL) · MySQL 8 (InnoDB, utf8mb4)**
- Hosting: **Hostinger shared hosting** — this drives half the rules below.

---

## 0. How every agent must work (task → test → UI/UX → next)

1. Work **only** from `docs/TASKS.md`. Start a task only when all **Depends on** tasks are `done`.
2. Set task `in_progress`. Implement that task only (no skipping ahead).
3. Ship **automated tests with the code**. Run them until green. **No next task on red.**
4. If the task builds UI: pass the full **UI/UX gate** in `docs/TASKS.md` (§ Translate · RTL/LTR · Responsive) before `done`.
5. Paste an end-of-step report, update task status + Progress log in `docs/TASKS.md`, then take the next unlocked task.

**End-of-step report (required):**
```text
Task ID: T-x.x
Commands: …
Automated tests: PASS | FAIL
UI/UX (AR+EN, RTL+LTR, 375/768/1280): PASS | FAIL | N/A
Next task allowed: yes/no
```

**Never:** skip tests; skip UI/UX on Blade tasks; mark `done` with unfinished dependencies; put rules only in Cursor/IDE config.

---

## 0b. Role chain — Employee↔TL vs TL↔Admin (ask first)

Many workflows that exist **between Employee and Team Leader** *may* also exist **between Team Leader and Admin (Manager)** — but **do not assume**.

**Before** extending any Employee↔TL feature to TL↔Admin (routes, menus, services, policies, notifications, tests), **ask the user** in chat:

> “Should this also apply between TL and Admin? (yes / no / partial — explain)”

Only implement the TL↔Admin path after an explicit yes (or clear partial instructions). If the user says no, ship Employee↔TL only and note the exception in `MASTER_CONTEXT.md` / the task Progress log.

**Typical candidates to ask about:** leave/late, vacation, daily report reply, force, clock/self-service, evaluations, notifications.

**Hard exception (never ask to weaken):** payroll / money — TL never sees monetary amounts (AGENTS.md §5.1). Admin-only payroll stays Admin-only unless the user explicitly changes that product rule.

Details: `MASTER_CONTEXT.md` §1.7d.

---

## 1. Architecture

1. **Service layer owns all business logic.** Controllers only: validate (Form Request) → authorize (Policy) → call a Service → return view/response. A controller with an `if` that encodes a business rule is a defect.
2. Services live in `app/Services/<Domain>/` (e.g. `app/Services/Payroll/PayrollCalculator.php`). One domain concept per class; constructor dependency injection only — no `new` inside methods, no facades for business deps.
3. **OOP & SOLID**, concretely:
   - SRP: a class has one reason to change (`PayrollCalculator` calculates; `PayrollGenerator` orchestrates; `PayrollExporter` exports).
   - Open/closed: payroll multipliers, penalty rates, expected-hours profiles are **data/config**, not code branches (see `docs/PAYROLL_CALCULATION.md` §8).
   - Liskov/interface: depend on contracts (`ExpectedHoursStrategy`), bind in a service provider.
   - No god classes (> ~300 lines), no methods > ~40 lines, no nesting deeper than 3 levels.
4. **Eloquent models are thin**: casts, relations, accessors, scopes. No calculations in models; computed values (e.g. `age`, `remaining_days`) are accessors or service results, never duplicated stored columns unless the schema doc says so.
5. **Clean code**: meaningful English identifiers for code (DB enums stay uppercase ASCII), no commented-out code, no debug leftovers (`dd`, `dump`, `var_dump`, `console.log`), no TODOs merged without an issue reference.

## 2. Hosting Constraints (Hostinger shared) — NON-NEGOTIABLE

1. **Never** require: Redis, Memcached, Supervisor, Node on server, WebSockets, queues via `sync`-blocking long jobs, or any PHP extension not in Hostinger's shared plan. Queue driver = `database`; cache driver = `file` or `database`.
2. Scheduler = single cron `* * * * * php artisan schedule:run`. No system cron per feature.
3. Assets are built **locally** (`vite build`) and uploaded. Never run npm/composer install on the server; deploy vendor + `public/build`.
4. Heavy work (payroll generation, Excel/PDF export, imports) must be a **queued, chunked job** (≤ 100 employees per chunk), safe under 30s execution and 256MB memory.
5. No package may be added without checking: (a) it is actually needed, (b) its extensions exist on shared hosting, (c) it is maintained. Prefer Laravel built-ins.

## 3. Database Rules

1. **Schema lives only in migrations.** Every table/column must trace to `docs/DATABASE_SCHEMA.md`. Changing schema = update the doc **and** add a migration in the same change.
2. **No unused tables or columns.** Before adding one, prove it is read or written by code. Removing a feature = remove its migration/table/column too (new migration dropping it).
3. Money = `DECIMAL(12,2)`; scores `DECIMAL(5,2)`; never `FLOAT` for money. Enums match the schema doc exactly.
4. Every FK indexed; composite indexes for monthly queries (`user_id + date`/`month+year`) as defined in the schema doc.
5. Multi-tenant-style scoping by `team_leader_id` must happen in query scopes/policies, not by trusting request input.

## 4. Performance Rules

1. **No N+1**: eager load (`with`) everywhere; Laravel Debugbar / `Model::preventLazyLoading()` enabled in dev. A page budget is **≤ 30 queries**.
2. **Paginate every list** (default 25, max 50). No unbounded `all()` / `get()` on user-facing paths.
3. Aggregations (counts, sums for payroll) run **in SQL**, not PHP loops over collections.
4. Cache `penalty_rules`, holidays, and settings (tagged, file driver); invalidate on update. Never cache per-user payroll data.
5. No per-request `DB::statement` raw SQL when the query builder works; raw SQL only for proven hot paths with a comment explaining why.

## 5. Security Rules

1. **Authorization in Policies, always.** `PayrollPolicy`: TEAM_LEADER gets 403 on every payroll route/query — this is the system's #1 privacy rule; employee sees own APPROVED/PAID payslips only. UI must also hide payroll menus for TL (defense in depth).
2. All input validated via **Form Requests**; mass assignment via `$fillable` only; never `$request->all()` into `create()`.
3. Output escaping: Blade `{{ }}` default; `{{!! !!}}` is forbidden on user content (daily reports are user content).
4. Passwords: `Hash` (bcrypt/argon default). Rate-limit login + clock endpoints. CSRF on all web forms; Sanctum for API.
5. File uploads (employee documents): whitelist mime/extension, store outside web root via `Storage`, serve through an authorized controller, random filenames. Max size 5MB.
6. Secrets only in `.env`; never commit it; no credentials in code, tests, or docs.
7. SQL injection/XSS/CSRF/mass-assignment must be considered in every review — Laravel defaults handle them **only if you don't bypass them**.

## 6. Testing Rules — STEP GATES (NON-NEGOTIABLE)

**Build order:** implement one step → test it until green → only then start the next step.  
Full checklists: `docs/BUILD_AND_TEST_GATES.md` · task board: `docs/TASKS.md`.

1. **No forward progress on red.** If any test for the current step fails, stop. Do not begin the next Gate/Phase/feature.
2. **Tests ship with the code** in the same change. No “implement now, test later.”
3. **PHPUnit required for every Service and every new/changed route:**
   - **Unit**: `PayrollCalculator`, `EvaluationService`, `AttendanceService`, penalty/vacation — Excel fixtures in `docs/PAYROLL_CALCULATION.md` §9 (net = **2808.00**; legacy 4-metric avg = **0.97375**). App monthly total uses **5 metrics** (`/5` incl. Interest Return).
   - **Feature**: happy path + validation 422 + role 403 (especially **TEAM_LEADER → payroll 403**).
4. **Per-step minimum run:** targeted `php artisan test --filter=…` → `php -l` on touched files → `vendor/bin/pint --dirty` → `php artisan route:list` must not error. Schema changes also require `migrate:fresh --seed` on a **scratch** DB.
5. Never weaken assertions to force a pass. Update tests only when the contract changed on purpose — then update **all** callers.
6. Tests use in-memory/transactions + factories; **never** against production.
7. **End-of-step report required** (commands + PASS/FAIL) — template in §0 and `docs/BUILD_AND_TEST_GATES.md`.
8. **Task board:** respect **Depends on** / **Unlocks** in `docs/TASKS.md`. After automated tests, every UI task must pass that file’s UI/UX gate. Update status + Progress log when done.

## 7. No Dead Code / No Waste

1. No unused: files, classes, methods, routes, views, lang keys, migrations, tables, columns, config entries, npm/composer packages. If you remove the last usage, remove the thing.
2. No speculative features — implement only what the spec/docs define. Three similar lines beat a premature abstraction.
3. Before finishing a task, run a self-check: `grep` for the symbols you touched — if something you created is referenced nowhere, delete it.
4. Git commits (when asked): small, scoped, conventional messages (`feat(payroll): ...`). **Never** commit/push without explicit user request.

## 8. Localization & UI (NON-NEGOTIABLE for every UI task)

Any task that **builds or changes UI** (Blade views, components, menus, forms, flash) must satisfy all three below before status `done` in `docs/TASKS.md`:

1. **Translate AR + EN:** every visible string via `__('key')`; add matching keys in `lang/ar` **and** `lang/en` in the same change. No hardcoded Arabic/English in Blade or user-facing JS.
2. **RTL + LTR:** verify the screen in Arabic (`dir=rtl`) **and** English (`dir=ltr`). Use Bootstrap RTL/LTR builds per `docs/UI_FRAMEWORK.md`; prefer logical properties (`ms`/`me`, `start`/`end`) over physical `left`/`right`.
3. **Responsive:** verify ~375px, ~768px, and ~1280px — no broken layout, no clipped primary actions, tables usable on small screens (scroll container or stack).

Also: dates via Carbon `translatedFormat()`; money via `<x-money>` — never hand-format currency. Details: `docs/UI_FRAMEWORK.md` §9 · checklist: `docs/TASKS.md` UI/UX gate.

## 9. Definition of Done (every task)

- [ ] Logic in Services; controllers thin; Policies enforced
- [ ] Migration + schema doc updated (if schema touched)
- [ ] Current task in `docs/TASKS.md` has dependencies `done`; this task set `done` after tests + UI/UX
- [ ] Unit + feature tests added/updated and **green**; step report pasted (commands + PASS)
- [ ] UI/UX checklist PASS (or N/A for backend-only): **AR+EN keys**, **RTL+LTR**, **responsive** (375 / 768 / 1280); previous↔next screen flow verified
- [ ] Current Gate in `docs/BUILD_AND_TEST_GATES.md` checklist items for this step checked
- [ ] `php -l` clean; Pint clean; no N+1 (`preventLazyLoading` / Debugbar in dev)
- [ ] No dead code, no unused files/columns introduced
- [ ] Spec docs (`MASTER_CONTEXT.md` / `docs/*`) updated if behavior or conventions changed
- [ ] **Next task not started** until this DoD is complete

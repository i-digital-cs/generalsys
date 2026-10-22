# Engineering constitution — protected invariants (index)

This file is an **index**, not a second copy of project rules. Agents and humans must follow the linked sources; do not invent conflicting policies here.

| Invariant | Canonical source |
|---|---|
| How agents work (tasks → tests → UI/UX → report) | `AGENTS.md` §0 |
| Architecture / services / SOLID | `AGENTS.md` §1 |
| Hostinger shared hosting | `AGENTS.md` §2 · `MASTER_CONTEXT.md` §0.1 |
| **Database migration & production safety** | `AGENTS.md` §3.0 · `docs/DEPLOYMENT.md` |
| Schema DDL | `docs/DATABASE_SCHEMA.md` |
| Security (payroll privacy, policies) | `AGENTS.md` §5 |
| Testing gates | `AGENTS.md` §6 · `docs/BUILD_AND_TEST_GATES.md` |
| Localization / UI | `AGENTS.md` §8 · `docs/UI_FRAMEWORK.md` |
| Attendance 15-min grace (Task 1) | `docs/attendance/ATTENDANCE_BUSINESS_RULES.md` · `MASTER_CONTEXT.md` §1.7 |
| Attendance calculation SSOT | `docs/attendance/SSOT_CALCULATION_ARCHITECTURE.md` · `AGENTS.md` §1.1b |
| Payroll math | `docs/PAYROLL_CALCULATION.md` |
| Task board | `docs/TASKS.md` |

**Database rule (summary):** every schema change is a **new** Laravel migration; data fixes are version-controlled and reproducible; no hardcoded-ID-only production updates; no silent payroll backfills inside `migrate`; deploy with authorization + backup via `docs/DEPLOYMENT.md`.

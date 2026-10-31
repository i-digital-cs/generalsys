# GH-9.1 — Remediation Report

**Phase:** GH-9.1  
**Date:** 2026-10-31  
**Environment:** TEST `/home/d14098/generalsys.idigital-cs.com` · DB `d14098_general`  
**Base HEAD before remediation:** `fd9846a`  
**Attendance RC branch preserved:** `release/attendance-ssot-grace-payroll-rc-20261022`  
**Isolated RC branch (local only):** `release/general-house-gh1-gh8` @ `ad27b06` (+ GH-9.1 docs tip may follow)  
**Constraints honored:** no LIVE deploy; no push; no merge; proposed payment launch flags **not** activated; `C-GH-TAX-MIN` left unresolved.

---

## Summary of work

| Area | Result |
|---|---|
| A1 Mass assignment | **Done** — empty/restricted `$fillable`; services use `forceFill`/`forceCreate` |
| A2 DriverRoleTest | **Done** — expectation updated; auth unchanged; HR post-login still clock |
| A3 Other failures | **Done** — root cause Carbon `Y-m` day-31 overflow (+ flaky month-end shift date); fixed without Attendance business-rule changes |
| A4 Full suite | **1203 passed**, 1 skipped, **0 failed** |
| B Financial launch | **Documented** only — config not activated |
| B2 C-GH-TAX-MIN | **Unresolved** (fail-closed retained) |
| C1 Report N+1 | **Done** — `withSum` + grouped export map |
| C2 Float money paths | **Hardened** key GH money normalizers to `GhMoney` |
| C3 Oversized services | **Deferred** (analysis only; low-risk — no broad rewrite) |
| C4 Hygiene | Payment POST `throttle:30,1` |
| D Release isolation | **Done locally** — cherry-pick onto `origin/main` clean; Attendance SSOT **not** in RC |
| D4 Fresh migrate | **Done** — disposable sqlite; GH migrations sequential batch 1 |

---

## Commits (local, not pushed)

On Attendance RC working branch:

1. `70da53f` — `docs(gh): record GH-9 production readiness audit on TEST`  
2. `3f424d9` — `fix(gh): GH-9.1 remediation for mass-assignment, month parse, and report N+1`  
3. (this docs set — GH-9.1 deliverables)

On `release/general-house-gh1-gh8`: cherry-picked GH-1…GH-8 + above (Attendance `7ed3ed2` **absent**).

---

## Finding closure map

| GH-9 ID | Action | Status |
|---|---|---|
| SEC-H2 | Empty fillable + forceFill in services + tests | Closed |
| SEC-H1 / TEST-1 | DriverRoleTest updated | Closed |
| TEST-2 (3 failures) | YearMonth parse + test date fix | Closed |
| ARCH-2 | Reporting withSum / grouped sums | Closed |
| FIN-E1 | GhMoney in pricing/normalize/estimate paths | Closed (engine remainder floats retained — intentional) |
| SEC-L4 | Throttle payment POSTs | Closed |
| GIT-1 | Isolated RC from `origin/main` | Closed locally (not pushed) |
| C-GH-TAX-MIN | Kept null | Open — owner/accountant |
| FIN-E5 online init | Proposed disable — not activated | Open — owner |
| ARCH-1 god classes | Deferred | Open — post-launch |

---

## LIVE impact

**None.** No push, merge, or LIVE deploy. TEST DB records not mutated for audit workflows. `error_log` left uncommitted.

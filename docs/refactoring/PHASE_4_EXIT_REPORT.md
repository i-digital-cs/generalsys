# Task 2 Phase 4 — Exit report (HR policies, leave, permissions & approval safety)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 4 only.  
**Phase 5:** Not started (requires explicit owner approval).

---

## 1. HR lifecycle matrix (evidence-based)

### Permission (`LEAVE_PERMISSION` / `LATE_PERMISSION` / `ABSENCE_PERMISSION`)

| Aspect | Rule (canonical owner) |
|---|---|
| Create | Self via `PermissionRequestService::submit` (Employee needs ≥1 TL; TL/Manager may omit snapshot TL) |
| View (list) | Owner: `listForUser`. Approver queue: `listForActor` (TL = team employees only; Manager = all) |
| Approve / reject | TL who leads owner **or** Manager (`actorMayDecide`) — PENDING only |
| Force | Self, leading TL, or Manager (`actorMayForce`) — REJECTED → FORCED only |
| States | `PENDING` → `ACCEPTED` \| `REJECTED` → `FORCED` |
| Cancel / edit after submit | **Not supported** (no cancel/edit API) |
| Attendance impact | Absence accept/force → `AbsenceAttendanceApplier` writes `EXCUSED_ABSENCE` (skips rest/holiday/vacation; blocks on worked days) |
| Payroll impact | Rejected leave/late → `penalty_amount`; forced → double multiplier. Absence excused days feed aggregators. **Does not mutate APPROVED/PAID payroll** |
| Balance | N/A (time/absence permissions; not vacation days) |
| Notifications | `permissionSubmitted` / `permissionDecided` / `permissionForced` |
| Background | None beyond in-request DB transaction |

### Vacation (`ANNUAL` / `SICK` / `EMERGENCY`)

| Aspect | Rule (canonical owner) |
|---|---|
| Create | Self via `VacationRequestService::submit` |
| ANNUAL/SICK | Multi-day range; balance **reserved** on submit (`VacationBalanceService::reserveForVacation`) |
| EMERGENCY | Single day + rest-day swap (balance unused) |
| View | Owner list / actor queue (same team/Manager split as permissions) |
| Approve / reject | TL who leads owner **or** Manager — PENDING only |
| Force | Self / leading TL / Manager — REJECTED → FORCED; rest day **not** consumed on force |
| States | `PENDING` → `APPROVED` \| `REJECTED` → `FORCED` |
| Cancel / edit | **Not supported** |
| Attendance | Rest day deleted on EMERGENCY approve; vacation dates used by absence applier skip list |
| Payroll | Day-type facts via attendance/aggregators; **no direct settled payroll write** |
| Balance | Approve = confirm pending→used; reject = release pending; force = charge used (after release) |
| Notifications | `vacationSubmitted` / `vacationDecided` / `vacationForced` |

Permission and vacation rules are **not** identical (statuses ACCEPTED vs APPROVED; balance only on vacation; absence attendance only on permissions).

---

## 2. Canonical policy ownership

| Rule | Authoritative implementation | Consumers |
|---|---|---|
| Decide authority | `PermissionRequestService::actorMayDecide` / `VacationRequestService::actorMayDecide` | Services + **new** Policies (delegate) + controllers via `$this->authorize` |
| Force authority | `actorMayForce` (same services) | Same |
| Pending / rejected transition | **`HrRequestWriteGuard`** (lock + re-check) | Approve/reject/force in both request services |
| Overlap (absence) | `PermissionRequestService::assertNoOverlappingAbsence` | Submit only |
| Overlap (vacation) | `VacationRequestService::hasOverlap` | Submit only |
| Vacation balance math | `VacationBalanceService` (+ `lockedBalanceFor` under transaction) | Submit / approve / reject / force / credit |
| Absence → attendance | `AbsenceAttendanceApplier` | Permission approve + force |
| Penalties | `PermissionPenaltyCalculator` | Reject + force |
| Attendance grace / history freeze | Task 1.6 owners (unchanged) | Clock / payroll aggregators |
| Settled payroll | `PayrollWriteGuard` (Phase 3) | Unchanged; HR paths do not call draft refresh on settled rows |

No merge of leave vs vacation policies — different business meanings preserved.

---

## 3. Authorization matrix (server-side)

| Actor | Submit own | Decide team leave/vacation | Decide any | Force own | Force team | Force any | Approvals inbox |
|---|---|---|---|---|---|---|---|
| EMPLOYEE | yes | no | no | yes (REJECTED) | no | no | no (403 route) |
| TEAM_LEADER | yes (own → Manager queue) | yes (members only) | no | yes (own + team) | yes | no | yes (scoped) |
| MANAGER | yes | yes | yes | yes | yes | yes | yes |
| Other employee / other TL | — | **403** | **403** | **403** | **403** | — | scoped empty / 403 on action |

HTTP: Policies via `$this->authorize('decide'|'force', $model)`. Service still throws domain exceptions for wrong-team if called directly (`notTeamMember` / `cannotForce`).

**Not broadened.** Attendance clock/history remain role middleware + service scopes; `AttendancePhotoPolicy` / `ShiftAssignmentPolicy` / valuation policies unchanged. A full `AttendancePolicy` for every attendance route was **not** invented (matrix already covered by existing scopes; adding a broad policy without owner product matrix risked accidental 403).

---

## 4. Before / after architecture

| Area | Before | After |
|---|---|---|
| HTTP auth on approve/reject/force | `abort_unless(service->actorMay…)` | **`$this->authorize`** + `PermissionPolicy` / `VacationPolicy` |
| Pending / rejected checks | Pre-transaction status assert | **`HrRequestWriteGuard`** `lockForUpdate` + re-check inside transaction |
| Vacation balance writes | Unlocked row updates | **`lockedBalanceFor`** inside caller's transaction |
| Duplicate side-effects | Possible under concurrent double-approve | Blocked by row lock + status re-check |
| Policies registered | Missing for Permission/Vacation | `AppServiceProvider` Gate::policy |

Public service method contracts preserved (`approve` / `reject` / `force` / `actorMayDecide` / `actorMayForce`).

---

## 5. Tests added

| File | Coverage |
|---|---|
| `tests/Feature/Hr/HrRequestLifecycleTest.php` | Policy Gate matrix; repeated approve (service + HTTP); vacation balance no double-confirm; reject→force charge once; unauthorized decide/force 403 |

Existing packs remain green: `PermissionApprovalTest`, `PermissionForceTest`, `VacationApprovalTest`, `VacationForceTest`, `AbsencePermissionTest`, `VacationRequestTest`.

---

## 6. Confirmed defects & corrections

| Finding | Action |
|---|---|
| No Laravel Policy for Permission/Vacation (F-06) | Added policies mirroring service allow/deny |
| Controllers used ad-hoc `abort_unless` | Wired `$this->authorize` |
| Concurrent / repeated approve could re-enter side-effects | `HrRequestWriteGuard` lock + status re-check |
| Balance confirm/release without row lock | `VacationBalanceService::lockedBalanceFor` |
| Cancellation after approval | **Not a product feature** — documented; not invented |
| APPROVED→PAID payroll via HR | Out of scope (Phase 3); HR does not invent reconciliation |

---

## 7. Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

No historical attendance rewrite; no settled payroll mutation.

---

## 8. Regression results

| Suite | Result |
|---|---|
| HR targeted (`HrRequestLifecycle` + leave/vacation packs) | PASS |
| Protected attendance + payroll (+ immutability) | **102 PASS** |
| Full `php artisan test` | **940 PASS / 0 FAIL** (baseline 932 + 8 new) |
| Pint `--dirty` | PASS |
| `php -l` on new files | PASS |
| `route:list` approvals | OK |

---

## 9. Remaining risks

| Risk | Notes |
|---|---|
| No cancel/edit after submit | Product gap — do not invent without owner decision |
| Full `AttendancePolicy` for all clock/history routes | Deferred; existing middleware + service scopes hold; owner matrix needed before broad Policy |
| Gap recommend/decide still service-enforced without Gate Policy class | Pre-existing; out of Phase 4 leave/vacation scope |
| SQLite `lockForUpdate` weaker than MySQL | Characterization tests still enforce sequential immutability; production MySQL gets real locks |

---

## 10. Files changed

| Path | Change |
|---|---|
| `app/Services/Hr/HrRequestWriteGuard.php` | **New** |
| `app/Policies/PermissionPolicy.php` | **New** |
| `app/Policies/VacationPolicy.php` | **New** |
| `app/Services/Permissions/PermissionRequestService.php` | WriteGuard on transitions |
| `app/Services/Vacations/VacationRequestService.php` | WriteGuard on transitions |
| `app/Services/Vacations/VacationBalanceService.php` | Locked balance mutations |
| `app/Http/Controllers/TeamLeader/ApprovalController.php` | authorize decide/force |
| `app/Http/Controllers/Employee/RequestController.php` | authorize force |
| `app/Providers/AppServiceProvider.php` | Register policies |
| `tests/Feature/Hr/HrRequestLifecycleTest.php` | **New** |
| `docs/refactoring/PHASE_4_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` / `REFACTORING_MASTER_PLAN.md` | Progress |

---

## 11. Verdict

Phase 4 **PASS**. Ready for Phase 5 only after **explicit owner approval**.

# Final release candidate — attendance forensic RC1

## Verdict

**CONDITIONAL → APPROVED FOR LIVE DEPLOYMENT** after owner confirms:

1. Historical fabricated rows remain as-is for go-live (UI badges only), and  
2. Admin will correct Reda’s shift profile separately.

Code + tests on TEST are green. LIVE not deployed. No migrations. No historical writes.

---

## Acceptance criteria evidence

| Criterion | Status | Evidence |
|---|---|---|
| Gate 0 TEST ≠ LIVE | PASS | LIVE `u449614343_system` @ 151.106.117.60; TEST `d14098_general` |
| Root causes fixed | PASS | `AttendanceService` stale-close + unused preference |
| Forensic tests | PASS | 14/14 |
| Full attendance regression | PASS | **180/180** (825 assertions) |
| Migrations | PASS | None required |
| Historical dry-run | PASS | 18 classified; 0 modified — `HISTORICAL_DRY_RUN_CLASSIFICATION.md` |
| Shift config audit | PASS | Proposals only — `SHIFT_CONFIGURATION_AUDIT.md` |
| Secrets excluded | PASS | No `.env` / `backup.sql` / `error_log` in commit |
| LIVE deploy | NOT DONE | Plan ready — `LIVE_DEPLOYMENT_PLAN.md` |

---

## Git

- Branch: `release/attendance-forensic-rc1`  
- Commit hash: *(filled after commit)*  
- Manifest: see commit `git show --stat`

---

## Docs index

| Doc | Purpose |
|---|---|
| `HISTORICAL_DRY_RUN_CLASSIFICATION.md` | 18-record classification |
| `SHIFT_CONFIGURATION_AUDIT.md` | Config proposals |
| `REGRESSION_TEST_RESULTS.md` | 180 PASS |
| `LIVE_DEPLOYMENT_PLAN.md` | Promote / rollback / smoke |
| `IMPLEMENTATION_REPORT.md` | Code change summary |
| `TEST_RELEASE_REPORT.md` | Earlier gate summary |

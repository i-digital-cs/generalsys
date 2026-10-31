# GH-9 — Final Checklist & Verdict

**Phase:** GH-9  
**Date:** 2026-10-31  
**STATUS:** **CONDITIONAL**

**Environment verified:** TEST = `/home/d14098/generalsys.idigital-cs.com` + DB `d14098_general` (owner-identified). `APP_ENV=production` ignored as LIVE proof. LIVE not accessed.

---

## Deliverables

| Document | Path |
|---|---|
| Production readiness audit | `docs/general-house/GH9_PRODUCTION_READINESS_AUDIT.md` |
| Security findings | `docs/general-house/GH9_SECURITY_FINDINGS.md` |
| Financial integrity | `docs/general-house/GH9_FINANCIAL_INTEGRITY.md` |
| Deployment plan (proposed) | `docs/general-house/GH9_DEPLOYMENT_PLAN.md` |
| Release isolation plan | `docs/general-house/GH9_RELEASE_ISOLATION_PLAN.md` |
| Open decisions | `docs/general-house/GH9_OPEN_DECISIONS.md` |
| This checklist | `docs/general-house/GH9_FINAL_CHECKLIST.md` |

---

## Automated tests (exact counts)

| Run | Passed | Failed | Skipped | Assertions |
|---|---|---|---|---|
| Full `php artisan test` | **1195** | **4** | **1** | 12524 |
| `--filter=GeneralHouse` | **93** | **0** | **0** | 353 |
| `--filter='Attendance\|Payroll\|Gross\|Valuation'` | **641** | **0** | **1** | 3411 |

### Failures (full suite)

1. `Tests\Feature\DriverRoleTest::test_driver_menu_has_clock_and_report_and_hr_lands_on_clock` — **GH-related** (menu expects no `gh.driver.dashboard`)  
2. `Tests\Feature\Admin\DashboardTest` — manager month select `value="2026-09"`  
3. `Tests\Feature\Employee\EmployeeDashboardTest` — missing `ONLINE_HALF` string  
4. `Tests\Feature\TeamLeader\ApprovalsFilterTest` — missing `reason-rejected-bob`

---

## Severity register

### Critical

_None._

### High

| ID | Finding | Blocks LIVE? |
|---|---|---|
| GIT-1 | GH on Attendance RC branch; tip ships mixed ancestry | **Yes** until isolation |
| C-GH-TAX-MIN | Surcharge tax class null; ISSUE blocked when surcharge > 0 | **Yes** if surcharge used |
| SEC-H1 / TEST-1 | DriverRoleTest vs GH DRIVER menu | Conditional (suite policy) |
| SEC-H2 | `status`/`payment_status` in `$fillable` | No (current paths safe) / harden recommended |

### Medium

| ID | Finding | Blocks LIVE? |
|---|---|---|
| DB-1 | TEST migrations batch/id anomaly GH-1–3 | No if LIVE forward-only |
| ARCH-1 | God-class LOC > ~300 | No |
| ARCH-2 | Report N+1 payment sums | No |
| INFRA-1 | LIVE cron not verified | **Yes** at deploy |
| FIN-E2 | Mixed tax-class discount edges | Conditional |
| FIN-E3 | No void/credit/refund | No (disabled OK) |
| FIN-E5 | Online init without gateway | Conditional |
| FIN-E6 | Denormalized payment_status drift risk | No |
| FIN-E8 | Report period TZ boundary confirm | Conditional |
| TEST-2 | 3 non-GH full-suite failures | No for GH-only |
| SEC-M1–M4 | Coarse ACL, dual driver enforce, PENDING noise, CSV residual | No |

### Low / Info

ARCH-3/4, FIN-E1/E4/E7, SEC-L*, SEC-I*, ENV-1, TZ-1, GIT-2, FIN-OK positives.

---

## LIVE deployment blockers

1. Execute **release isolation** (Strategy A preferred).  
2. Resolve **C-GH-TAX-MIN** or ensure no surcharge at launch.  
3. Owner sign-off on **payment feature matrix** (cash-primary recommended).  
4. Verify **LIVE cron** `schedule:run` at deploy.  
5. Accept **no refunds/credit notes** or delay launch.  
6. If hard gate: fix **DriverRoleTest** (+ triage 3 non-GH failures).  
7. Scratch DB **forward migrate** dry-run of all 8 GH migrations.  
8. Pre-LIVE **backup** verified.

---

## Recommended fix order (after owner approval — not started)

1. Owner decisions (`GH9_OPEN_DECISIONS.md`)  
2. Release isolation branch + conflict verification  
3. Update DriverRoleTest / menu contract  
4. Optional: strip financial status from `$fillable`  
5. Optional: disable online initiation for launch  
6. Set tax class **only** after accountant approval  
7. Report N+1 / period TZ hardening (can be post-launch if volume low)  
8. Triage non-GH dashboard/approvals failures  
9. Authorized LIVE deploy per `GH9_DEPLOYMENT_PLAN.md`

---

## Go / No-Go dimensions

| Dimension | Verdict |
|---|---|
| ENVIRONMENT VERIFIED | TEST confirmed; LIVE not touched |
| GIT RELEASE STATUS | CONDITIONAL — isolation required |
| ARCHITECTURE | CONDITIONAL — SSOT good; oversized services |
| FINANCIAL INTEGRITY | CONDITIONAL — SSOT+locks good; tax decision open |
| SECURITY | CONDITIONAL — Manager-only OK; harden fillable |
| DATABASE | PASS structure; CONDITIONAL history on TEST |
| MIGRATIONS | PASS forward files; forward-only policy |
| TIMEZONE | PASS GH UTC + Dubai; app Cairo unchanged |
| INFRASTRUCTURE | PASS on TEST cron; LIVE cron TBD |
| PERFORMANCE | CONDITIONAL — report N+1 |
| AUTOMATED TESTS | GH PASS 93; full 1195/4/1 |
| REGRESSION RESULTS | Attendance/Payroll/Gross/Valuation filter green (641+1 skip); 3 unrelated full failures |
| BUSINESS DECISIONS | Open — see open decisions doc |
| LIVE IMPACT | None yet (audit only) |
| **FINAL VERDICT** | **CONDITIONAL** |

---

## Sign-off template (owner)

```text
GH-9 audit reviewed: yes/no
Release isolation strategy: A / B / C
C-GH-TAX-MIN choice: …
Payment launch matrix approved: yes/no
Remediation authorized to begin: yes/no
LIVE deploy authorized: yes/no (must be separate explicit order)
```

**Stop condition:** Audit complete. No remediation. No deploy.

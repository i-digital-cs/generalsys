# Task 2 Phase 6 — Exit report (cross-cutting architecture, security & reliability)

**Date:** 2026-10-13  
**Authorization:** Owner approved Phase 6 only.  
**Phase 7:** Not started (requires explicit owner approval).

Baseline carried forward: Phase 5 full suite **947 PASS**.

---

## 1. Findings & severity (evidence-ranked)

| ID | Finding | Severity | Disposition |
|---|---|---|---|
| **RISK-GROSS-001** | Repair clamp: ON_HAND short → leave ON_HAND; WITH_COURIER still += | High (integrity) | **INVESTIGATED / ACCEPTED** — no formula change |
| Gap recommend/decide | Service ValidationException; no Policy / `$this->authorize` | Medium | **FOLLOW-UP** — 403 vs 422 is product/UX; not invented |
| Attendance `app(ShiftConfig)` | Model service-locator | Low–Med | **ACCEPTED DEBT** (F-05) |
| `applyRefuseReturn` unused | Dead API | Low | **ACCEPTED** — `@deprecated`; do not wire |
| `SalesInvoiceController` 585 LOC | Fat HTTP surface | Low–Med | **FOLLOW-UP** — already service-backed |
| Scheduled auto-clock | Stub only; not in `routes/console.php` | Low | **ACCEPTED** — keep soft-fail stub; stale force-close remains |
| Payroll jobs lacking `$tries`/`$timeout` | Relied on worker CLI only | Low | **FIXED** — aligned to Hostinger |
| `Employee` vs `Employees` services | Namespace split | Low | **FIXED** — dashboard moved to `Employees` |
| Valuation Blade calculator calls | Presentation in view | Low | **IMPROVED** — display strings from stats service |
| Stale GROSS_MODULE §6 refuse | Doc said immediate ON_HAND restock | Med (doc drift) | **FIXED** — matches code/tests |
| N+1 / `preventLazyLoading` | Not enabled; unmeasured | Med | **Phase 7** — measure first |
| Nested Stock/invoice transactions | Pre-existing | Low | **ACCEPTED** |

**No Phase 6 blockers.** No authorization broadening.

---

## 2. RISK-GROSS-001 investigation (authorized; not “fixed”)

**Write path:** `StockService::repairMoveOnHandToWithCourier` ← sole caller `RepairGrossReturnedWithCourierCommand`.

| Condition | ON_HAND | WITH_COURIER | Movement |
|---|---|---|---|
| `on_hand >= pieces` | −pieces | +pieces | none |
| `on_hand < pieces` (incl. 0) | **unchanged** | **+pieces** | none |
| no balance row | — | — | returns `null` |

**Impact:** can create WITH_COURIER without a matching ON_HAND debit; ledger has no repair movement.  
**Triggers:** running `gross:repair-returned-with-courier` when warehouse on-hand is insufficient for pending client returns.  
**Correction proposal (needs separate owner approval):** refuse inflate when on_hand short; or partial debit; plus optional `ADJUSTMENT_*` / dedicated repair movement — **not implemented in Phase 6**.

**Tests:** `GrossStockIntegrityTest` (insufficient on_hand, zero on_hand, null balance).

---

## 3. Cross-cutting improvements shipped

| Slice | Change |
|---|---|
| F-07 namespace | `EmployeeDashboardService` → `App\Services\Employees\`; removed empty `Services/Employee` |
| Queue reliability | `GeneratePayrollJob` / `ExportPayrollExcelJob`: `$tries = 1`, `$timeout = 50` |
| F-15 clarity | `AutoClockOutCommand` docs distinguish stub vs stale force-close |
| Dead API | `applyRefuseReturn` marked `@deprecated` |
| F-08 light | `ValuationStatsService` preformats percent displays + `gap_tone` |
| Doc SSOT | `docs/GROSS_MODULE.md` §6 refuse aligned with code |

**Not changed:** financial/stock math, repair clamp, gap HTTP status, AttendancePolicy invent, schema, job schedules.

---

## 4. Authorization results

| Area | Result |
|---|---|
| Payroll / Gross / HR policies (Phases 3–5) | Unchanged; regression green |
| Gap recommend/decide | Still service-scoped ValidationException; **no new Policy** (avoid silent 403 flip) |
| Export payroll TL 403 | Still enforced (`PayrollExportTest`) |

---

## 5. Transaction / retry results

| Path | Status |
|---|---|
| Payroll job retry | Explicit `$tries=1` matches schedule worker |
| Payroll job timeout | Explicit `$timeout=50` |
| Gross/HR/payroll write guards | Preserved (no formula edits) |
| Duplicate confirm/handToCourier | Still idempotent |

---

## 6. Logging & auditability

- App `Log::` in jobs: coverage warnings with `user_id`/month/year only — no secrets.  
- No new audit events.  
- Stock movements unchanged for repair (still no movement — characterized).

---

## 7. Queue & scheduler

| Item | Notes |
|---|---|
| Schedule | Unchanged: `queue:work` every minute; `reports:warn-missing` 21:00 / 08:00 |
| `attendance:auto-clock-out` | **Not scheduled**; stub retained |
| Jobs | Hostinger-aligned tries/timeout documented in code |

---

## 8. Performance

**NOT MEASURED** (Phase 7 trigger). No index/schema changes. No speculative eager-load sweeps. Valuation display prep moved server-side (micro; no query change).

---

## 9. Tests added

| File | Cases |
|---|---|
| `GrossStockIntegrityTest` | + RISK-GROSS-001 zero on_hand; null balance |
| `PayrollJobHostingerConfigTest` | tries/timeout for both jobs |
| `ValuationStatsServiceTest` | display/gap_tone keys |

Full suite: **951 PASS / 0 FAIL** (947 + 4).

---

## 10. Database governance

```text
DATABASE CHANGES: NO
SCHEMA MIGRATIONS CREATED: NOT APPLICABLE
DATA MIGRATION OR BACKFILL: NOT APPLICABLE
PRODUCTION DEPLOYMENT PROCEDURE: VERIFIED
HARDCODED ID SAFETY: NOT APPLICABLE
DATABASE MIGRATION POLICY: PASS
```

---

## 11. Remaining technical debt

| Item | Class |
|---|---|
| RISK-GROSS-001 repair clamp | Accepted until owner correction approval |
| AttendanceGapPolicy (403) | Follow-up |
| Attendance model `app(ShiftConfig)` | Accepted debt |
| SalesInvoiceController thin | Follow-up |
| Nested DB transactions (Gross) | Accepted |
| N+1 / preventLazyLoading | Phase 7 |
| Deep valuation `_drivers` ViewModel | Follow-up |

---

## 12. Files changed

| Path | Change |
|---|---|
| `app/Services/Employees/EmployeeDashboardService.php` | Moved namespace |
| `app/Http/Controllers/Employee/DashboardController.php` | Import update |
| `app/Jobs/GeneratePayrollJob.php` | tries/timeout |
| `app/Jobs/ExportPayrollExcelJob.php` | tries/timeout |
| `app/Console/Commands/AutoClockOutCommand.php` | Docs |
| `app/Services/Gross/StockService.php` | deprecate applyRefuseReturn |
| `app/Services/Valuation/ValuationStatsService.php` | Display fields |
| `resources/views/valuation/sheets/_month-stats.blade.php` | Use display fields |
| `docs/GROSS_MODULE.md` | §6 refuse corrected |
| `tests/Feature/Gross/GrossStockIntegrityTest.php` | RISK cases |
| `tests/Unit/Jobs/PayrollJobHostingerConfigTest.php` | **New** |
| `tests/Unit/Valuation/ValuationStatsServiceTest.php` | Display asserts |
| `docs/refactoring/PHASE_6_EXIT_REPORT.md` | This report |
| `docs/TASKS.md` / `REFACTORING_MASTER_PLAN.md` | Progress |

---

## 13. Regression results

| Suite | Result |
|---|---|
| Targeted Phase 6 packs | PASS |
| Protected attendance/payroll/HR + Gross integrity | **119 PASS** |
| Full `php artisan test` | **951 PASS / 0 FAIL** |
| Pint `--dirty` | PASS |

---

## 14. Verdict

Phase 6 **PASS WITH CONDITIONS** (RISK-GROSS-001 investigated and explicitly **not** remediated).  
Ready for Phase 7 only after **explicit owner approval**.

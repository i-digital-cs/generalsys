# Payroll grace policy — financial integrity audit

**Environment:** TEST only (`/home/d14098/generalsys.idigital-cs.com`, DB `d14098_general`)  
**Date:** 2026-10-22  
**LIVE / approved payroll rewrite / auto-backfill:** **NOT performed**

---

## 1. Root cause of the “Use suggested” issue

Draft payroll UI exposed `suggested_late_minutes_total` from `PayrollAggregator::punchLateMinutesTotal()`, which summed **`AttendanceService::rawLateMinutes`** (pre-grace punch delta).

The help text and “Use suggested” control therefore offered **raw** lateness (e.g. 10) even when policy-counted lateness was **0**. Clicking the button (client JS writing `data-apply-suggested-late`) could save that raw value into `payroll_records.late_minutes_total`, feeding `PayrollCalculator` late penalties incorrectly.

`late_minutes_total` on generate/refresh already used `exactLateMinutes`. Only the **suggestion path** was wrong.

---

## 2. Exact source of the old suggested values

| Step | Old behaviour |
|---|---|
| SSOT raw | `AttendanceService::rawLateMinutes` |
| Aggregator | `PayrollAggregator::punchLateMinutesTotal` → `suggested_late_minutes_total` |
| Controller | `PayrollController@show` → `$suggestedLateMinutes` |
| Blade | `field_help.late_minutes_total` + `data-apply-suggested-late="{{ suggested }}"` |
| JS | Set `#late_minutes_total` input from the data attribute (no server recompute) |
| Persist | Admin Save → `UpdatePayrollDraftRequest` → `PayrollDraftService::updateCounts` |
| Money | `PayrollCalculator` minute penalty on saved `late_minutes_total` |

Early departure had **no** payroll suggestion field; only late minutes hit `TYPE_LATE_ARRIVAL` penalties.

---

## 3. Corrected end-to-end data flow

```text
Attendance punches
  → exactLateMinutes / exactEarlyMinutes (grace + effective date + override)
  → PayrollAggregator::aggregate
       late_minutes_total              = sum exact late
       suggested_late_minutes_total    = same exact late
       suggested_early_departure_minutes_total = sum exact early
       raw_late_minutes_total          = punch raw (diagnostic only)
  → Draft show: suggestion text uses counted late (+ counted early in help)
  → “Use suggested” → POST admin.payroll.apply-suggested-late
       → PayrollDraftService::applySuggestedLateMinutes
       → server recomputes aggregate; sets late_minutes_total; recalculates money
       → ignores any client-forged late_minutes_total in the request body
  → Manual Save Counts still allows authorized HR override of late_minutes_total
  → APPROVED / PAID: apply / update forbidden (PayrollPolicy / WriteGuard)
```

Stored attendance columns are **not** used as authority when exact values are available (Oct+). Pre-Oct exact* still returns stored (historical protection).

---

## 4. Historical discrepancy inventory (TEST)

### Cohort 1046–1055 (Joy Wilderman, uid 1044)

| Fact | Value |
|---|---|
| Pattern | Daily `09:20`–`17:00`, `late_minutes=0`, `PRESENT`, **0 segments** |
| SSOT | raw late **20**, exact late **20** |
| Created | 2026-10-22 15:01 (factory-style insert; not clock pipeline) |
| Oct payroll | **None** |

**Cause:** Synthetic / factory rows written with punches but counted columns left at defaults — never `AttendanceTimeCalculator::recalculate`.

### Broader October closed drift (stored ≠ exact)

| Metric | Count |
|---:|---:|
| Closed Oct rows with late and/or early drift | **40** |
| No segments (synthetic 1046–1055) | **10** |
| With segments (real punches) | **30** |

Typical real pattern: `late_minutes` stored **0** + `PRESENT` while exact late is **17–1390** (overnight / window mismatch at original write, or close before grace write-path). Some early rows: stored early **1–5** while exact early **0** (grace on read).

Examples (not exhaustive): DOAA 485/496, Rawan 509/524/538, Mohamed Reda 542/562/573/584/593 (stored 0, exact ~1380), KAREEM 514/543/555/574.

**Payroll association:** **0 October payroll records** on TEST. September APPROVED drafts for some users exist but are immutable and out of Oct scope. Draft/pending Oct money **not impacted** today because no Oct payslips exist.

**Current suggestions:** read **exact*** (after this fix), not stored columns — so draft generate/refresh/apply-suggested for Oct would use counted 20 / 33 / etc. even when stored is 0.

**New writes:** Oct+ clock-out → `recalculate` persists grace-counted late/early/status. Verified by `GracePolicyEndToEndTest` + financial suite.

### Safe remediation proposal (owner approval required)

1. **Do not** auto-backfill in migrate.  
2. Optional Artisan command (idempotent, dry-run default): for `date >= 2026-10-01` closed rows where stored late/early ≠ exact*, call `AttendanceTimeCalculator::recalculate` (or write counted fields only). Log before/after.  
3. Exclude rows with `adjusted_late_minutes` set unless explicitly included.  
4. Delete or quarantine synthetic uid 1044 factory rows (1046–1055) if confirmed non-production.  
5. Re-run only after backup; never touch APPROVED/PAID payroll.  
6. After backfill, regenerate **draft** Oct payroll only if/when created.

---

## 5. Draft versus approved payroll impact

| State | Impact of this fix |
|---|---|
| Oct payroll on TEST | None exist |
| Future Oct drafts | Suggestions + apply + refresh use counted late |
| Manual draft late override | Preserved on Save Counts (`syncAttendanceCounts=false`) |
| APPROVED / PAID | Unchanged; apply-suggested → 403; refresh → 403 |

---

## 6. Regression tests and results

**New:** `tests/Unit/Payroll/PayrollGraceFinancialIntegrityTest.php`

| Case | Expected suggestion | Result |
|---|---|---|
| 14 / 15 late | 0 | PASS |
| 16 / 17 late | 16 / 17 | PASS |
| 14 / 15 early | 0 | PASS |
| 16 / 17 early | 16 / 17 | PASS |
| Late 10 + early 16 | late 0, early 16 | PASS |
| Overnight 10 late | late 0 | PASS |
| Multi-segment first-in 16 | late 16 | PASS |
| Stale stored late 0 / exact 20 | suggest 20 | PASS |
| Manual HR 99 preserved | 99 | PASS |
| Apply suggested (raw 10 day) | saves **0** | PASS |
| HTTP forged `late_minutes_total=10` on apply | saves **16** | PASS |
| APPROVED apply | 403 | PASS |
| Pre-Oct stored 10 | suggest 10 | PASS |
| Draft UI help shows 0 not raw 10 | PASS | PASS |

**Inventory filter** (prior suite + financial + draft):

**311 passed (1451 assertions)**

---

## 7. Files changed

| File | Change |
|---|---|
| `app/Services/Payroll/PayrollAggregator.php` | suggested late = exact; add suggested early + raw diagnostic |
| `app/Services/Payroll/PayrollDraftService.php` | `applySuggestedLateMinutes()` server SSOT apply |
| `app/Http/Controllers/Admin/PayrollController.php` | `applySuggestedLate`; pass early suggestion to view |
| `routes/web.php` | `admin.payroll.apply-suggested-late` |
| `resources/views/admin/payroll/show.blade.php` | server form apply (no nested form / no JS data attribute) |
| `lang/en/payroll.php` / `lang/ar/payroll.php` | help + flash copy |
| `tests/Unit/Payroll/PayrollGraceFinancialIntegrityTest.php` | **new** |
| `tests/Unit/Attendance/GracePolicyEndToEndTest.php` | suggestion = exact assertions |
| `tests/Feature/Admin/PayrollDraftTest.php` | suggestion semantics |
| `docs/attendance/PAYROLL_GRACE_FINANCIAL_INTEGRITY_AUDIT.md` | this file |

No schema migration required.

---

## 8. Remaining financial risks

1. **Stored-column drift** on ~30 real Oct rows — UI/history using exact* is correct; stored status/labels may still say PRESENT until owned backfill.  
2. **`hours_deduction`** remains **physical** planned−credited (not grace deficit Rule C). Within-grace early/late can still create hours shortfall money. Product decision if that should change.  
3. **No separate payroll column** for early-departure minute penalty (only `TYPE_LATE_ARRIVAL`). Counted early is exposed as suggestion/help for transparency; it does not auto-create a new deduction line.  
4. **Manual draft late** can still diverge from SSOT by design (authorized override). Apply-suggested and Refresh restore authority.  
5. LIVE not validated in this pass.

---

## 9. Final verdict

### **CONDITIONAL**

| Gate | Status |
|---|---|
| Suggested late = counted SSOT | PASS (fixed) |
| Suggested early = counted SSOT (aggregate) | PASS |
| Client bypass on “Use suggested” | PASS (server recompute) |
| Manual HR override preserved | PASS |
| Approved immutability | PASS |
| Financial regression matrix | PASS |
| Oct draft payroll money on TEST | N/A (no Oct payslips) |
| Historical stored drift backfill | OPEN (proposal only) |
| hours_deduction vs Rule C | OPEN (documented intentional) |
| LIVE | **BLOCKED** |

**TEST readiness:** Payroll suggestion/apply path is financially aligned with attendance grace SSOT. Clear stored-column drift via owned backfill and decide hours-deduction policy before unconditional LIVE APPROVED.

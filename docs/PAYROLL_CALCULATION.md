# HR-Pro — Payroll Calculation Engine (Traced from Excel)

Source workbooks (the current manual process this system replaces):

- `docs/HR-Data.xlsx` — sheet `RADWA`: daily attendance + daily TL evaluation (one sheet per employee per month)
- `docs/Salaries.xlsx` — sheet `مني`: monthly payroll + annual report (one sheet per employee)
- `docs/Employee-inputes.xlsx` — employee profile fields (feeds `employee_profiles`, not payroll math)

Every formula below is traced cell-by-cell, then mapped to the Laravel implementation in §7.

---

## 1. Shift Schedule Config (HR-Data.xlsx, rows 2–4)

| Cell | Value / Formula | Result | Meaning |
|------|-----------------|--------|---------|
| B4 / C4 | 10:00 / 06:00 | — | Full-time clock-in / clock-out |
| U4 | 12:00 ("modify TIME") | — | Overnight offset hack |
| **D4** | `=+U4+C4-B4` | **08:00** | Full-time planned hours: 12:00 + 06:00 − 10:00 = 8h |
| E4 / F4 | 06:00 / 10:00 | — | Online half-day in/out |
| G4 | `=F4-E4` (implicit) | **04:00** | Online half-day planned hours |
| H4 / I4 | 10:00 / 10:00 | — | Online full-day in/out (next day) |
| **J4** | `=I4+U12-H4` | **12:00** | Online full-day planned hours = 12h |

**Rule extracted:** planned hours = FULL_TIME 8h · ONLINE_HALF 4h · ONLINE_FULL 12h — matches MASTER_CONTEXT §5. The "modify TIME" 12:00 is an Excel-only trick for overnight shifts; the app stores real datetimes instead (see §3).

## 2. Daily Row Logic (HR-Data.xlsx, rows 12–42, one row per day)

Columns per day row:

| Col | Content |
|-----|---------|
| B | Day type: `Full time` · `Online` · `يوم راحة` · `إجازة` · `إجازة رسمية` · `غياب بإذن` · `غياب بدون إذن` |
| D | Date |
| E/F | Full-time clock in/out |
| **G** | Full-time worked time (formula below) |
| H/I | Online clock in/out |
| **J** | Online worked time |
| K | Daily report (Arabic text) |
| L | TL daily evaluation text |
| M/N/O | Daily scores: performance / behavior / productivity (0–1 in sheet = 0–100%) |
| P | Daily attendance score |
| R | `=+V12-E12` → FT worked (V = `=+F12+U12` = clock-out + 12h offset) |
| S | `=+I12-H12` → online worked (same-day) |
| T | `=(I12+U12)-H12` → online worked (overnight) |
| U | 12:00 offset · V | clock-out + offset |

**Worked-time formula (G12, same pattern all rows):**
```
=IF(E12>0,                       // clock-in exists?
   IF(F12>0,                     // clock-out exists?
      IF(B12=$A$5, R12,          // "Full time" → V-E
      IF(B12=$A$8, "Done",       // "Online"     → handled in J column
      IF(B12=<day-type>, <echo>, // rest/vacation/absence → echo the label
      "Error"))),
   "Error"),
 B12)                            // no clock-in → keep day-type label
```

**Extracted business rules:**
- Worked time only counts when both clock-in **and** clock-out exist; otherwise the row keeps its day-type label (rest/vacation/absence) or flags `Error` (missing punch → manual review).
- Online worked time: same-day → `I−H`; overnight → `(I+12h)−H`.
- Rows after the employee stopped working (RADWA sheet, Aug 10→31) have empty B and no punches → contribute nothing; monthly counts come only from column B (§4).

## 3. Overnight Shifts — Design Decision

Excel stores clock-out as `time + 12:00` ("modify TIME", columns U/V). **The app must NOT copy this hack.** `attendance.clock_in` / `clock_out` are real `DATETIME`s; a 10:00→06:00 overnight shift is `2026-08-02 10:00` → `2026-08-03 06:00`. Worked minutes = `clock_out - clock_in`. The attendance `date` is always the clock-in date.

## 4. Monthly Attendance Summary (HR-Data.xlsx, rows 5–10)

| Cell | Formula | Meaning |
|------|---------|---------|
| B5 | `=COUNTIF(B12:B42,"Full time")` | Full-time days |
| G5 | `=COUNTIF(B12:B42,"يوم راحة")` | Rest days (→ 2 in sample) |
| G6 | `=COUNTIF(...,"إجازة")` | Vacation days |
| G7 | `=COUNTIF(...,"إجازة رسمية")` | Official holidays |
| G8 | `=COUNTIF(...,"غياب بإذن")` | Excused absence days |
| G9 | `=COUNTIF(...,"غياب بدون إذن")` | Unexcused absence days |
| B8 | `=COUNTIF(B12:B42,"Online")` | Online days (→ 7 in sample) |
| D5 | `=B5*D4` | FT **basic** hours = days × 8h |
| D6 | `=SUM(G12:G42)` | FT **actual** hours (sums G column; day-type labels contribute 0) |
| **C7** | `=IF(D6>=D5,"لا يوجد خصم ساعات",D5-D6)` | **FT hour deduction** = max(0, basic − actual) |
| D8 | `=IF(K2="Customer Care",R9,IF(K2="werk",S7,"Error"))` | Online basic hours — **department-dependent** (below) |
| D9 | `=SUM(J12:J42)` | Online actual hours |
| **C10** | `=IF(D9>=D8,"لا يوجد خصم ساعات",D8-D9)` | **Online hour deduction** |
| G10 | `=IF(COUNT(D12:D42)=G5+G6+G7+G8+G9+B5+B8,"سليم","خطأ")` | **Validation**: every day of month must be classified |

**Online basic-hours profiles (K2 switch — keep as config, not code):**
- `Customer Care`: `R9 = R7+R8` = (FT days × 4h) + (Online days × 12h) — mixed-shift expectation
- `werk`: `S7 = COUNT(I12:I42)*D4` = online days worked × 8h

**Rules extracted:**
1. Per-month, per-shift-type: `basic_hours = days × planned_hours`; `hours_deduction = max(0, basic − actual)`.
2. Expected-hours profile varies by department → store as a per-employee/department setting, not hardcoded.
3. Day-count validation (Excel G10): by default Admin may generate drafts with a warning when days are incomplete; set `PAYROLL_REQUIRE_FULL_MONTH_COVERAGE=true` to block like Excel «خطأ».

## 5. Monthly Evaluation (HR-Data.xlsx, I6:J10)

| Cell | Formula | Meaning |
|------|---------|---------|
| J6 | `=IF(M10>0,SUM(M12:M42)/M10,0%)` | Performance = avg of daily scores |
| J7 | `=IF(N10>0,SUM(N12:N42)/N10,0%)` | Behavior = avg |
| J8 | `=IF(O10>0,SUM(O12:O42)/O10,0%)` | Productivity = avg |
| J9 | `=IF(P10>0,SUM(P12:P42)/P10,0)` | Attendance = avg (auto) |
| **J10** | `=SUM(J6:J9)/4` | **Total = simple average of 4** (Excel original) |

**Product extension (locked):** add **Interest Return** (عائد الاهتمام) as a 5th daily % score (TL), averaged monthly like the others. App total:

```
total_score = (performance + behavior + productivity + interest_return + attendance) / 5
```

Excel sheets have no Interest Return column — fixtures still validate the original four averages; `total_score` tests use **÷5** once Interest Return is present. Daily scores live on `attendance`; monthly `evaluations` stores the five averages + `total_score` + monthly `evaluation_text`.

## 6. Salary Sheet (Salaries.xlsx, sheet `مني` — worked example)

### 6.1 Header block (rows 4–9) — mirrors HR-Data summary
FT 26 days · rest 4 · official holiday 1 · excused absence 1 · unexcused absence 1 · hour deduction **1:27** (1h27m). HR notes (C11): 1-hour permission on 7/6, **27 minutes late across the month**, worked on official holiday 18/6.

### 6.2 Core payroll grid (rows 38–42) — every formula

| Cell | Formula | Value | Rule |
|------|---------|-------|------|
| B39 | *(input)* | 5000 | Base salary |
| **D39** | `=B39/30*C39` | 3333.33 | **FT pay = base/30 × FT days** (divisor is fixed 30) |
| **E39** | `=B39/30*H6` | 166.67 | **Official-holiday work bonus = daily rate × days** (1×) |
| **G39** | `=B39/30*H7` | 166.67 | **Excused absence deduction = daily rate × days** (1×) |
| **H39** | `=+B39/30*H8` | 166.67 | **Unexcused absence deduction = daily rate × days** — ⚠️ 1× in sheet, spec says 3× (see §8) |
| I39 | `الراتب / عدد ساعات الشهر × ساعات التأخير` | manual | **Late/permission hours deduction = (base / month hours) × missing hours** |
| **J39** | `=SUM(D39:F39)-SUM(G39:I39)` | 3166.67 | Row net = earnings − deductions |
| **D40** | `=B39/30/2*C40` | 833.33 | **Online half-day pay = (base/30/2) × days** (50% of daily rate) |
| I40 | `الراتب / ساعات الشهر / 2 × ساعات التأخير` | note | Same hours deduction at half rate |
| J40 | `=SUM(D40:F40)-SUM(G40:I40)` | 833.33 | Row net |
| **D41** | `=B39/30*C41` | 0 | **Online full-day pay = base/30 × days** |
| J41 | `=SUM(D41:F41)-SUM(G41:I41)` | 0 | Row net |
| **J42** | `=SUM(J39:J41)` | **4000.00** | **Gross = FT + online½ + online-full rows** |

### 6.3 Manual lines & final net (rows 43–50)

| Row | Label | Formula |
|-----|-------|---------|
| J43–J45 | عمولة القسم / عمولة مبيعات / عمولة ارباح | manual amounts (department / sales / profit commissions) |
| J46–J49 | سلفة ×4 | manual advance deductions (100 + 67 + 1000 + 25 = 1192) |
| **J50** | صافي المرتب والعمولات والارباح | `=SUM(J42:L45)-SUM(J46:L49)` → **2808.00** |

**Final formula (as practiced):**
```
NET = (FT_row + OnlineHalf_row + OnlineFull_row + commissions + bonuses)
      − (advances + other manual deductions)
where each row = (pay components) − (absence/hour deductions of that row)
```

### 6.4 Annual report (rows 18–33)

Per employee per year: contract start/end/type · per-month: vacation balance, penalties (الجزاءات), bonuses (المكافئات), deductions (الخصومات), promotion (الترقية), monthly evaluation. **L33 `=AVERAGE(L21:L32)`** = year evaluation average (0.974 in sample). Powers `reports/annual` API + admin screen.

## 7. Laravel Implementation Map

All logic lives in services (never controllers) — see `AGENTS.md`:

| Excel formula | PHP implementation | Inputs |
|---------------|--------------------|--------|
| D4/G4/J4 planned hours | `ShiftConfig::plannedHours(shiftType, $assignment?)` → assignment `expected_hours` when types match, else 8/4/12 | `shift_type`, optional `shift_assignments` |
| R/S/T worked time | `AttendanceService::computeWorkedMinutes()` — `clock_out − clock_in` (real datetimes) | `attendance` row |
| G/J row dispatcher | `AttendanceService::resolveDayStatus()` — punches? day-type? missing-punch flag | row + day type |
| B5…B8 COUNTIFs | `PayrollAggregator::dayCounts(user, month)` — one grouped query | `attendance.status` |
| C7/C10 hour deduction | `PayrollAggregator::hoursDeduction()` — `max(0, days×planned − Σactual)` | counts + hours |
| D8 department switch | `ExpectedHoursStrategy` per employee profile config | employee setting |
| G10 validation | `PayrollGenerator::coverageGap()` — warn by default; throw only when `payroll.require_full_month_coverage=true` | calendar |
| J6–J9 + Interest Return avgs | `EvaluationService::monthlyFromDaily()` — `AVG` of daily score columns | `attendance` scores |
| Total | `EvaluationService` — `array_sum($scores)/5` | 5 scores (Excel J10 was /4) |
| D39/D41 `base/30×days` | `PayrollCalculator::dayRatePay()` — `base / divisor × days` (divisor from `penalty_rules`/settings, default 30) | counts, base |
| D40 `base/30/2×days` | default online-half rate = `base/divisor/2`, **overridden by `users.online_half_day_rate`** (spec §1.6) | rate config |
| E39 holiday bonus | `dayRate × official_holiday_work_days × 1.0` | counts |
| G39/H39 absence | `dayRate × days × multiplier` — multiplier from `penalty_rules` (excused 1×, unexcused **3×** per spec §8) | counts, rules |
| I39 hours deduction | `base / (divisor × 8) × hours_deduction` (hourly), or per-minute penalty per spec §1.1 — mode from settings | hours, rules |
| J43–J49 manual lines | `payroll_adjustments` rows (commissions / bonuses / advances) | admin input |
| J42 gross / J50 net | `PayrollCalculator::calculate()` — `gross = Σ earnings`, `net = gross − Σ deductions` (commissions/bonuses in earnings) | all above |
| Friday / day-off / OT multipliers (spec §1.2, not in these sheets) | `PayrollCalculator::multiplierPay()` — FT Friday 1×, online Friday 0.5×, day-off FT 2×, day-off online 1.5×, OT 1.5× | flags on `attendance`, `users.overtime_hourly_rate` |
| L33 year average | `ReportService::annualEvaluation()` — `AVG(total_score)` | `evaluations` |

## 8. ⚠️ Discrepancies: Excel Practice vs MASTER_CONTEXT (resolve before Phase 4)

| # | Topic | Excel (current practice) | MASTER_CONTEXT | Resolution in engine |
|---|-------|--------------------------|----------------|----------------------|
| 1 | Unexcused absence | **1×** daily rate (H39) | **3×** daily rate | Multiplier stored in `penalty_rules` — default **3×** per spec §1.1; editable by admin |
| 2 | Online half-day rate | `base/30/2` (fixed 50%) | Dynamic per-employee rate (§1.6) | Use `users.online_half_day_rate`; fallback default = `base/30/2` |
| 3 | Late deduction | Proportional hours: `base/monthHours × late hours` | Per-minute penalty rate (§1.1) | Both supported; mode + rate in `penalty_rules` per currency |
| 4 | Month divisor | Fixed **30** | "Monthly working days" | Fixed 30 (Excel is real practice); configurable setting |
| 5 | Official-holiday work | 1× daily rate | Not specified | Keep 1× (sheet practice) |
| 6 | Hour deduction cap | Only if actual < basic | — | Same: `max(0, basic − actual)` |

**Decision rule:** where the sheet and the spec conflict, the multiplier/rate is **data** (in `penalty_rules` / user rates / settings), never hardcoded — so either policy can be applied without code changes.

## 9. Worked Example Verification (from sheet `مني`)

```
Base 5000 EGP · FT 26d (paid 20 in row) · online½ 10d · holiday work 1 · excused 1 · unexcused 1
FT row:    5000/30×20 = 3333.33  + 166.67 (holiday) − 166.67 − 166.67 = 3166.67
Online½:   5000/30/2×10 = 833.33
Gross:     4000.00
Advances:  100 + 67 + 1000 + 25 = 1192.00
NET:       4000.00 − 1192.00 = 2808.00 EGP   ✅ matches J50
```

> This example (and the RADWA daily-score averages) must become **unit-test fixtures** for `PayrollCalculator` and `EvaluationService` — same inputs must produce 2808.00 and 0.97375 respectively.

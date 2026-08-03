
# HR & Payroll Management System — Final Technical Specification v2.0
## System Name: HR-Pro (حـر)

---

## 0. TECH STACK & HOSTING — FINAL

| Layer | Technology | Notes |
|-------|-----------|-------|
| Backend | **PHP 8.2 + Laravel 11** | Monolith, no separate SPA |
| Frontend | **Blade + Bootstrap 5** | Server-rendered, RTL-first (Arabic), vanilla JS only where needed |
| Database | **MySQL 8 (InnoDB, utf8mb4)** | MariaDB 10.6+ compatible |
| Auth | **Laravel Sanctum** (session for web, token for API) | RBAC: MANAGER / TEAM_LEADER / EMPLOYEE |
| Excel Import/Export | **maatwebsite/excel** | Payroll parity with existing sheets |
| PDF Export | **barryvdh/laravel-dompdf** | Payslips, reports |
| Queue/Cache | **database driver** | No Redis on shared hosting |
| Scheduler | **Laravel scheduler via cron** | Single cron entry on Hostinger |

### 0.1 Hosting Constraint — Hostinger Shared Hosting (HARD RULE)
- **No** root access, **no** Redis/Memcached, **no** Supervisor, **no** Node runtime on server, **no** WebSockets.
- Deployment: build assets locally (`vite build`), upload compiled assets; `public/` is the web root (or symlink to `public_html`).
- Queues run via `database` driver; scheduler triggered by one cron job: `* * * * * php artisan schedule:run`.
- PHP memory_limit and execution time are limited → heavy jobs (payroll generation, Excel export) must be **chunked** and run in queue.
- No packages that require extensions not available on Hostinger shared plans (verify `php -m` before adding any package).
- Every feature must be designed for **low resource usage**: eager loading, pagination everywhere, DB indexes, no N+1.

### 0.2 Reference Documents (Source of Truth)
| Document | Purpose |
|----------|---------|
| `docs/DATABASE_SCHEMA.md` | Full MySQL schema (DDL) |
| `docs/API_DOCUMENTATION.md` | REST API reference |
| `docs/UI_FRAMEWORK.md` | Blade + Bootstrap 5 UI conventions |
| `docs/PAYROLL_CALCULATION.md` | Payroll engine traced from Excel sheets |
| `docs/BUILD_AND_TEST_GATES.md` | Per-step build order — test green before next step |
| `docs/TASKS.md` | All tasks with dependencies + mandatory UI/UX checks |
| `AGENTS.md` | Engineering + task/test/UI rules for **all** AI tools |
| `docs/Employee-inputes.xlsx` | Employee profile fields (HR source) |
| `docs/HR-Data.xlsx` | Attendance & evaluation workbook (source) |
| `docs/Salaries.xlsx` | Payroll calculation workbook (source) |

---

## 1. CLARIFICATIONS APPLIED (User Input)

### 1.1 Penalty Rules — FINAL
| Violation | Arabic | Penalty Rate | Unit | Forced = Double |
|-----------|--------|-------------|------|-----------------|
| Late Arrival | تأخير | [Admin sets per currency] | Per Minute | ✅ Yes |
| Leave Permission | إذن انصراف | [Admin sets per currency] | Per Minute | ✅ Yes |
| Late Permission | إذن تأخير | [Admin sets per currency] | Per Minute | ✅ Yes |
| Excused Absence | غياب بعذر | 1× Daily Salary | Per Day | N/A |
| Unexcused Absence | غياب بدون عذر | 3× Daily Salary | Per Day | N/A |

**Forced Rejection Rule**: If TL rejects and employee forces it → penalty × 2

### 1.2 Overtime & Special Work Multipliers

#### Employee Works Two Shifts: Fulltime + Online

| Scenario | Arabic | Fulltime Rate | Online Rate |
|----------|--------|--------------|-------------|
| **Work on Friday** | شغل يوم الجمعة | 1× day (يوم بيوم) | 0.5× day (نصف يوم) |
| **Change FT→Online (non-Friday)** | تغيير من فول تايم لأونلاين | Hour rate × 0.5 | Hour rate × 0.5 |
| **Work on Dynamic Day Off — Full Day** | شغل في يوم الراحة — يوم كامل | 2× multiplier | — |
| **Work on Dynamic Day Off — Online** | شغل في يوم الراحة — أونلاين | — | 1.5× multiplier |

> ⚠️ **Note**: These multipliers apply to the employee's specific dynamic rate (set by Admin per employee).

### 1.3 Currencies
- **EGP** (Egyptian Pound) 🇪🇬
- **AED** (UAE Dirham) 🇦🇪
- **NO third currency**

### 1.4 Vacation Accrual — DYNAMIC
- **NOT** fixed monthly
- **Team Leader** adds vacation credit per employee
- Can be added **weekly** or as needed
- System tracks: `accrued` - `used` - `pending` = `remaining`

### 1.5 Evaluation Scoring
| Metric | Arabic | Cadence | Scale | Input By |
|--------|--------|---------|-------|----------|
| Performance | مستوى الأداء | **Daily** | 0-100% | Team Leader |
| Behavior | السلوك | **Daily** | 0-100% | Team Leader |
| Productivity | مستوى الإنتاج | **Daily** | 0-100% | Team Leader |
| Interest Return | عائد الاهتمام | **Daily** | 0-100% | Team Leader |
| Attendance | الحضور | **Daily (auto)** | 0-100% | **System** |
| **Total** | **التقييم الكلي** | **Monthly avg** | **Average of 5** | **System** |
| Evaluation text | تقييم نصي | **Monthly** | text | Team Leader |

- TL enters **four daily scores** (Performance, Behavior, Productivity, Interest Return) on the attendance day.
- Attendance % is **auto per day** from clock in/out vs planned hours.
- At month end, each metric = **average of that month's daily values**; `total_score` = average of the **five** monthly averages.
- Evaluation **text** is filled **once per month** (not daily).

### 1.6 Online Pay Rates — DYNAMIC PER EMPLOYEE
- **Admin** sets custom rate for each employee:
  - `online_half_day_rate` (per day or per hour)
  - `online_full_day_rate` (per day or per hour)
- **NOT** automatic 50% or 100% of base salary
- Stored per user, per currency

### 1.7 Shift rotation & overtime (locked 2026-08-02)
- Shifts are **rotational**, not fixed every day.
- **Team Leader** (or Manager) pre-assigns via UI `/team-leader/shifts` → `shift_assignments` (FT / Online ½ / Online full / EXTRA / OT).
- **Clock-in rules** (`AttendanceService` + `/clock`):
  1. If TL assigned a SCHEDULED shift for today → that type is the **preferred** option (banner on clock).
  2. If nothing is assigned → employee uses **`shift_type_default`** or **chooses** FT / Online ½ / Online full.
  3. Employee **may still switch** among FT / Online ½ / Online full even when a base shift was assigned.
  4. **EXTRA_SHIFT / OVERTIME** — cannot self-select; SCHEDULED assignment required.
  5. **One attendance row per `(user, date, shift_type)`** — after clock-out, that type is unavailable the same day; pick a different type for another session.
  6. One open punch at a time; overnight `clock_out` uses real next-day datetime.
- TL assigns: shift type, date, expected start/end/hours, multiplier (default 1.0 base / 1.5 premium), optional notes. Cancel sets `CANCELLED` (only while `SCHEDULED`). Duplicate SCHEDULED same user+date+shift_type rejected.
- Planned hours: matching assignment’s `expected_hours`, else profile defaults 8 / 4 / 12 (`ShiftConfig`).

### 1.8 Payroll Visibility — CRITICAL
| Role | Can View Payroll? |
|------|-------------------|
| Admin / Manager | ✅ All employees |
| Team Leader | ❌ NO — cannot see any payroll data |
| Employee | ✅ Own payroll only |

> Team Leader uses HR data (attendance, evaluations, permissions) to help Admin create salaries, but NEVER sees monetary values.

---

## 2. UPDATED DATABASE SCHEMA

### 2.1 Users Table (Extended)
```sql
users
├── id (PK)
├── name_ar / name_en
├── email
├── password_hash
├── role (enum: MANAGER, TEAM_LEADER, EMPLOYEE)
├── currency (EGP, AED)
├── language_preference (ar, en)
├── team_leader_id (FK → users, nullable)
├── base_salary (decimal)
├── online_half_day_rate (decimal)        -- ADMIN sets per employee
├── online_full_day_rate (decimal)        -- ADMIN sets per employee
├── overtime_hourly_rate (decimal)        -- ADMIN sets per employee
├── monthly_vacation_accrual (decimal)    -- TL can update dynamically
├── shift_type_default (FULL_TIME, ONLINE_HALF, ONLINE_FULL)
├── work_start_time (time, nullable)      -- مواعيد العمل: حضور
├── work_end_time (time, nullable)        -- مواعيد العمل: انصراف
├── status (ACTIVE, INACTIVE)
├── created_at / updated_at
```

### 2.2 Attendance Table (Extended)
```sql
attendance
├── id (PK)
├── user_id (FK)
├── date
├── shift_type (enum: FULL_TIME, ONLINE_HALF, ONLINE_FULL, EXTRA_SHIFT, OVERTIME)
├── is_friday_work (boolean)              -- Auto-detected
├── is_day_off_work (boolean)             -- Compared to rest_days table
├── assigned_by_tl_id (FK, nullable)    -- For EXTRA_SHIFT / OVERTIME
├── clock_in (timestamp)
├── clock_out (timestamp)
├── planned_hours (decimal)
├── actual_hours (decimal)
├── status (PRESENT, LATE, EARLY_LEAVE, REST_DAY, OFFICIAL_HOLIDAY, EXCUSED_ABSENCE, UNEXCUSED_ABSENCE)
├── late_minutes (int)
├── early_departure_minutes (int)
├── daily_report (text)
├── team_leader_response (text)
├── performance_score / behavior_score / productivity_score / interest_return_score  -- TL daily %
├── attendance_score (decimal)            -- Auto daily %
├── created_at / updated_at
```

### 2.3 Permissions Table
```sql
permissions
├── id (PK)
├── user_id (FK)
├── type (LEAVE_PERMISSION, LATE_PERMISSION)
├── request_date
├── from_time / to_time
├── reason (text)
├── status (PENDING, ACCEPTED, REJECTED, FORCED)
├── team_leader_id (FK)
├── penalty_amount (decimal)              -- Auto-calculated if rejected/forced
├── is_double_penalty (boolean)           -- TRUE if FORCED
├── created_at / updated_at
```

### 2.4 Vacations Table
```sql
vacations
├── id (PK)
├── user_id (FK)
├── type (ANNUAL, SICK, EMERGENCY)
├── start_date / end_date
├── days_requested (decimal)
├── status (PENDING, APPROVED, REJECTED)
├── team_leader_id (FK)
├── created_at / updated_at
```

### 2.5 Vacation Balance Table (Dynamic)
```sql
vacation_balances
├── id (PK)
├── user_id (FK)
├── year
├── accrued_days (decimal)                -- TL adds dynamically
├── used_days (decimal)
├── pending_days (decimal)
├── remaining_days (decimal)              -- Auto: accrued - used - pending
├── last_updated_by (FK → users)          -- TL who last updated
├── updated_at
```

### 2.6 Monthly Evaluations Table
```sql
evaluations
├── id (PK)
├── user_id (FK)
├── team_leader_id (FK)
├── month / year
├── performance_score (decimal 0-100)     -- avg of daily TL scores
├── behavior_score (decimal 0-100)        -- avg of daily TL scores
├── productivity_score (decimal 0-100)    -- avg of daily TL scores
├── interest_return_score (decimal 0-100) -- avg of daily TL scores
├── attendance_score (decimal 0-100)      -- avg of daily auto scores
├── total_score (decimal 0-100)           -- Average of 5
├── evaluation_text (text)                -- Monthly text only
├── created_at / updated_at
```

### 2.7 Payroll Records Table (Admin + Employee Only)
```sql
payroll_records
├── id (PK)
├── user_id (FK)
├── month / year
├── currency (EGP, AED)
├── base_salary
├── full_time_days (int)
├── online_half_days (int)
├── online_full_days (int)
├── extra_shift_hours (decimal)
├── overtime_hours (decimal)
├── friday_work_days (int)
├── day_off_work_days (int)
├── overtime_pay
├── friday_bonus
├── day_off_bonus
├── commissions
├── bonuses
├── late_penalty
├── leave_permission_penalty
├── late_permission_penalty
├── excused_absence_penalty
├── unexcused_absence_penalty
├── forced_penalty_surcharge
├── advance_deductions
├── gross_pay
├── total_deductions
├── net_pay
├── status (DRAFT, APPROVED, PAID)
├── created_by (FK → users)               -- Admin
├── created_at / updated_at
```

### 2.8 Penalty Rules Table (Admin Config)
```sql
penalty_rules
├── id (PK)
├── name
├── type (LATE_ARRIVAL, LEAVE_PERMISSION, LATE_PERMISSION, EXCUSED_ABSENCE, UNEXCUSED_ABSENCE)
├── currency (EGP, AED)
├── amount_per_unit (decimal)
├── unit (MINUTE, HOUR, DAY)
├── double_penalty_multiplier (default: 2.0)
├── is_active
├── created_at / updated_at
```

### 2.9 Advances Table
```sql
advances
├── id (PK)
├── user_id (FK)
├── amount
├── date_given
├── deduction_amount_monthly
├── remaining_amount
├── status (ACTIVE, FULLY_DEDUCTED)
├── created_at / updated_at
```

### 2.10 Shift Assignments Table (TL Pre-assigns)
```sql
shift_assignments
├── id (PK)
├── user_id (FK)
├── assigned_by_tl_id (FK)               -- TL or Manager who assigned
├── date
├── shift_type (FULL_TIME, ONLINE_HALF, ONLINE_FULL, EXTRA_SHIFT, OVERTIME)
├── expected_start_time
├── expected_end_time
├── expected_hours (decimal)
├── multiplier (decimal)                  -- 1.0 base; typically 1.5 for OT/EXTRA
├── notes (text)
├── status (SCHEDULED, COMPLETED, CANCELLED)
├── created_at / updated_at
```
> UI: `team-leader/shifts` (T-2.14). Team-scoped for TL; Manager may assign any employee.

### 2.11 Employee Profiles Table (from Employee-inputes.xlsx)
HR personal data lives here — `users` stays auth/work config only.
```sql
employee_profiles
├── id (PK)
├── user_id (FK, unique)
├── employee_code (unique)                -- كود الموظف
├── national_id                           -- رقم قومي
├── birth_date / age (computed)           -- تاريخ ميلاد / العمر
├── address                               -- العنوان
├── phone                                 -- رقم التليفون
├── gender                                -- النوع
├── religion                              -- الديانة
├── marital_status                        -- الحالة الاجتماعية
├── personal_photo_path                   -- صورة شخصية
├── created_at / updated_at
```

### 2.12 Employee Qualifications Table
```sql
employee_qualifications
├── id (PK)
├── user_id (FK)
├── degree                                -- الشهادة
├── specialization                        -- التخصص
├── graduation_year                       -- سنة التخرج
```

### 2.13 Employee Documents Table
```sql
employee_documents
├── id (PK)
├── user_id (FK)
├── type (ID_CARD, QUALIFICATION, BIRTH_CERTIFICATE, CRIMINAL_RECORD, CONTRACT_PDF, OTHER)
├── file_path
├── uploaded_at
```

### 2.14 Emergency Contacts Table
```sql
emergency_contacts
├── id (PK)
├── user_id (FK)
├── name / phone / relationship           -- الاسم / رقم الهاتف / صلة القرابة
```

### 2.15 Contracts Table
```sql
contracts
├── id (PK)
├── user_id (FK)
├── hire_date                             -- تاريخ التعيين
├── contract_start / contract_end         -- بداية / نهاية التعاقد
├── contract_type                         -- نوع العقد (e.g. سنة)
├── document_id (FK → employee_documents, nullable)
├── is_active
```

### 2.16 Official Holidays Table
```sql
official_holidays
├── id (PK)
├── date (unique)
├── name_ar / name_en
```

### 2.17 Vacation Credit Logs Table (audit for §7 flow)
```sql
vacation_credit_logs
├── id (PK)
├── vacation_balance_id (FK)
├── added_by (FK → users)                 -- TL or Admin
├── amount_days (decimal)
├── reason (text)
├── created_at
```

### 2.18 Rest Days Table (monthly quota, marked per date)
```sql
rest_days
├── id (PK)
├── user_id (FK → users, cascade)
├── date                                  -- UNIQUE(user_id, date)
├── created_by (FK → users, nullable)
├── created_at / updated_at
```
> Rest days are NOT a fixed weekday: an employee gets X rest days per month (4 one month,
> 5 another) and the dates vary, so Admin marks each date. Phase-2 attendance computes
> `is_day_off_work` against this table; working a marked rest day earns the §1.2 multipliers
> (FT = 2×, Online = 1.5×).

> ⚠️ Excel-trace notes folded into schema: attendance must support **overnight shifts** (RADWA sheet uses a 12:00 "modify TIME" offset), daily TL evaluation scores are stored **per attendance day** then averaged monthly, and `payroll_records` must also store `official_holiday_work_days`, `hours_deduction`, and per-row commission entries (see `docs/PAYROLL_CALCULATION.md`).

---

## 3. UPDATED ROLE PERMISSIONS MATRIX

| Feature | Admin | Team Leader | Employee |
|---------|-------|-------------|----------|
| **Attendance** | | | |
| Clock In/Out | ✅ All | ✅ Self + Team | ✅ Self |
| View Team Attendance % | ✅ All | ✅ Own Team | ❌ |
| **Permissions** | | | |
| Submit Permission | ✅ Self | ✅ Self | ✅ Self |
| Approve/Reject Permission | ✅ All | ✅ Team Only | ❌ |
| Force Rejected Permission | ✅ | ❌ | ✅ Self |
| **Vacations** | | | |
| Submit Vacation | ✅ Self | ✅ Self | ✅ Self |
| Approve/Reject Vacation | ✅ All | ✅ Team Only | ❌ |
| View Vacation Credit | ✅ All | ✅ Team Only | ✅ Self |
| **Daily Reports** | | | |
| Submit Report | ✅ Self | ✅ Self | ✅ Self |
| Respond to Report | ✅ All | ✅ Team Only | ❌ |
| **Evaluations** | | | |
| Submit Monthly Evaluation | ✅ All | ✅ Team Only | ❌ |
| View Evaluations | ✅ All | ✅ Team Only | ✅ Self |
| **Payroll** | | | |
| View Payroll | ✅ All | ❌ **BLOCKED** | ✅ Self |
| Generate Payroll | ✅ | ❌ | ❌ |
| Approve Payroll | ✅ | ❌ | ❌ |
| **HR Config** | | | |
| Set Employee Rates | ✅ | ❌ | ❌ |
| Set Penalty Rules | ✅ | ❌ | ❌ |
| Add Vacation Credit | ✅ | ✅ Team Only | ❌ |
| Pre-assign Shifts | ✅ | ✅ Team Only | ❌ |

---

## 4. SALARY CALCULATION ENGINE — FINAL FORMULA

### 4.1 Daily Rate Calculation
```
Daily Rate = Base Salary / Monthly Working Days
Hourly Rate = Daily Rate / Daily Working Hours (8 for fulltime)
```

### 4.2 Earnings Components

| Component | Formula |
|-----------|---------|
| Full Time Pay | Daily Rate × Full Time Days |
| Online ½ Day Pay | Employee's `online_half_day_rate` × Online ½ Days |
| Online 1 Day Pay | Employee's `online_full_day_rate` × Online 1 Days |
| Extra Shift Pay | Assigned Hours × Employee's `overtime_hourly_rate` × Multiplier |
| Overtime Pay | OT Hours × Employee's `overtime_hourly_rate` × 1.5 |
| Friday Work — Fulltime | Daily Rate × Friday FT Days × 1.0 |
| Friday Work — Online | Daily Rate × Friday Online Days × 0.5 |
| Day Off Work — Fulltime | Daily Rate × Day Off FT Days × 2.0 |
| Day Off Work — Online | Daily Rate × Day Off Online Days × 1.5 |
| Commissions | Manual input by Admin |
| Bonuses | Manual input by Admin |

### 4.3 Deductions

| Deduction | Formula |
|-----------|---------|
| Late Arrival | Late Minutes × Penalty Per Minute |
| Leave Permission | Absent Minutes × Penalty Per Minute |
| Late Permission | Late Minutes × Penalty Per Minute |
| Excused Absence | Daily Rate × Excused Absence Days × 1 |
| Unexcused Absence | Daily Rate × Unexcused Absence Days × 3 |
| Forced Surcharge | Sum of all permission penalties × 1 (doubled already) |
| Advance Deductions | Sum of active advance monthly deductions |

### 4.4 Net Salary
```
NET SALARY = 
    Full Time Pay
  + Online ½ Day Pay
  + Online 1 Day Pay
  + Extra Shift Pay
  + Overtime Pay
  + Friday Work Bonus
  + Day Off Work Bonus
  + Commissions
  + Bonuses
  - Late Penalties
  - Permission Penalties
  - Excused Absence Penalties
  - Unexcused Absence Penalties
  - Forced Penalty Surcharge
  - Advance Deductions
```

---

## 5. ATTENDANCE STATUS AUTO-CALCULATION LOGIC

```python
def calculate_attendance_status(clock_in, clock_out, shift_type, assigned_shift):

    # 1. Determine planned hours based on shift
    planned_hours = {
        'FULL_TIME': 8.0,
        'ONLINE_HALF': 4.0,
        'ONLINE_FULL': 12.0,
        'EXTRA_SHIFT': assigned_shift.expected_hours,
        'OVERTIME': assigned_shift.expected_hours
    }[shift_type]

    # 2. Calculate actual hours
    actual_hours = (clock_out - clock_in).total_hours

    # 3. Check for lateness
    shift_start = get_shift_start_time(shift_type)
    grace_period = 0  # Or admin-configured

    if clock_in > shift_start + grace_period:
        late_minutes = (clock_in - shift_start).total_minutes
        status = 'LATE'
    else:
        late_minutes = 0

    # 4. Check for early departure
    shift_end = get_shift_end_time(shift_type)
    if clock_out < shift_end:
        early_minutes = (shift_end - clock_out).total_minutes
    else:
        early_minutes = 0

    # 5. Attendance score for evaluation
    attendance_score = min(100, (actual_hours / planned_hours) * 100)

    return {
        'planned_hours': planned_hours,
        'actual_hours': actual_hours,
        'status': status,
        'late_minutes': late_minutes,
        'early_departure_minutes': early_minutes,
        'attendance_score': attendance_score
    }
```

---

## 6. EVALUATION AUTO-CALCULATION

```python
def calculate_monthly_evaluation(user_id, month, year):
    # Each metric = AVG of that month's daily values on attendance rows
    performance     = avg(daily.performance_score)       # TL daily
    behavior        = avg(daily.behavior_score)          # TL daily
    productivity    = avg(daily.productivity_score)      # TL daily
    interest_return = avg(daily.interest_return_score)   # TL daily
    attendance      = avg(daily.attendance_score)        # system daily auto

    evaluation.performance_score      = performance
    evaluation.behavior_score         = behavior
    evaluation.productivity_score     = productivity
    evaluation.interest_return_score  = interest_return
    evaluation.attendance_score       = attendance

    # Total = simple average of 5 (equal weight)
    evaluation.total_score = (
        performance + behavior + productivity + interest_return + attendance
    ) / 5

    # evaluation_text is monthly TL narrative — not part of the numeric average
    return evaluation
```

---

## 7. VACATION CREDIT FLOW (Dynamic)

```
Team Leader opens employee profile
        │
        ▼
┌─────────────────────────────────────────────┐
│  Vacation Balance Card                       │
│  ┌─────────────────────────────────────┐    │
│  │ Accrued: 12.5 days                  │    │
│  │ Used: 5.0 days                      │    │
│  │ Pending: 2.0 days                   │    │
│  │ Remaining: 5.5 days ✅              │    │
│  └─────────────────────────────────────┘    │
│  [+ Add Credit] [View History]            │
└─────────────────────────────────────────────┘
        │
        ▼
TL clicks "+ Add Credit"
        │
        ▼
┌─────────────────────────────────────────────┐
│  Add Vacation Credit                         │
│  Employee: Ahmed                             │
│  Amount: [____] days                         │
│  Reason: [________________]                  │
│  [Save]                                      │
└─────────────────────────────────────────────┘
        │
        ▼
System updates: accrued_days += amount
                remaining_days = accrued - used - pending
```

---

## 8. PAYROLL GENERATION WORKFLOW (Admin Only)

```
Admin opens Payroll Module
        │
        ▼
┌─────────────────────────────────────────────┐
│  Step 1: Select Month & Department           │
│  Month: [June 2026 ▼]                        │
│  Department: [All ▼]                         │
│  [Generate Draft]                            │
└─────────────────────────────────────────────┘
        │
        ▼
System auto-calculates for each employee:
  ├── Attendance data (days, hours, late minutes)
  ├── Permission penalties
  ├── Vacation deductions
  ├── Evaluation scores (from TL)
  ├── Dynamic rates (online, overtime)
  ├── Friday / Day-off multipliers
  └── Advance deductions
        │
        ▼
┌─────────────────────────────────────────────┐
│  Step 2: Review Draft Payroll                │
│  ┌─────────────────────────────────────┐    │
│  │ Employee: Radwa                     │    │
│  │ Base: 5,000 EGP                     │    │
│  │ Full Time: 20 days × 166.67 = 3333  │    │
│  │ Online ½: 10 days × 83.33 = 833     │    │
│  │ Late Penalty: 27 min × 2 = 54 EGP   │    │
│  │ Excused Absence: 1 day = 167 EGP    │    │
│  │ Unexcused Absence: 1 day = 500 EGP  │    │
│  │ Advances: 100 + 67 + 1000 + 25      │    │
│  │ Net: 2,808 EGP                      │    │
│  └─────────────────────────────────────┘    │
│  [Edit] [Approve] [Export PDF]              │
└─────────────────────────────────────────────┘
        │
        ▼
Admin approves → Employee can view payslip
```

---

## 9. UI SCREENS PRIORITY LIST

### Employee Screens (Mobile-First)
1. **Login** — Email + Password + Language toggle (AR/EN)
2. **Dashboard** — Today's shift, Clock In/Out button, quick stats
3. **Clock Screen** — Shift selector (if TL assigned multiple), timer, location
4. **Daily Report** — Text area + submit + view TL response
5. **My Requests** — Permission & Vacation forms + status tracking
6. **My Salary** — Monthly payslip (view only, after Admin approval)
7. **My Evaluation** — Monthly scores + TL text feedback

### Team Leader Screens
1. **Team Dashboard** — All team members' today's status + attendance %
2. **Pending Approvals** — Permissions + Vacations queue
3. **Daily Reports Review** — List of reports with response field
4. **Monthly Evaluation** — Form per employee (5 score averages from daily + monthly text)
5. **Vacation Credit** — Add/Update vacation balance per employee
6. **Shift Assignment** — Pre-assign extra shifts / overtime
7. **Team Attendance %** — Auto-calculated daily/weekly/monthly

### Admin/Manager Screens
1. **Company Dashboard** — All departments overview
2. **Employee Management** — CRUD with document uploads + dynamic rates
3. **Penalty Rules Config** — Per-currency, per-minute rates
4. **Shift Config** — Working hours per shift type
5. **Payroll Generation** — Auto-calculate + bulk approve + export
6. **Annual Reports** — Contract expiry, vacation balances, trends
7. **Currency & Language Settings** — Global configuration

---

## 10. IMPLEMENTATION PHASES (Revised)

### Phase 1: Foundation (Weeks 1-3)
- Database setup & migrations (all 10 tables)
- Authentication system (3 roles with RBAC)
- Employee CRUD with document uploads
- Dynamic rate fields per employee

### Phase 2: Attendance Core (Weeks 4-6)
- Clock In/Out with 5 shift types
- Daily reports + TL responses
- Permission requests (Leave/Late) + approval flow
- Vacation requests + dynamic balance tracking
- TL adds vacation credit

### Phase 3: Evaluation & Shifts (Weeks 7-8)
- Monthly evaluation forms (daily 4 TL scores + auto attendance → monthly avg of 5 + monthly text)
- Auto-attendance score calculation
- TL pre-assigns extra shifts / overtime
- Friday & day-off multiplier logic

### Phase 4: Payroll Engine (Weeks 9-11)
- Penalty rule engine (per-minute, per-currency)
- Salary calculation with all components
- Admin-only payroll generation & approval
- Employee payslip view
- Advance (سلف) management

### Phase 5: Reports & Polish (Weeks 12-14)
- Annual reports + Excel/PDF export
- Multi-currency full support
- Multi-language full coverage (AR/EN)
- Mobile responsiveness polish
- Notifications (email/push for approvals)

---

## 11. KEY BUSINESS RULES SUMMARY

| # | Rule | Impact |
|---|------|--------|
| 1 | Penalty per minute for Late, Leave Permission, Late Permission | Precise deduction calculation |
| 2 | Forced permission = Double penalty | Deterrent for bypassing TL |
| 3 | Excused Absence = 1× day salary | Standard deduction |
| 4 | Unexcused Absence = 3× day salary | Heavy penalty for no-shows |
| 5 | Friday work: FT=1×, Online=0.5× | Weekend compensation |
| 6 | Rest-day work (date marked in `rest_days`): FT=2×, Online=1.5× | Rest day compensation |
| 7 | FT→Online change (non-Fri): hour × 0.5 | Rate adjustment |
| 8 | TL cannot see payroll | Data privacy |
| 9 | Admin sets all rates per employee | Flexibility |
| 10 | TL adds vacation credit dynamically | Weekly/ad-hoc accrual |
| 11 | TL pre-assigns extra shifts | Controlled overtime |
| 12 | Evaluation = Average of 5 daily metrics (incl. Interest Return); text is monthly | Equal-weight scoring |

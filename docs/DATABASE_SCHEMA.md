# HR-Pro — Database Schema (MySQL 8 / MariaDB 10.6+)

- Engine: **InnoDB** · Charset: **utf8mb4 / utf8mb4_unicode_ci** (Arabic support)
- All money columns: `DECIMAL(12,2)` — never `FLOAT`
- All score columns: `DECIMAL(5,2)` (0.00–100.00)
- Every table has `created_at` / `updated_at` unless stated
- In Laravel these map 1:1 to migrations — **no table/column may exist without a migration**, and no unused tables/columns (see `AGENTS.md`)

---

## 1. users

```sql
CREATE TABLE users (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar                 VARCHAR(150) NOT NULL,
    name_en                 VARCHAR(150) NOT NULL,
    email                   VARCHAR(190) NOT NULL UNIQUE,
    password                VARCHAR(255) NOT NULL,
    role                    ENUM('MANAGER','TEAM_LEADER','EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE',
    currency                ENUM('EGP','AED') NOT NULL,
    language_preference     ENUM('ar','en') NOT NULL DEFAULT 'ar',
    team_leader_id          BIGINT UNSIGNED NULL,
    base_salary             DECIMAL(12,2) NOT NULL DEFAULT 0,
    online_half_day_rate    DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Admin sets per employee
    online_full_day_rate    DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Admin sets per employee
    overtime_hourly_rate    DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Admin sets per employee
    shift_type_default      ENUM('FULL_TIME','ONLINE_HALF','ONLINE_FULL') NOT NULL DEFAULT 'FULL_TIME',
    work_start_time         TIME NULL,                          -- مواعيد العمل: حضور
    work_end_time           TIME NULL,                          -- مواعيد العمل: انصراف
    status                  ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    remember_token          VARCHAR(100) NULL,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,
    CONSTRAINT fk_users_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_team_leader (team_leader_id),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Vacation accrual is **dynamic** (TL adds credit) — it lives in `vacation_balances`, not on `users`.

## 2. employee_profiles

```sql
CREATE TABLE employee_profiles (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL UNIQUE,
    employee_code       VARCHAR(50) NOT NULL UNIQUE,
    national_id         VARCHAR(20) NULL,
    birth_date          DATE NULL,
    address             VARCHAR(255) NULL,
    phone               VARCHAR(30) NULL,
    gender              ENUM('MALE','FEMALE') NULL,
    religion            VARCHAR(50) NULL,
    marital_status      ENUM('SINGLE','MARRIED','DIVORCED','WIDOWED') NULL,
    personal_photo_path VARCHAR(255) NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> `age` is **computed** from `birth_date` (accessor) — never stored.

## 3. employee_qualifications

```sql
CREATE TABLE employee_qualifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    degree          VARCHAR(150) NOT NULL,
    specialization  VARCHAR(150) NULL,
    graduation_year SMALLINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_qual_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_qual_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 4. employee_documents

```sql
CREATE TABLE employee_documents (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    type        ENUM('ID_CARD','QUALIFICATION','BIRTH_CERTIFICATE','CRIMINAL_RECORD','CONTRACT_PDF','OTHER') NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    CONSTRAINT fk_docs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_docs_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 5. emergency_contacts

```sql
CREATE TABLE emergency_contacts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(150) NOT NULL,
    phone        VARCHAR(30) NOT NULL,
    relationship VARCHAR(100) NULL,
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    CONSTRAINT fk_emergency_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_emergency_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6. contracts

```sql
CREATE TABLE contracts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    hire_date       DATE NOT NULL,
    contract_start  DATE NOT NULL,
    contract_end    DATE NOT NULL,
    contract_type   VARCHAR(100) NOT NULL,           -- e.g. "سنة"
    document_id     BIGINT UNSIGNED NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_contracts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_doc FOREIGN KEY (document_id) REFERENCES employee_documents(id) ON DELETE SET NULL,
    INDEX idx_contracts_user_active (user_id, is_active),
    INDEX idx_contracts_end (contract_end)           -- annual report: expiry tracking
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 7. attendance

```sql
CREATE TABLE attendance (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                  BIGINT UNSIGNED NOT NULL,
    date                     DATE NOT NULL,
    shift_type               ENUM('FULL_TIME','ONLINE_HALF','ONLINE_FULL','EXTRA_SHIFT','OVERTIME') NOT NULL,
    is_friday_work           TINYINT(1) NOT NULL DEFAULT 0,   -- auto-detected
    is_day_off_work          TINYINT(1) NOT NULL DEFAULT 0,   -- vs rest_days table
    is_official_holiday_work TINYINT(1) NOT NULL DEFAULT 0,   -- vs official_holidays
    assigned_by_tl_id        BIGINT UNSIGNED NULL,            -- set when clocking a matching TL assignment
    clock_in                 DATETIME NULL,
    clock_out                DATETIME NULL,                   -- overnight: next-day datetime, no offset hacks
    planned_hours            DECIMAL(4,2) NOT NULL DEFAULT 0, -- 8 / 4 / 12 / assigned hours
    actual_hours             DECIMAL(4,2) NOT NULL DEFAULT 0,
    status                   ENUM('PRESENT','LATE','EARLY_LEAVE','REST_DAY','OFFICIAL_HOLIDAY',
                                  'EXCUSED_ABSENCE','UNEXCUSED_ABSENCE','VACATION') NOT NULL DEFAULT 'PRESENT',
    late_minutes             INT UNSIGNED NOT NULL DEFAULT 0,
    early_departure_minutes  INT UNSIGNED NOT NULL DEFAULT 0,
    daily_report             TEXT NULL,
    team_leader_response     TEXT NULL,
    -- daily evaluation scores (averaged into monthly evaluation)
    performance_score        DECIMAL(5,2) NULL,               -- TL
    behavior_score           DECIMAL(5,2) NULL,               -- TL
    productivity_score       DECIMAL(5,2) NULL,               -- TL
    interest_return_score    DECIMAL(5,2) NULL,               -- TL (عائد الاهتمام)
    attendance_score         DECIMAL(5,2) NULL,               -- auto: min(100, actual/planned*100)
    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_tl FOREIGN KEY (assigned_by_tl_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_att_user_date_shift (user_id, date, shift_type),
    INDEX idx_att_date (date),
    INDEX idx_att_user_month (user_id, date),
    INDEX idx_att_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Overnight shifts (e.g. 10:00 → 06:00 next day): store real datetimes. The Excel "modify TIME +12:00" hack is **not** carried into the app (see `docs/PAYROLL_CALCULATION.md` §3).
>
> Unique `(user_id, date, shift_type)`: one punch cycle per shift type per day. After clock-out, that type is hidden on `/clock`; employee may still clock a **different** type the same day.

## 8. permissions

```sql
CREATE TABLE permissions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    type                ENUM('LEAVE_PERMISSION','LATE_PERMISSION') NOT NULL,
    request_date        DATE NOT NULL,
    from_time           TIME NOT NULL,
    to_time             TIME NOT NULL,
    reason              TEXT NULL,
    status              ENUM('PENDING','ACCEPTED','REJECTED','FORCED') NOT NULL DEFAULT 'PENDING',
    team_leader_id      BIGINT UNSIGNED NULL,
    penalty_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_double_penalty   TINYINT(1) NOT NULL DEFAULT 0,          -- TRUE when FORCED
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_perm_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_perm_user_date (user_id, request_date),
    INDEX idx_perm_tl_status (team_leader_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 9. vacations

```sql
CREATE TABLE vacations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            ENUM('ANNUAL','SICK','EMERGENCY') NOT NULL,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    days_requested  DECIMAL(4,1) NOT NULL,
    status          ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    team_leader_id  BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_vac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_vac_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vac_user_status (user_id, status),
    INDEX idx_vac_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 10. vacation_balances

```sql
CREATE TABLE vacation_balances (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    year            SMALLINT UNSIGNED NOT NULL,
    accrued_days    DECIMAL(5,1) NOT NULL DEFAULT 0,  -- TL adds dynamically
    used_days       DECIMAL(5,1) NOT NULL DEFAULT 0,
    pending_days    DECIMAL(5,1) NOT NULL DEFAULT 0,
    remaining_days  DECIMAL(5,1) NOT NULL DEFAULT 0,  -- accrued - used - pending
    last_updated_by BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_vb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_vb_updater FOREIGN KEY (last_updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_vb_user_year (user_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 11. vacation_credit_logs

```sql
CREATE TABLE vacation_credit_logs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacation_balance_id BIGINT UNSIGNED NOT NULL,
    added_by            BIGINT UNSIGNED NOT NULL,
    amount_days         DECIMAL(5,1) NOT NULL,
    reason              TEXT NULL,
    created_at          TIMESTAMP NULL,
    CONSTRAINT fk_vcl_balance FOREIGN KEY (vacation_balance_id) REFERENCES vacation_balances(id) ON DELETE CASCADE,
    CONSTRAINT fk_vcl_adder FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_vcl_balance (vacation_balance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 12. evaluations

```sql
CREATE TABLE evaluations (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            BIGINT UNSIGNED NOT NULL,
    team_leader_id     BIGINT UNSIGNED NOT NULL,
    month              TINYINT UNSIGNED NOT NULL,
    year               SMALLINT UNSIGNED NOT NULL,
    performance_score       DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily TL
    behavior_score          DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily TL
    productivity_score      DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily TL
    interest_return_score   DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily TL
    attendance_score        DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily auto
    total_score             DECIMAL(5,2) NOT NULL DEFAULT 0,  -- average of 5
    evaluation_text         TEXT NULL,                        -- monthly narrative only
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    CONSTRAINT fk_eval_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval_user_month (user_id, month, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 13. penalty_rules

```sql
CREATE TABLE penalty_rules (
    id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                      VARCHAR(150) NOT NULL,
    type                      ENUM('LATE_ARRIVAL','LEAVE_PERMISSION','LATE_PERMISSION',
                                   'EXCUSED_ABSENCE','UNEXCUSED_ABSENCE') NOT NULL,
    currency                  ENUM('EGP','AED') NOT NULL,
    amount_per_unit           DECIMAL(12,2) NOT NULL,
    unit                      ENUM('MINUTE','HOUR','DAY') NOT NULL,
    double_penalty_multiplier DECIMAL(4,2) NOT NULL DEFAULT 2.00,
    is_active                 TINYINT(1) NOT NULL DEFAULT 1,
    created_at                TIMESTAMP NULL,
    updated_at                TIMESTAMP NULL,
    UNIQUE KEY uq_rule_type_currency (type, currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 14. payroll_records

```sql
CREATE TABLE payroll_records (
    id                            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                       BIGINT UNSIGNED NOT NULL,
    month                         TINYINT UNSIGNED NOT NULL,
    year                          SMALLINT UNSIGNED NOT NULL,
    currency                      ENUM('EGP','AED') NOT NULL,
    base_salary                   DECIMAL(12,2) NOT NULL,
    -- day/hour counts
    full_time_days                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    online_half_days              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    online_full_days              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    rest_days                     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    extra_shift_hours             DECIMAL(6,2) NOT NULL DEFAULT 0,
    overtime_hours                DECIMAL(6,2) NOT NULL DEFAULT 0,
    friday_work_days              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    day_off_work_days             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    official_holiday_work_days    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hours_deduction               DECIMAL(6,2) NOT NULL DEFAULT 0,   -- from hour shortage (Excel: خصم ساعات)
    late_minutes_total            INT UNSIGNED NOT NULL DEFAULT 0,
    -- earnings
    full_time_pay                 DECIMAL(12,2) NOT NULL DEFAULT 0,
    online_half_pay               DECIMAL(12,2) NOT NULL DEFAULT 0,
    online_full_pay               DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_pay                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    friday_bonus                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    day_off_bonus                 DECIMAL(12,2) NOT NULL DEFAULT 0,
    official_holiday_bonus        DECIMAL(12,2) NOT NULL DEFAULT 0,
    -- deductions
    late_penalty                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    leave_permission_penalty      DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_permission_penalty       DECIMAL(12,2) NOT NULL DEFAULT 0,
    excused_absence_penalty       DECIMAL(12,2) NOT NULL DEFAULT 0,
    unexcused_absence_penalty     DECIMAL(12,2) NOT NULL DEFAULT 0,
    forced_penalty_surcharge      DECIMAL(12,2) NOT NULL DEFAULT 0,
    advance_deductions            DECIMAL(12,2) NOT NULL DEFAULT 0,
    -- totals
    gross_pay                     DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deductions              DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_pay                       DECIMAL(12,2) NOT NULL DEFAULT 0,
    status                        ENUM('DRAFT','APPROVED','PAID') NOT NULL DEFAULT 'DRAFT',
    created_by                    BIGINT UNSIGNED NOT NULL,          -- Admin only
    created_at                    TIMESTAMP NULL,
    updated_at                    TIMESTAMP NULL,
    CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pay_user_month (user_id, month, year),
    INDEX idx_pay_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 15. payroll_adjustments

Manual lines on a payslip — Excel rows 43–49: عمولة القسم / عمولة مبيعات / عمولة ارباح / مكافآت / سلفة.

```sql
CREATE TABLE payroll_adjustments (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_record_id BIGINT UNSIGNED NOT NULL,
    type              ENUM('DEPARTMENT_COMMISSION','SALES_COMMISSION','PROFIT_COMMISSION',
                           'BONUS','ADVANCE','OTHER_DEDUCTION') NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    note              VARCHAR(255) NULL,
    created_by        BIGINT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    CONSTRAINT fk_adj_payroll FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_adj_payroll (payroll_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 16. advances

```sql
CREATE TABLE advances (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                  BIGINT UNSIGNED NOT NULL,
    amount                   DECIMAL(12,2) NOT NULL,
    date_given               DATE NOT NULL,
    deduction_amount_monthly DECIMAL(12,2) NOT NULL,
    remaining_amount         DECIMAL(12,2) NOT NULL,
    status                   ENUM('ACTIVE','FULLY_DEDUCTED') NOT NULL DEFAULT 'ACTIVE',
    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    CONSTRAINT fk_adv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_adv_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 17. shift_assignments

Daily roster / rotation + premium shifts. TL assigns FT/Online as well as EXTRA/OT.

```sql
CREATE TABLE shift_assignments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    assigned_by_tl_id   BIGINT UNSIGNED NOT NULL,
    date                DATE NOT NULL,
    shift_type          ENUM('FULL_TIME','ONLINE_HALF','ONLINE_FULL','EXTRA_SHIFT','OVERTIME') NOT NULL,
    expected_start_time TIME NOT NULL,
    expected_end_time   TIME NOT NULL,
    expected_hours      DECIMAL(4,2) NOT NULL,
    multiplier          DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    notes               TEXT NULL,
    status              ENUM('SCHEDULED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT fk_sa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_tl FOREIGN KEY (assigned_by_tl_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sa_user_date (user_id, date),
    INDEX idx_sa_tl (assigned_by_tl_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Clock rules: preferred = today’s SCHEDULED assignment if any (still available); else `users.shift_type_default` or employee choice among remaining FT/Online types. EXTRA/OT still require assignment. Employee may switch FT↔Online even when a base shift was assigned. App rejects a second SCHEDULED row for the same `user_id + date + shift_type`. Cancel = status `CANCELLED`. Completing a matching clock-out may mark the assignment `COMPLETED`.
>
> Web UI (shipped T-2.14): `GET/POST /team-leader/shifts`, `POST /team-leader/shifts/{id}/cancel` — TL team-scoped; Manager all employees. Service: `ShiftAssignmentService`.

## 18. official_holidays

```sql
CREATE TABLE official_holidays (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date       DATE NOT NULL UNIQUE,
    name_ar    VARCHAR(150) NOT NULL,
    name_en    VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 19. settings

Small key/value store backing Admin global settings (language/currency defaults).

```sql
CREATE TABLE settings (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`      VARCHAR(100) NOT NULL UNIQUE,
    value      TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 20. rest_days

Rest days are a **monthly quota marked per date** (4 one month, 5 another — which dates vary),
not a fixed weekday. Admin marks each rest-day date per employee.

```sql
CREATE TABLE rest_days (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    date       DATE NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_rd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rd_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_rd_user_date (user_id, date),
    INDEX idx_rd_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> `attendance.is_day_off_work` (Phase 2) is computed against this table; the day-off-work
> multipliers (MASTER_CONTEXT §1.2: FT = 2×, Online = 1.5×) apply when an employee works a
> marked rest day.

---

## Entity Relationship Overview

```
users ─┬─ employee_profiles (1:1)
       ├─ employee_qualifications (1:N)
       ├─ employee_documents (1:N) ── contracts (N:1 via document_id)
       ├─ emergency_contacts (1:N)
       ├─ contracts (1:N)
       ├─ rest_days (1:N) ── monthly rest-day dates, marked per date
       ├─ users.team_leader_id (self FK, TL → team members)
       ├─ attendance (1:N) ── daily TL scores
       ├─ permissions (1:N)
       ├─ vacations (1:N)
       ├─ vacation_balances (1:N) ── vacation_credit_logs (1:N)
       ├─ evaluations (1:N per month)
       ├─ payroll_records (1:N per month) ── payroll_adjustments (1:N)
       ├─ advances (1:N)
       └─ shift_assignments (1:N)
official_holidays (standalone, referenced by attendance logic)
penalty_rules (standalone config, referenced by payroll engine)
settings (standalone key/value, backs Admin global settings)
```

## Indexing & Performance Rules (Hostinger shared hosting)

- Every FK is indexed (shown above); monthly reporting queries hit `idx_att_user_month` and `uq_pay_user_month`.
- No full-text search, no JSON columns — keep rows small and queries index-only where possible.
- All list endpoints must paginate (max 50/page) — never `SELECT *` unbounded.
- Payroll generation reads attendance **once per employee per month** (aggregate query), never row-by-row in PHP loops.

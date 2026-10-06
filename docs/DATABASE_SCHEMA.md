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
    job_title_ar            VARCHAR(150) NULL,              -- المسمى الوظيفي
    job_title_en            VARCHAR(150) NULL,
    email                   VARCHAR(190) NOT NULL UNIQUE,
    password                VARCHAR(255) NOT NULL,
    role                    ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT','ASSISTANT','DRIVER','SALES') NOT NULL DEFAULT 'EMPLOYEE',
    can_confirm_gross_loss  TINYINT(1) NOT NULL DEFAULT 0, -- capability gross.loss.confirm for SALES (Manager/Accountant always)
    currency                ENUM('EGP','AED') NOT NULL,
    language_preference     ENUM('ar','en') NOT NULL DEFAULT 'ar',
    base_salary             DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Admin; online/OT derived (base÷30÷2, base÷30, base÷30÷8)
    shift_type_default      ENUM('FULL_TIME','ONLINE_HALF','ONLINE_FULL') NOT NULL DEFAULT 'FULL_TIME',
    shift_types_default     JSON NULL,                               -- profiles: {type,start,end,expected_hours} up to 3 (T-7.3 / T-7.7); expected_hours derived from start/end span
    work_start_time         TIME NULL,                          -- مواعيد العمل: حضور
    work_end_time           TIME NULL,                          -- مواعيد العمل: انصراف
    status                  ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    remember_token          VARCHAR(100) NULL,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Vacation accrual is **dynamic** (TL adds credit) — it lives in `vacation_balances`, not on `users`.
> Team membership (T-8.1): **not** a column on `users`. See §1b `employee_team_leaders` (max two `TEAM_LEADER` rows per employee, app-enforced).

## 1b. employee_team_leaders

Which team leaders an employee belongs to. One required, second optional; both equal. Permission/vacation **queue and decide** use this membership (either TL). `permissions.team_leader_id` / `vacations.team_leader_id` is a first-TL **submit snapshot** (not the notify/queue path). `decided_by` is who accepted/rejected/forced. Notify uses membership (both TLs). Evaluations keep `team_leader_id` as the evaluation owner (T-8.5). Payroll generate “by TL” uses `ledBy` membership.

```sql
CREATE TABLE employee_team_leaders (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    team_leader_id  BIGINT UNSIGNED NOT NULL,
    assigned_by     BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_etl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_etl_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_etl_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_etl_user_tl (user_id, team_leader_id),
    INDEX idx_etl_tl (team_leader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

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
    is_day_off_work          TINYINT(1) NOT NULL DEFAULT 0,   -- vs rest_days date marks
    is_official_holiday_work TINYINT(1) NOT NULL DEFAULT 0,   -- vs official_holidays
    assigned_by_tl_id        BIGINT UNSIGNED NULL,            -- set when clocking a matching TL assignment
    clock_in                 DATETIME NULL,
    clock_in_lat             DECIMAL(10,7) NULL,              -- browser GPS (T-7.2)
    clock_in_lng             DECIMAL(10,7) NULL,
    clock_in_accuracy_m      INT UNSIGNED NULL,
    clock_in_distance_m      INT UNSIGNED NULL,               -- meters from office when configured
    clock_in_outside_geofence TINYINT(1) NOT NULL DEFAULT 0,  -- soft: punch still accepted
    clock_out                DATETIME NULL,                   -- overnight: next-day datetime, no offset hacks
    clock_out_lat            DECIMAL(10,7) NULL,
    clock_out_lng            DECIMAL(10,7) NULL,
    clock_out_accuracy_m     INT UNSIGNED NULL,
    clock_out_distance_m     INT UNSIGNED NULL,
    clock_out_outside_geofence TINYINT(1) NOT NULL DEFAULT 0,
    planned_hours            DECIMAL(4,2) NOT NULL DEFAULT 0, -- 8 / 4 / 12 / assigned hours
    actual_hours             DECIMAL(4,2) NOT NULL DEFAULT 0, -- credited hours (cap / TL override)
    raw_actual_hours         DECIMAL(6,2) NOT NULL DEFAULT 0, -- sum of segment minutes / 60
    credited_hours           DECIMAL(6,2) NULL,               -- TL/Admin manual override (NULL = auto)
    status                   ENUM('PRESENT','LATE','EARLY_LEAVE','REST_DAY','OFFICIAL_HOLIDAY',
                                  'EXCUSED_ABSENCE','UNEXCUSED_ABSENCE','VACATION') NOT NULL DEFAULT 'PRESENT',
    late_minutes             INT UNSIGNED NOT NULL DEFAULT 0,
    adjusted_late_minutes    INT UNSIGNED NULL,               -- TL/Admin override (NULL = auto from 1st segment)
    early_departure_minutes  INT UNSIGNED NOT NULL DEFAULT 0,
    recommended_overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    overtime_tl_recommendation ENUM('NONE','RECOMMEND','NO_OVERTIME') NOT NULL DEFAULT 'NONE',
    overtime_tl_by           BIGINT UNSIGNED NULL,
    overtime_tl_at           TIMESTAMP NULL,
    overtime_admin_decision  ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    overtime_admin_by        BIGINT UNSIGNED NULL,
    overtime_admin_at        TIMESTAMP NULL,
    is_auto_clock_out        TINYINT(1) NOT NULL DEFAULT 0,   -- historical scheduler close; no longer used for new rows
    force_closed_by          BIGINT UNSIGNED NULL,            -- TL/Admin/Assistant closed open punch at actual time
    force_closed_at          TIMESTAMP NULL,
    daily_report             TEXT NULL,
    team_leader_response     TEXT NULL,
    attendance_score         DECIMAL(5,2) NULL,               -- auto: min(100, actual/planned*100)
    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_tl FOREIGN KEY (assigned_by_tl_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_att_ot_tl FOREIGN KEY (overtime_tl_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_att_ot_admin FOREIGN KEY (overtime_admin_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_att_user_date_shift (user_id, date, shift_type),
    INDEX idx_att_date (date),
    INDEX idx_att_user_month (user_id, date),
    INDEX idx_att_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Overnight shifts (e.g. 10:00 → 06:00 next day): store real datetimes. The Excel "modify TIME +12:00" hack is **not** carried into the app (see `docs/PAYROLL_CALCULATION.md` §3).
>
> Unique `(user_id, date, shift_type)`: one **attendance row** per shift type per day; **multiple segments** (clock-in/out pairs) on that row (T-7.9). After clock-out the employee may clock in again on the same shift (mid-shift break); gaps between segments go to TL recommend → Admin final decision. `actual_hours` = credited (min raw, planned, + approved OT); `raw_actual_hours` = segment sum. TL/Admin may override credited hours and late minutes; TL recommends overtime → Admin approves/rejects.
>
> **GPS (T-7.2):** lat/lng/accuracy **required for FULL_TIME** clock-in/out only (browser geolocation; no manual pick). Online½ / Online full / Extra / OT do not require GPS. Soft geofence vs Admin Settings office point + radius (default 1 km): outside still accepted; flags + notify employee/TL/Admin. Auto clock-out removed — manual clock-out only. View like photos: owner · TL team · Manager.

## 7b. attendance_segments

Multiple clock-in → clock-out intervals within one shift row (T-7.9).

```sql
CREATE TABLE attendance_segments (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id            BIGINT UNSIGNED NOT NULL,
    sequence                 SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    clock_in                 DATETIME NOT NULL,
    clock_out                DATETIME NULL,
    clock_in_lat             DECIMAL(10,7) NULL,
    clock_in_lng             DECIMAL(10,7) NULL,
    clock_in_accuracy_m      INT UNSIGNED NULL,
    clock_in_distance_m      INT UNSIGNED NULL,
    clock_in_outside_geofence TINYINT(1) NOT NULL DEFAULT 0,
    clock_out_lat            DECIMAL(10,7) NULL,
    clock_out_lng            DECIMAL(10,7) NULL,
    clock_out_accuracy_m     INT UNSIGNED NULL,
    clock_out_distance_m     INT UNSIGNED NULL,
    clock_out_outside_geofence TINYINT(1) NOT NULL DEFAULT 0,
    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    CONSTRAINT fk_att_seg_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    UNIQUE KEY uq_att_seg_att_seq (attendance_id, sequence),
    INDEX idx_att_seg_att (attendance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 7c. attendance_gaps

Unpaid gap between two segments on the same shift. TL recommends APPROVE/REJECT → Admin APPROVED/REJECTED (T-7.9).

```sql
CREATE TABLE attendance_gaps (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id     BIGINT UNSIGNED NOT NULL,
    gap_start         DATETIME NOT NULL,
    gap_end           DATETIME NOT NULL,
    minutes           INT UNSIGNED NOT NULL,
    tl_recommendation ENUM('PENDING','APPROVE','REJECT') NOT NULL DEFAULT 'PENDING',
    tl_decided_by     BIGINT UNSIGNED NULL,
    tl_decided_at     TIMESTAMP NULL,
    tl_note           VARCHAR(500) NULL,
    admin_decision    ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_decided_by  BIGINT UNSIGNED NULL,
    admin_decided_at  TIMESTAMP NULL,
    admin_note        VARCHAR(500) NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    CONSTRAINT fk_att_gap_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_gap_tl FOREIGN KEY (tl_decided_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_att_gap_admin FOREIGN KEY (admin_decided_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_att_gaps_att_admin (attendance_id, admin_decision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 7d. attendance_photos

Optional multi-image proof at clock-in and/or clock-out (T-6.6). Files on `local` disk outside web root; served via authorized route.

```sql
CREATE TABLE attendance_photos (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id   BIGINT UNSIGNED NOT NULL,
    attendance_segment_id BIGINT UNSIGNED NULL,            -- links punch photos to segment (T-7.9)
    kind            ENUM('CLOCK_IN','CLOCK_OUT','FIELD') NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255) NULL,
    description     VARCHAR(500) NULL,                   -- caption per image (required for DRIVER)
    lat             DECIMAL(10,7) NULL,                  -- mid-day FIELD GPS (T-7.10)
    lng             DECIMAL(10,7) NULL,
    accuracy_m      INT UNSIGNED NULL,
    distance_m      INT UNSIGNED NULL,
    outside_geofence TINYINT(1) NOT NULL DEFAULT 0,
    mime_type       VARCHAR(100) NULL,
    size_bytes      INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_att_photo_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_photo_seg FOREIGN KEY (attendance_segment_id) REFERENCES attendance_segments(id) ON DELETE SET NULL,
    INDEX idx_att_photos_att_kind (attendance_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Optional on both punches; **no count limit**; each file ≤ **50MB** (jpg/jpeg/png/webp) — see `config/attendance.php` `photos.*`. Optional **description** per image (T-6.7); **required** for DRIVER. **FIELD** kind = mid-day uploads while shift is open (DRIVER only; GPS + description required). View: owner · team TL · Manager. UI: `/clock`, employee sessions, TL/Admin today board + hours day list, report review.

## 7e. attendance_daily_scores

Per-TL daily scores on an attendance row (T-8.5). Attendance % stays on `attendance`. Unique pair: one score row per TL per day.

```sql
CREATE TABLE attendance_daily_scores (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id           BIGINT UNSIGNED NOT NULL,
    team_leader_id          BIGINT UNSIGNED NOT NULL,         -- evaluator (TL or Manager)
    scored_by               BIGINT UNSIGNED NOT NULL,         -- actor (same as team_leader_id)
    performance_score       DECIMAL(5,2) NOT NULL,
    behavior_score          DECIMAL(5,2) NOT NULL,
    productivity_score      DECIMAL(5,2) NOT NULL,
    interest_return_score   DECIMAL(5,2) NOT NULL,            -- عائد الفائدة
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,
    CONSTRAINT fk_ads_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    CONSTRAINT fk_ads_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ads_scored_by FOREIGN KEY (scored_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ads_attendance_tl (attendance_id, team_leader_id),
    INDEX idx_ads_tl (team_leader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Each membership TL (and Manager) saves **their** four metrics. The other TL’s scores are read-only on report review. One official daily-report reply still locks.

## 8. permissions

```sql
CREATE TABLE permissions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    type                ENUM('LEAVE_PERMISSION','LATE_PERMISSION','ABSENCE_PERMISSION') NOT NULL,
    request_date        DATE NOT NULL,                        -- start date (also the only date for leave/late)
    end_date            DATE NULL,                            -- ABSENCE period end (inclusive); leave/late = request_date
    from_time           TIME NOT NULL,                        -- ABSENCE uses 00:00–23:59 sentinels (UI hides times)
    to_time             TIME NOT NULL,
    reason              TEXT NULL,
    status              ENUM('PENDING','ACCEPTED','REJECTED','FORCED') NOT NULL DEFAULT 'PENDING',
    team_leader_id      BIGINT UNSIGNED NULL,                  -- first-TL snapshot at submit (queue uses membership)
    decided_by          BIGINT UNSIGNED NULL,                  -- who accepted / rejected / forced
    penalty_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_double_penalty   TINYINT(1) NOT NULL DEFAULT 0,          -- TRUE when FORCED
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_perm_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_perm_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_perm_user_date (user_id, request_date),
    INDEX idx_perm_tl_status (team_leader_id, status),
    INDEX idx_perm_decided_by (decided_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **ABSENCE_PERMISSION (T-15.1):** period `request_date`→`end_date` (inclusive, max 31 days). Accept/Force writes `attendance.status = EXCUSED_ABSENCE` for each **working** day (rest pool / official holiday / approved vacation skipped). Reject leaves no attendance rows. TL may submit for Admin decision.

## 9. vacations

```sql
CREATE TABLE vacations (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              BIGINT UNSIGNED NOT NULL,
    type                 ENUM('ANNUAL','SICK','EMERGENCY') NOT NULL,
    start_date           DATE NOT NULL,
    end_date             DATE NOT NULL,
    days_requested       DECIMAL(4,1) NOT NULL,
    replace_rest_date    DATE NULL,              -- rest day given up (swap)
    replace_rest_day_id  BIGINT UNSIGNED NULL,  -- FK until approve (then null)
    status               ENUM('PENDING','APPROVED','REJECTED','FORCED') NOT NULL DEFAULT 'PENDING',
    -- FORCED = rejected then forced (product A): day off kept; rest day NOT consumed
    team_leader_id       BIGINT UNSIGNED NULL,                 -- first-TL snapshot at submit (queue uses membership)
    decided_by           BIGINT UNSIGNED NULL,                 -- who accepted / rejected / forced
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    CONSTRAINT fk_vac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_vac_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vac_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vac_rest FOREIGN KEY (replace_rest_day_id) REFERENCES rest_days(id) ON DELETE SET NULL,
    INDEX idx_vac_user_status (user_id, status),
    INDEX idx_vac_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Product (2026-09): **ANNUAL** and **SICK** = multi-day From→To; balance reserved on submit (`pending_days`), confirmed on approve (`used_days`), released on reject. **EMERGENCY** = one day + rest-day swap (no balance). On approve swap types delete rest row; annual/sick have no rest link. TL adds credit via `vacation_credit_logs`.

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
    performance_score       DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of that TL's daily scores
    behavior_score          DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of that TL's daily scores
    productivity_score      DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of that TL's daily scores
    interest_return_score   DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of that TL's daily scores
    attendance_score        DECIMAL(5,2) NOT NULL DEFAULT 0,  -- avg of daily auto (shared)
    total_score             DECIMAL(5,2) NOT NULL DEFAULT 0,  -- average of 5
    evaluation_text         TEXT NULL,                        -- monthly narrative only
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    CONSTRAINT fk_eval_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_tl FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval_user_tl_month (user_id, team_leader_id, month, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> One row per `(employee, evaluator, month, year)`. Four % = AVG of that evaluator’s `attendance_daily_scores`; attendance % = AVG of `attendance.attendance_score`. Combined month (payslip / annual) = average of existing TL `total_score`s for that month.

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
    extra_shift_hours             DECIMAL(6,2) NOT NULL DEFAULT 0,  -- legacy; folded into overtime_hours (same OT formula)
    overtime_hours                DECIMAL(6,2) NOT NULL DEFAULT 0,  -- EXTRA + OVERTIME shift hours + Admin-APPROVED recommended OT (minutes/60)
    overtime_multiplier           DECIMAL(5,2) NOT NULL DEFAULT 0,   -- 0 = use config/payroll.php overtime multiplier
    friday_work_days              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    day_off_work_days             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    official_holiday_work_days    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    extra_days                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- Excel إضافي days (manual)
    hours_deduction               DECIMAL(6,2) NOT NULL DEFAULT 0,   -- from hour shortage (Excel: خصم ساعات hours)
    hours_deduction_amount        DECIMAL(12,2) NOT NULL DEFAULT 0,  -- money = base/(30×8)×hours
    excused_absence_days          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    unexcused_absence_days        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    late_minutes_total            INT UNSIGNED NOT NULL DEFAULT 0,
    -- earnings
    full_time_pay                 DECIMAL(12,2) NOT NULL DEFAULT 0,
    online_half_pay               DECIMAL(12,2) NOT NULL DEFAULT 0,
    online_full_pay               DECIMAL(12,2) NOT NULL DEFAULT 0,
    overtime_pay                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    friday_bonus                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    day_off_bonus                 DECIMAL(12,2) NOT NULL DEFAULT 0,
    rest_day_pay                  DECIMAL(12,2) NOT NULL DEFAULT 0,  -- legal rest days not worked × daily rate
    official_holiday_bonus        DECIMAL(12,2) NOT NULL DEFAULT 0,
    extra_pay                     DECIMAL(12,2) NOT NULL DEFAULT 0,  -- Excel إضافي amount (manual / days×rate)
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

Small key/value store backing Admin global settings (language/currency defaults, office geofence, **login-as-user**).

Known keys (no extra columns): `default_language`, `default_currency`, `office_latitude`, `office_longitude`, `office_radius_meters`, `impersonation_enabled` (`1`/`0`, default off; enabling requires `.env` `IMPERSONATION_ENABLE_OTP`, not the normal Settings form).

Payroll pay multipliers are **not** stored here — defaults live in `config/payroll.php`; per-employee overtime multiplier / extra pay are edited on each payroll draft.

```sql
CREATE TABLE settings (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`      VARCHAR(100) NOT NULL UNIQUE,
    value      TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 20. Rest days (date-based)

Per-employee rest dates (monthly quota marked per date — not weekly weekdays).
`users.rest_weekdays` removed (2026-08-03).

```sql
CREATE TABLE rest_days (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    CONSTRAINT fk_rd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rd_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_rd_user_date (user_id, date),
    INDEX idx_rd_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> `attendance.is_day_off_work` is true when a `rest_days` row exists for the clock-in date.
> Working a rest day earns the §1.2 multipliers (FT = 2×, Online = 1.5×).
> Admin/TL add dates; vacation/emergency **swaps** one rest date for the day off.

---

## Entity Relationship Overview

```
users ─┬─ employee_profiles (1:1)
       ├─ employee_qualifications (1:N)
       ├─ employee_documents (1:N) ── contracts (N:1 via document_id)
       ├─ emergency_contacts (1:N)
       ├─ contracts (1:N)
       ├─ rest_days (1:N, date-based)
       ├─ employee_team_leaders (N:M, max 2 TLs per employee)
       ├─ attendance (1:N) ── daily TL scores · attendance_photos (1:N)
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
valuation_assignments / valuation_sheets (Service + Product daily valuation; T-9.4)
gross_* (Mohra Gross MVP — T-10.x; see §22)
```

## 21. Valuation (Service / Product)

Admin assigns any active user to `service`, `product`, or both. Each assignee fills **their own** sheet **per day**. Filler + Manager may view; money figures are on the filler’s own sheet.

```sql
CREATE TABLE valuation_assignments (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    assigned_by  BIGINT UNSIGNED NULL,
    module       VARCHAR(20) NOT NULL, -- service | product
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    UNIQUE KEY uq_val_asg_user_module (user_id, module),
    INDEX idx_val_asg_module (module)
);

-- Mohra sidebar pages per non-Manager user (Manager always has all). See config/gross_pages.php.
CREATE TABLE gross_user_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    page_key VARCHAR(32) NOT NULL, -- items|categories|attributes|stock|suppliers|purchases|customers|sales|sales_stats|shipping|treasury|expense_types|receipt_purposes
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_user_page (user_id, page_key),
    INDEX idx_gross_user_page_key (page_key),
    CONSTRAINT fk_gross_user_page_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE valuation_sheets (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    module     VARCHAR(20) NOT NULL,
    date       DATE NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    input      JSON NOT NULL,  -- typed Excel fields (scores 0–1, targets, notes)
    result     JSON NOT NULL,  -- calculator output (ratios, kpi/cashflow labels, stars)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_val_sheet (user_id, module, date),
    INDEX idx_val_sheet_module_date (module, date)
);
```

Result formulas match `docs/Valuation 07-2026.xlsx`: sales `AVERAGE` of six channels; count/amount totals and ratios; KPI `IF` bands خسائر/مقبول/مكسب/فوق المتوقع; cash-flow سالب/موجب/نقطة أمان; earnings high/low vs cash-flow base.

## 22. Mohra Gross (Product only — Phase 10)

Rules: `docs/GROSS_MODULE.md`. Base stock unit = **PIECE**. Money = `DECIMAL(12,2)`.

```sql
CREATE TABLE gross_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name_ar VARCHAR(120) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_cat_parent (parent_id),
    CONSTRAINT fk_gross_cat_parent FOREIGN KEY (parent_id) REFERENCES gross_categories(id) ON DELETE SET NULL
);

CREATE TABLE gross_item_attributes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('COLOR','SIZE','MODEL') NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_attr_kind_en (kind, name_en),
    INDEX idx_gross_attr_kind (kind)
);

CREATE TABLE gross_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(191) NOT NULL,
    color_id BIGINT UNSIGNED NULL,
    size_id BIGINT UNSIGNED NULL,
    model_id BIGINT UNSIGNED NULL,
    color VARCHAR(64) NULL, -- denormalized label from attribute (invoice snapshots)
    size VARCHAR(64) NULL,
    model VARCHAR(64) NULL,
    description TEXT NULL,
    category_id BIGINT UNSIGNED NULL,
    subcategory_id BIGINT UNSIGNED NULL,
    pieces_per_carton INT UNSIGNED NOT NULL DEFAULT 1,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 5, -- pieces
    default_sale_price DECIMAL(12,2) NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_item_code (code),
    INDEX idx_gross_item_status (status),
    INDEX idx_gross_item_color (color_id),
    INDEX idx_gross_item_size (size_id),
    INDEX idx_gross_item_model (model_id),
    CONSTRAINT fk_gross_item_cat FOREIGN KEY (category_id) REFERENCES gross_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_item_sub FOREIGN KEY (subcategory_id) REFERENCES gross_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_item_color FOREIGN KEY (color_id) REFERENCES gross_item_attributes(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_item_size FOREIGN KEY (size_id) REFERENCES gross_item_attributes(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_item_model FOREIGN KEY (model_id) REFERENCES gross_item_attributes(id) ON DELETE SET NULL
);

CREATE TABLE gross_item_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL, -- local disk, outside public/
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(64) NULL,
    size_bytes INT UNSIGNED NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_item_images_item_sort (item_id, sort_order),
    CONSTRAINT fk_gross_item_img_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE CASCADE
);

CREATE TABLE gross_stock_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    qty_on_hand_pieces INT NOT NULL DEFAULT 0,
    qty_with_courier_pieces INT NOT NULL DEFAULT 0,
    qty_lost_pieces INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_bal_item (item_id),
    CONSTRAINT fk_gross_bal_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE CASCADE
);

CREATE TABLE gross_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL, -- ADJUSTMENT_IN|ADJUSTMENT_OUT|SALE_OUT|SALE_RETURN|SALE_RETURN_CLIENT|SALE_DELIVERED|REFUSE_RETURN|LOSS_CONFIRM|CANCEL_RESTORE|PURCHASE_IN|PURCHASE_RETURN
    qty INT UNSIGNED NOT NULL,
    unit ENUM('PIECE','CARTON') NOT NULL,
    conversion_factor INT UNSIGNED NOT NULL,
    qty_pieces INT UNSIGNED NOT NULL,
    ref_type VARCHAR(64) NULL,
    ref_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_mov_item (item_id, created_at),
    INDEX idx_gross_mov_ref (ref_type, ref_id),
    CONSTRAINT fk_gross_mov_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_mov_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE gross_shipping_companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE gross_customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    address TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_cust_phone (phone),
    INDEX idx_gross_cust_name (name)
);

-- Phase 11 start (T-11.1). Purchases/treasury tables added with later T-11.x migrations.
CREATE TABLE gross_suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    address TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_supp_phone (phone),
    INDEX idx_gross_supp_name (name)
);

CREATE TABLE gross_purchase_invoice_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_month CHAR(6) NOT NULL, -- YYYYMM
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_gross_pur_inv_seq (year_month)
);

CREATE TABLE gross_purchase_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(32) NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    supplier_name VARCHAR(191) NOT NULL,
    supplier_phone VARCHAR(40) NOT NULL,
    supplier_address TEXT NULL,
    currency ENUM('EGP','AED') NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('DRAFT','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_pur_inv_number (invoice_number),
    INDEX idx_gross_pur_inv_status (status),
    INDEX idx_gross_pur_inv_supplier (supplier_id),
    CONSTRAINT fk_gross_pur_inv_supp FOREIGN KEY (supplier_id) REFERENCES gross_suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_pur_inv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gross_purchase_invoice_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_invoice_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    item_code_snapshot VARCHAR(64) NOT NULL,
    item_name_snapshot VARCHAR(191) NOT NULL,
    color_snapshot VARCHAR(120) NULL,
    size_snapshot VARCHAR(120) NULL,
    model_snapshot VARCHAR(120) NULL,
    qty INT UNSIGNED NOT NULL,
    unit ENUM('PIECE','CARTON') NOT NULL DEFAULT 'PIECE',
    conversion_factor_snapshot INT UNSIGNED NOT NULL,
    qty_pieces INT UNSIGNED NOT NULL,
    qty_returned_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    buy_price DECIMAL(12,2) NOT NULL,
    sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_pur_line_inv (purchase_invoice_id),
    CONSTRAINT fk_gross_pur_line_inv FOREIGN KEY (purchase_invoice_id) REFERENCES gross_purchase_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_pur_line_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE SET NULL
);

CREATE TABLE gross_purchase_return_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_month CHAR(6) NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_gross_pur_ret_seq (year_month)
);

CREATE TABLE gross_purchase_returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(32) NOT NULL,
    purchase_invoice_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_pur_ret_number (return_number),
    INDEX idx_gross_pur_ret_inv (purchase_invoice_id),
    CONSTRAINT fk_gross_pur_ret_inv FOREIGN KEY (purchase_invoice_id) REFERENCES gross_purchase_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_pur_ret_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gross_purchase_return_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_return_id BIGINT UNSIGNED NOT NULL,
    purchase_invoice_line_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    qty INT UNSIGNED NOT NULL,
    unit ENUM('PIECE','CARTON') NOT NULL DEFAULT 'PIECE',
    conversion_factor INT UNSIGNED NOT NULL,
    qty_pieces INT UNSIGNED NOT NULL,
    buy_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_pur_ret_line_ret (purchase_return_id),
    INDEX idx_gross_pur_ret_line_src (purchase_invoice_line_id),
    CONSTRAINT fk_gross_pur_ret_line_ret FOREIGN KEY (purchase_return_id) REFERENCES gross_purchase_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_pur_ret_line_src FOREIGN KEY (purchase_invoice_line_id) REFERENCES gross_purchase_invoice_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_pur_ret_line_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE SET NULL
);

CREATE TABLE gross_treasury_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('CASH','BANK') NOT NULL,
    name VARCHAR(191) NOT NULL,
    currency ENUM('EGP','AED') NOT NULL DEFAULT 'EGP',
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_treas_kind_cur (kind, currency)
);

CREATE TABLE gross_treasury_voucher_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_month CHAR(6) NOT NULL,
    kind ENUM('PAYMENT','RECEIPT') NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_gross_treas_voucher_seq (year_month, kind)
);

CREATE TABLE gross_expense_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(120) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_expense_type_en (name_en)
);

CREATE TABLE gross_receipt_purposes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(120) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_receipt_purpose_en (name_en)
);

CREATE TABLE gross_treasury_vouchers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_number VARCHAR(32) NOT NULL,
    type ENUM('PAYMENT','RECEIPT') NOT NULL,
    purpose ENUM('SUPPLIER','EXPENSE','COURIER_COD','OTHER') NOT NULL DEFAULT 'SUPPLIER',
    account_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    expense_type_id BIGINT UNSIGNED NULL,
    receipt_purpose_id BIGINT UNSIGNED NULL,   -- required for new purpose=OTHER (T-15.2); legacy OTHER may be null
    sales_invoice_id BIGINT UNSIGNED NULL,
    shipping_company_id BIGINT UNSIGNED NULL,
    currency ENUM('EGP','AED') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    voucher_date DATE NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_treas_voucher_number (voucher_number),
    UNIQUE KEY uq_gross_treas_courier_cod_invoice (sales_invoice_id),
    INDEX idx_gross_treas_voucher_type_date (type, voucher_date),
    INDEX idx_gross_treas_voucher_supplier (supplier_id),
    INDEX idx_gross_treas_voucher_purpose (purpose),
    INDEX idx_gross_treas_voucher_receipt_purpose (receipt_purpose_id),
    INDEX idx_gross_treas_courier_cod_ship (shipping_company_id),
    CONSTRAINT fk_gross_treas_voucher_account FOREIGN KEY (account_id) REFERENCES gross_treasury_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_gross_treas_voucher_supplier FOREIGN KEY (supplier_id) REFERENCES gross_suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_treas_voucher_expense FOREIGN KEY (expense_type_id) REFERENCES gross_expense_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_treas_voucher_receipt_purpose FOREIGN KEY (receipt_purpose_id) REFERENCES gross_receipt_purposes(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_treas_voucher_invoice FOREIGN KEY (sales_invoice_id) REFERENCES gross_sales_invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_treas_voucher_ship FOREIGN KEY (shipping_company_id) REFERENCES gross_shipping_companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_treas_voucher_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gross_treasury_voucher_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_treas_att_voucher (voucher_id),
    CONSTRAINT fk_gross_treas_att_voucher FOREIGN KEY (voucher_id) REFERENCES gross_treasury_vouchers(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_treas_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gross_invoice_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_month CHAR(6) NOT NULL, -- YYYYMM
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_gross_inv_seq (year_month)
);

CREATE TABLE gross_sales_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(32) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(191) NOT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    customer_address TEXT NULL,
    sales_user_id BIGINT UNSIGNED NULL,
    shipping_company_id BIGINT UNSIGNED NULL,
    shipping_company_name VARCHAR(191) NULL,
    currency ENUM('EGP','AED') NOT NULL,
    shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_fee_status ENUM('NONE','PENDING','LOST','SETTLED','COLLECTED') NOT NULL DEFAULT 'NONE',
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    returned_merchandise DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('DRAFT','CONFIRMED','DELIVERED','REFUSED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    refused_at TIMESTAMP NULL,
    refused_by BIGINT UNSIGNED NULL,
    refuse_reason TEXT NULL,
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    fulfillment_status VARCHAR(32) NULL COMMENT 'PREPARING|READY_WAREHOUSE|WITH_COURIER',
    packing_image_path VARCHAR(255) NULL,
    packing_image_original_name VARCHAR(191) NULL,
    packing_image_mime VARCHAR(64) NULL,
    packing_image_size_bytes INT UNSIGNED NULL,
    packing_uploaded_at TIMESTAMP NULL,
    packing_uploaded_by BIGINT UNSIGNED NULL,
    shipping_fee_collected_at TIMESTAMP NULL,
    shipping_fee_collected_by BIGINT UNSIGNED NULL,
    cod_collection_status ENUM('NONE','PENDING','COLLECTED','LOST') NOT NULL DEFAULT 'NONE',
    cod_collected_amount DECIMAL(12,2) NULL,
    cod_collected_at TIMESTAMP NULL,
    cod_collected_by BIGINT UNSIGNED NULL,
    delivered_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_gross_inv_number (invoice_number),
    INDEX idx_gross_inv_status (status),
    INDEX idx_gross_inv_fulfillment (fulfillment_status),
    INDEX idx_gross_inv_created (created_at),
    CONSTRAINT fk_gross_inv_cust FOREIGN KEY (customer_id) REFERENCES gross_customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_inv_sales FOREIGN KEY (sales_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_inv_ship FOREIGN KEY (shipping_company_id) REFERENCES gross_shipping_companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_inv_refused_by FOREIGN KEY (refused_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_inv_packing_by FOREIGN KEY (packing_uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_gross_inv_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE gross_sales_invoice_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(120) NULL,
    description TEXT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_sales_media_invoice (sales_invoice_id),
    CONSTRAINT fk_gross_sales_media_invoice FOREIGN KEY (sales_invoice_id) REFERENCES gross_sales_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_sales_media_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gross_sales_invoice_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    item_code_snapshot VARCHAR(64) NOT NULL,
    item_name_snapshot VARCHAR(191) NOT NULL,
    color_snapshot VARCHAR(64) NULL,
    size_snapshot VARCHAR(64) NULL,
    model_snapshot VARCHAR(64) NULL,
    qty INT UNSIGNED NOT NULL,
    unit ENUM('PIECE','CARTON') NOT NULL,
    conversion_factor_snapshot INT UNSIGNED NOT NULL,
    qty_pieces INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    returned_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    lost_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_line_inv (sales_invoice_id),
    CONSTRAINT fk_gross_line_inv FOREIGN KEY (sales_invoice_id) REFERENCES gross_sales_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_line_item FOREIGN KEY (item_id) REFERENCES gross_items(id) ON DELETE SET NULL
);

CREATE TABLE gross_shipment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_line_id BIGINT UNSIGNED NOT NULL,
    shipping_company_id BIGINT UNSIGNED NULL,
    conversion_factor_snapshot INT UNSIGNED NOT NULL,
    qty_shipped_pieces INT UNSIGNED NOT NULL,
    qty_delivered_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    qty_returned_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    qty_returned_received_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    qty_with_courier_pieces INT UNSIGNED NOT NULL,
    qty_lost_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_gross_alloc_line (sales_invoice_line_id),
    CONSTRAINT fk_gross_alloc_line FOREIGN KEY (sales_invoice_line_id) REFERENCES gross_sales_invoice_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_gross_alloc_ship FOREIGN KEY (shipping_company_id) REFERENCES gross_shipping_companies(id) ON DELETE SET NULL
);
```

## Indexing & Performance Rules (Hostinger shared hosting)

- Every FK is indexed (shown above); monthly reporting queries hit `idx_att_user_month` and `uq_pay_user_month`.
- No full-text search, no JSON columns — keep rows small and queries index-only where possible.
- All list endpoints must paginate (max 50/page) — never `SELECT *` unbounded.
- Payroll generation reads attendance **once per employee per month** (aggregate query), never row-by-row in PHP loops.

## 23. Product calculated expenses (Mohra only)

**Single source of truth with Gross treasury:** categories = `gross_expense_types`; actual spend = `gross_treasury_vouchers` where `type=PAYMENT` and `purpose=EXPENSE`. Monthly **expected** budgets only live in `product_expense_month_budgets`.

Formula: `remaining = expected − SUM(expense vouchers for type in calendar month)`.

```sql
-- extends gross_expense_types: sort_order, supports_rent_period (Rent=true); seed includes Petrol

CREATE TABLE product_expense_month_budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_type_id BIGINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    currency ENUM('EGP','AED') NOT NULL DEFAULT 'EGP',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_prod_exp_budget_type (expense_type_id, year, month),
    INDEX idx_prod_exp_budget_ym (year, month),
    CONSTRAINT fk_prod_exp_budget_type FOREIGN KEY (expense_type_id) REFERENCES gross_expense_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_prod_exp_budget_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

Adding spend from Product expense detail posts the same إذن صرف as `/product/gross/treasury/create?type=PAYMENT&purpose=EXPENSE`. Manager/Accountant write; Product access view. Does **not** feed valuation Gross tiles.

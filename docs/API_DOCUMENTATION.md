# HR-Pro — REST API Documentation v1.0

- Base URL: `https://generalsys.idigital-cs.com/api/v1`
- Auth: **Laravel Sanctum** — `Authorization: Bearer {token}` (web Blade app uses session cookies on the same guards)
- Roles: `MANAGER` (admin), `TEAM_LEADER`, `EMPLOYEE`
- All responses JSON, UTF-8. Dates `YYYY-MM-DD`, datetimes ISO-8601, money as string `"1234.50"` with `currency` field.
- Errors: RFC-7807 style → `{ "message": "...", "errors": { "field": ["..."] } }` with proper HTTP codes.
- Rate limit: 60 req/min per user (Laravel `throttle` middleware).
- Every list endpoint: paginated (`page`, `per_page` ≤ 50) → `{ data: [...], meta: { current_page, last_page, total } }`.

---

## 1. Auth

### POST /auth/login
```json
{ "email": "radwa@example.com", "password": "••••••", "device_name": "android" }
```
→ `200 { "token": "...", "user": { "id", "name_ar", "name_en", "role", "currency", "language_preference" } }`

### POST /auth/logout · GET /auth/me
`GET /auth/me` → current user + `team_leader_id`, `shift_type_default`, `work_start_time`, `work_end_time`.

> Rest days are managed **per date** (monthly quota, not a fixed weekday): Admin marks dates
> Rest weekdays live on `users.rest_weekdays` (ISO weekdays). Phase-2 attendance sets
> `is_day_off_work` when the clock-in weekday is in that list.

---

## 2. Employees (Admin only unless noted)

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| GET | /employees | MANAGER | List (filters: `role`, `status`, `team_leader_id`, `q`) |
| POST | /employees | MANAGER | Create user + profile + contract in one transaction |
| GET | /employees/{id} | MANAGER, TL(own team), EMPLOYEE(self) | Full profile |
| PUT | /employees/{id} | MANAGER | Update work config & rates |
| PUT | /employees/{id}/rates | MANAGER | `base_salary`, `online_half_day_rate`, `online_full_day_rate`, `overtime_hourly_rate` |
| PATCH | /employees/{id}/status | MANAGER | `{ "status": "ACTIVE" \| "INACTIVE" }` |

### Sub-resources
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/PUT/DELETE | /employees/{id}/qualifications | CRUD degrees |
| GET/POST/DELETE | /employees/{id}/documents | Multipart upload; types: `ID_CARD`, `QUALIFICATION`, `BIRTH_CERTIFICATE`, `CRIMINAL_RECORD`, `CONTRACT_PDF`, `OTHER` |
| GET/POST/PUT/DELETE | /employees/{id}/emergency-contacts | CRUD contacts |
| GET/POST/PUT | /employees/{id}/contracts | Contract lifecycle; response includes `days_to_expiry` |

**Employee response shape**
```json
{
  "id": 7, "employee_code": "EMP-007", "name_ar": "رضوى", "name_en": "RADWA",
  "role": "EMPLOYEE", "status": "ACTIVE", "currency": "EGP",
  "team_leader_id": 3, "shift_type_default": "ONLINE_FULL",
  "work_start_time": "10:00", "work_end_time": "18:00",
  "rates": { "base_salary": "5000.00", "online_half_day_rate": "83.33",
             "online_full_day_rate": "166.67", "overtime_hourly_rate": "31.25" },
  "profile": { "national_id": "...", "birth_date": "1998-04-12", "age": 28,
               "phone": "...", "gender": "FEMALE", "marital_status": "SINGLE" },
  "active_contract": { "hire_date": "2025-10-08", "contract_start": "2025-10-08",
                       "contract_end": "2026-10-08", "contract_type": "سنة" }
}
```
> `rates` is **only** present for MANAGER and the employee self — never for TEAM_LEADER (payroll privacy rule).

---

## 3. Attendance

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | /attendance/clock-in | ALL (self) | `{ "shift_type": "FULL_TIME" }` — server stamps time, detects Friday/day-off/holiday |
| POST | /attendance/clock-out | ALL (self) | Closes open record, computes `actual_hours`, `late_minutes`, status |
| GET | /attendance/my | ALL | Own records, filters `from`, `to`, `status` |
| GET | /attendance/team | TEAM_LEADER | Team records + daily attendance % |
| GET | /attendance | MANAGER | All, filters `user_id`, `from`, `to` |
| POST/PUT | /attendance/{id}/report | ALL (self) | Daily report text |
| PUT | /attendance/{id}/report-response | TEAM_LEADER, MANAGER | TL response to report |
| PUT | /attendance/{id}/scores | TEAM_LEADER(team), MANAGER | Daily scores `{ performance, behavior, productivity, interest_return }` (0–100 each); `attendance` is server-computed |

**Shipped web routes (Blade session auth — primary UI):**

| Method | Route name | Path | Role |
|--------|------------|------|------|
| GET | `employee.clock` | `/clock` | EMPLOYEE/TL/MANAGER |
| POST | `employee.clock.in` | `/clock/in` | EMPLOYEE/TL/MANAGER — `{ shift_type, photos[]? }` optional multi images |
| POST | `employee.clock.out` | `/clock/out` | EMPLOYEE/TL/MANAGER — `{ photos[]? }` optional multi images |
| GET | `attendance.photos.show` | `/attendance-photos/{photo}` | owner · team TL · Manager — stream punch photo |
| GET | `employee.report` | `/report?date=` | EMPLOYEE |
| POST | `employee.report.store` | `/report` | EMPLOYEE — `{ date, daily_report }` |
| GET | `team-leader.reports` | `/team-leader/reports?filter=` | TEAM_LEADER(team), MANAGER — `pending` \| `replied` \| `all` |
| GET | `team-leader.reports.show` | `/team-leader/reports/{attendance}` | TEAM_LEADER(team), MANAGER |
| POST | `team-leader.reports.respond` | `/team-leader/reports/{attendance}/respond` | TEAM_LEADER(team), MANAGER — `{ team_leader_response }` |
| PUT | `team-leader.reports.scores` | `/team-leader/reports/{attendance}/scores` | TEAM_LEADER(team), MANAGER — `{ performance_score, behavior_score, productivity_score, interest_return_score }` (0–100); `attendance_score` read-only auto |

**Clock-in rules enforced server-side (422 / session errors on violation):**
- Preferred shift = today’s SCHEDULED `shift_assignment` if still available; else `shift_type_default` / first remaining type.
- FT / Online ½ / Online full always offered **until already recorded for that date** (unique `user_id + date + shift_type`).
- Employee may switch FT↔Online even when TL assigned a base type.
- `EXTRA_SHIFT` / `OVERTIME` appear only with a SCHEDULED assignment for that date; rejected without one.
- One open record per user at a time.
- Same `shift_type` twice on the same day → 422 (`clock.errors.shift_already_recorded`).
- Overnight shifts allowed — `clock_out` may be next calendar day.
- Planned hours from matching assignment `expected_hours`, else 8 / 4 / 12 (`ShiftConfig`).
- Optional punch photos (`photos[]`): jpg/jpeg/png/webp, ≤50MB each, **no count limit** (in and/or out).

```json
// POST /attendance/clock-in → 201
{ "id": 512, "date": "2026-08-03", "shift_type": "ONLINE_FULL",
  "clock_in": "2026-08-03T10:02:00+02:00", "planned_hours": 12,
  "is_friday_work": false, "is_day_off_work": false, "is_official_holiday_work": false }
```

---

## 4. Permissions (إذن)

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | /permissions | ALL (self) | `{ "type": "LEAVE_PERMISSION", "request_date", "from_time", "to_time", "reason" }` |
| GET | /permissions/my | ALL | Own requests |
| GET | /permissions/pending | TEAM_LEADER, MANAGER | Approval queue |
| PUT | /permissions/{id}/approve | TEAM_LEADER(team), MANAGER | Sets `ACCEPTED` |
| PUT | /permissions/{id}/reject | TEAM_LEADER(team), MANAGER | Sets `REJECTED`, computes `penalty_amount` |
| PUT | /permissions/{id}/force | EMPLOYEE(self), TEAM_LEADER(team), MANAGER | `REJECTED → FORCED`, penalty × `double_penalty_multiplier` |

**Shipped web routes (T-2.7):**

| Method | Route name | Path | Role |
|--------|------------|------|------|
| GET | `employee.requests` | `/requests?tab=permissions\|vacations` | EMPLOYEE |
| POST | `employee.requests.permissions.store` | `/requests/permissions` | EMPLOYEE — leave/late create |
| GET | `team-leader.approvals` | `/team-leader/approvals` | TEAM_LEADER(team), MANAGER |
| POST | `team-leader.approvals.permissions.approve` | `/team-leader/approvals/permissions/{permission}/approve` | TEAM_LEADER(team), MANAGER → `ACCEPTED`, penalty 0 |
| POST | `team-leader.approvals.permissions.reject` | `/team-leader/approvals/permissions/{permission}/reject` | TEAM_LEADER(team), MANAGER → `REJECTED` + `penalty_amount` (minutes × rate from `config/penalties.php` until T-4.2); amount **not** shown in TL UI |
| POST | `employee.requests.permissions.force` | `/requests/permissions/{permission}/force` | EMPLOYEE(self) — `REJECTED` → `FORCED`, penalty × `double_penalty_multiplier`, `is_double_penalty=true` |
| POST | `team-leader.approvals.permissions.force` | `/team-leader/approvals/permissions/{permission}/force` | TEAM_LEADER(team) or MANAGER |
| POST | `team-leader.approvals.vacations.approve` | `/team-leader/approvals/vacations/{vacation}/approve` | TEAM_LEADER(team), MANAGER → `APPROVED`; pending→used on year balance |
| POST | `team-leader.approvals.vacations.reject` | `/team-leader/approvals/vacations/{vacation}/reject` | TEAM_LEADER(team), MANAGER → `REJECTED`; pending released |

```json
// PUT /permissions/44/force → 200
{ "id": 44, "status": "FORCED", "is_double_penalty": true, "penalty_amount": "108.00" }
```

---

## 5. Vacations & Balances

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | `employee.requests.vacations.store` | ALL (self) | `{ "type", "start_date", "end_date", "days_requested" }` — allow submit with 0 remaining; 422 on overlap |
| GET | `team-leader.approvals` | TL(team), MANAGER | Permissions + vacations queues; replace rest date visible |
| POST | `team-leader.approvals.vacations.approve` | TL(team), MANAGER | `PENDING` → `APPROVED`; deletes swapped rest day |
| POST | `team-leader.approvals.vacations.reject` | TL(team), MANAGER | `PENDING` → `REJECTED`; rest day stays available |

---

## 6. Shift Assignments (TL pre-assign)

Daily rotation **and** premium shifts. `shift_type` ∈ `FULL_TIME` | `ONLINE_HALF` | `ONLINE_FULL` | `EXTRA_SHIFT` | `OVERTIME`.

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | /shift-assignments | TEAM_LEADER(team), MANAGER | `{ "user_id", "date", "shift_type", "expected_start_time", "expected_end_time", "expected_hours", "multiplier"?, "notes"? }` |
| GET | /shift-assignments | TL(team), MANAGER | Filter `date`; paginated. Employees: not exposed in web UI yet |
| PUT/POST | /shift-assignments/{id}/cancel | TL(team of assignee), MANAGER | Sets `CANCELLED` (only if `SCHEDULED`) |

**Shipped web routes (T-2.14):**

| Method | Route name | Path | Role |
|--------|------------|------|------|
| GET | `team-leader.shifts` | `/team-leader/shifts?date=` | TEAM_LEADER, MANAGER |
| POST | `team-leader.shifts.store` | `/team-leader/shifts` | TEAM_LEADER, MANAGER |
| POST | `team-leader.shifts.cancel` | `/team-leader/shifts/{shiftAssignment}/cancel` | TEAM_LEADER(team), MANAGER |

**Rules:**
- TL may assign only employees with `team_leader_id = self`. Manager: any active employee.
- Duplicate SCHEDULED for same `user_id + date + shift_type` → 422.
- Default multiplier: `1.0` (base types), `1.5` (EXTRA / OT) if omitted.
- EMPLOYEE → 403 on all shift-assignment write/list UI routes.
- Clock screen prefers today’s SCHEDULED assignment; employee may still choose another unused FT/Online type.

---

## 7. Evaluations

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| GET | /evaluations/my | ALL | Monthly evaluations list |
| POST | /evaluations | TEAM_LEADER(team), MANAGER | `{ "user_id", "month", "year", "evaluation_text" }` — numeric scores default from daily avgs |
| GET | /evaluations?user_id=&year= | TL(team), MANAGER | Team evaluations; `year` full set powers the annual report average |
| PUT | /evaluations/{id} | TL(owner), MANAGER | Edit text (and optional score overrides) before month lock |

**Web (shipped T-3.3):**

| Method | Name | Path | Role |
|--------|------|------|------|
| GET | `team-leader.evaluations` | `/team-leader/evaluations?month=&year=` | TEAM_LEADER(team), MANAGER |
| GET | `team-leader.evaluations.show` | `/team-leader/evaluations/{employee}?month=&year=` | TEAM_LEADER(team), MANAGER |
| GET | `employee.evaluation` | `/evaluation?month=&year=` | EMPLOYEE, TEAM_LEADER, MANAGER (self) — read-only saved monthly evaluation |

> On save, server sets `performance_score`, `behavior_score`, `productivity_score`, `interest_return_score`, `attendance_score` from **AVG of that month's daily attendance scores**, then `total_score = sum(5)/5`. TL mainly submits **monthly `evaluation_text`**.

---

## 8. Payroll (MANAGER + employee self — TEAM_LEADER fully blocked, 403)

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| POST | /payroll/generate | MANAGER | `{ "month": 6, "year": 2026, "team_leader_id": null }` → queued job, creates DRAFTs |
| GET | /payroll | MANAGER | List, filters `month`, `year`, `status` |
| GET | /payroll/{id} | MANAGER, EMPLOYEE(self, APPROVED/PAID only) | Full breakdown |
| PUT | /payroll/{id} | MANAGER | Edit DRAFT (counts, manual lines) |
| POST | /payroll/{id}/adjustments | MANAGER | `{ "type": "SALES_COMMISSION", "amount": "250.00", "note": "..." }` |
| DELETE | /payroll/{id}/adjustments/{adjId} | MANAGER | DRAFT only |
| PUT | /payroll/{id}/approve | MANAGER | `DRAFT → APPROVED` (locks record, visible to employee) |
| PUT | /payroll/{id}/mark-paid | MANAGER | `APPROVED → PAID` |
| GET | /payroll/{id}/export.pdf | MANAGER, EMPLOYEE(self) | Payslip PDF |
| GET | /payroll/export.xlsx?month=&year= | MANAGER | Bulk Excel export (queued, chunked) |

**Payslip response shape (matches docs/PAYROLL_CALCULATION.md):**
```json
{
  "id": 91, "user_id": 7, "month": 5, "year": 2026, "currency": "EGP",
  "counts": { "full_time_days": 26, "rest_days": 4, "online_half_days": 0,
              "online_full_days": 0, "official_holiday_work_days": 1,
              "excused_absence_days": 1, "unexcused_absence_days": 1,
              "hours_deduction": 1.45, "late_minutes_total": 27 },
  "earnings": { "full_time_pay": "3333.33", "official_holiday_bonus": "166.67",
                "online_half_pay": "0.00", "online_full_pay": "0.00",
                "overtime_pay": "0.00", "friday_bonus": "0.00", "day_off_bonus": "0.00" },
  "deductions": { "excused_absence_penalty": "166.67", "unexcused_absence_penalty": "166.67",
                  "late_penalty": "0.00", "hours_deduction_amount": "0.00",
                  "advance_deductions": "1192.00" },
  "adjustments": [ { "type": "ADVANCE", "amount": "100.00" },
                   { "type": "ADVANCE", "amount": "67.00" },
                   { "type": "ADVANCE", "amount": "1000.00" },
                   { "type": "ADVANCE", "amount": "25.00" } ],
  "gross_pay": "3500.00", "total_deductions": "692.00", "net_pay": "2808.00",
  "status": "APPROVED"
}
```

---

## 9. Advances (سلف)

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| GET/POST | /advances | MANAGER | List / create `{ "user_id", "amount", "date_given", "deduction_amount_monthly" }` |
| PUT | /advances/{id} | MANAGER | Update while `ACTIVE` |
| GET | /advances/my | EMPLOYEE | Own advances + remaining |

Payroll generation auto-pulls `deduction_amount_monthly` from `ACTIVE` advances and decrements `remaining_amount` on approval.

---

## 10. Configuration (MANAGER only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST/PUT/DELETE | /config/penalty-rules | Per-currency penalty rates (unique per `type`+`currency`) |
| GET/POST/DELETE | /config/official-holidays | Holiday calendar `{ "date", "name_ar", "name_en" }` |

---

## 11. Reports (MANAGER)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /reports/annual/{user_id}?year= | Contract info, per-month: vacation balance, penalties, bonuses, deductions, evaluation; `evaluation_year_average` |
| GET | /reports/contracts-expiring?days=60 | Contract expiry watchlist |
| GET | /reports/attendance-summary?from=&to= | Company-wide attendance % |

---

## Role Access Summary

| Resource | MANAGER | TEAM_LEADER | EMPLOYEE |
|----------|---------|-------------|----------|
| Employees & rates | full | team (no rates/payroll fields) | self (no rates of others) |
| Attendance | all | team | self |
| Permissions/Vacations approve | all | team only | — |
| Vacation credit | ✅ | team only | — |
| Shift assign | ✅ | team only | — |
| Evaluations submit | ✅ | team only | view self |
| **Payroll** | **full** | **403 everywhere** | self, APPROVED/PAID only |
| Config & reports | ✅ | — | — |

## Conventions

- All mutating endpoints run inside DB transactions; payroll generation runs in a queued, chunked job (`database` queue driver — Hostinger constraint).
- Authorization lives in Laravel Policies (`PayrollPolicy` hard-blocks TEAM_LEADER) — never inline role checks in controllers.
- Validation via Form Requests; Arabic + English error messages (`lang/ar`, `lang/en`).

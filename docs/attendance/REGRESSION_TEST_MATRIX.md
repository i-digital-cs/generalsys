# Attendance regression test matrix

Canonical automated coverage: `tests/Unit/Attendance/ForensicAttendanceRegressionTest.php` + existing Gate suites.

| # | Scenario | Expected | Covered by |
|---|---|---|---|
| 1 | Normal same-day shift | In/out same date; no auto | AttendanceServiceTest / ClockTest |
| 2 | Overnight true window | End next day; open until end | Forensic + ShiftWindowResolverTest |
| 3 | Two shifts same day | Two rows different types | Forensic Rahma + DualDefaultShiftsTest |
| 4 | Adjacent shifts | Preference switches unused | DualDefaultShiftsTest |
| 5 | Overlapping / mid-break | Segments on same type | Forensic multi-segment + MultiPunchTest |
| 6 | Missing check-in | N/A (self-service) | — |
| 7 | Missing checkout | `clock_out` null until real close | Forensic |
| 8 | Duplicate punch | Second open clock-in rejected | Forensic + AttendanceServiceTest |
| 9 | Out-of-order supervisor close | Bump +1m; force_closed_by set | Forensic |
| 10 | Delayed sync | N/A (no device import) | — |
| 11 | Identical in/out timestamps | Supervisor bump path | Forensic |
| 12 | Same timestamps different employees | Independent rows | Forensic |
| 13 | Shift settings after attendance | Planned via live resolver on recalc | existing planned-hours tests |
| 14 | Recalculate multiple times | Idempotent | Forensic |
| 15 | Concurrent import | N/A | — |
| 16 | Timezone / midnight | Africa/Cairo; overnight addDay | Forensic overnight matrix |
| 17 | Grace 15 / 16 | Threshold not deduction | Forensic + GracePolicyTest |
| 18 | Historical payroll immutability | APPROVED net_pay unchanged | Forensic + GraceEffectiveDateTest |
| 19 | API/UI consistency | Punch-range badges | Feature history/clock (manual UI gate) |
| 20 | Overnight then next shift | Stale at expectedEnd only | Forensic Amir stale |
| — | **Reda incident** | No fabricate; real out works | Forensic |
| — | **Amir comparison** | Manual out preserved | Forensic |
| — | **Rahma multi-shift** | Prefer HALF; separate row | Forensic |

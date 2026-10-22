# Regression test results (TEST) — RC1

**Executed:** 2026-10-22  
**Branch:** `release/attendance-forensic-rc1`

## Command

```bash
php artisan test --filter='AttendanceServiceTest|DualDefaultShiftsTest|AttendanceGrace|ShiftWindow|ClockTest|AttendanceForceClockOut|AttendanceHistory|AttendanceMultiPunch|AttendanceTimeBreakdown|ForensicAttendanceRegressionTest|AttendanceGracePolicy|ClockCta|Geofence|AttendanceSchema|AttendancePhoto|AttendanceGap|HoursComparison|ClockLocation|PayrollLateMinutes'
```

## Result

```text
Tests:    180 passed (825 assertions)
Duration: ~6.5s
```

Includes:

- ForensicAttendanceRegressionTest: 14/14  
- Stale night+day window leaves checkout missing  
- Dual-default preferred evening = unused type  
- Grace 15/16 + payroll immutability suites  

## Acceptance checks (automated + code)

| Criterion | Evidence |
|---|---|
| No fabricated verified checkout | Forensic Reda + updated AttendanceServiceTest |
| Correct shift selection | Forensic Rahma + DualDefaultShiftsTest |
| Overnight dates | Forensic overnight matrix + ShiftWindowResolverTest |
| Multiple shifts independent | Forensic Rahma two rows |
| Multi-segment still works | Forensic + AttendanceMultiPunchTest |
| Grace unchanged | AttendanceGracePolicyTest + EffectiveDateTest |
| Payroll stable | Forensic APPROVED net_pay + GraceEffectiveDateTest |
| UI labels sources | `<x-attendance-punch-range>` + flags (Blade; history/dashboard/clock) |

**Automated tests: PASS**

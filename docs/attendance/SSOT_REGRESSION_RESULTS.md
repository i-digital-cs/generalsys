# SSOT regression results

**Environment:** TEST (local PHPUnit)  
**LIVE deploy:** NOT performed  
**Date:** 2026-10-22  
**Full audit:** `docs/attendance/SSOT_FINAL_ARCHITECTURE_AUDIT.md`

## Commands & results

```bash
# RC1 release inventory (unchanged set)
php artisan test --filter='AttendanceServiceTest|DualDefaultShiftsTest|AttendanceGrace|ShiftWindow|ClockTest|AttendanceForceClockOut|AttendanceHistory|AttendanceMultiPunch|AttendanceTimeBreakdown|ForensicAttendanceRegressionTest|AttendanceGracePolicy|ClockCta|Geofence|AttendanceSchema|AttendancePhoto|AttendanceGap|HoursComparison|ClockLocation|PayrollLateMinutes'
# → 180 passed (828 assertions)

# RC1 + SSOT + Portal
php artisan test --filter='…above…|AttendanceCalculationEngine|AttendanceSsotArchitecture|CrossScreenAttendanceParity|PortalStatsServiceTest'
# → 198 passed (908 assertions)

# Substring filter (narrower; not full inventory)
php artisan test --filter='Attendance'
# → 178 passed (767 assertions)
```

## Verdict

**CONDITIONAL** — TEST architecture/parity APPROVED; LIVE BLOCKED pending owner approval.

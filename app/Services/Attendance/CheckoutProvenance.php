<?php

namespace App\Services\Attendance;

use App\Models\Attendance;

/**
 * How an attendance checkout was produced (SSOT display / parity).
 * Missing is distinct from numeric zero duration.
 *
 * Semantics: docs/attendance/CHECKOUT_PROVENANCE_RULES.md
 */
final class CheckoutProvenance
{
    public const OPEN = 'OPEN';

    public const EMPLOYEE = 'EMPLOYEE';

    public const SUPERVISOR = 'SUPERVISOR';

    public const AUTO_STALE = 'AUTO_STALE';

    public static function forAttendance(Attendance $attendance): string
    {
        if ($attendance->clock_out === null || $attendance->hasOpenSegment()) {
            return self::OPEN;
        }
        if ($attendance->force_closed_by !== null) {
            return self::SUPERVISOR;
        }
        if ((bool) $attendance->is_auto_clock_out) {
            return self::AUTO_STALE;
        }

        return self::EMPLOYEE;
    }

    public static function isVerifiedEmployeeCheckout(string $provenance): bool
    {
        return $provenance === self::EMPLOYEE;
    }

    /**
     * Duration derived from this checkout may be shown as verified worked time.
     * Supervisor closes are recorded but not employee-verified; auto closes are unverified.
     */
    public static function durationIsVerified(string $provenance): bool
    {
        return $provenance === self::EMPLOYEE;
    }

    public static function durationIsRecordedOnly(string $provenance): bool
    {
        return in_array($provenance, [self::AUTO_STALE, self::SUPERVISOR], true);
    }

    public static function labelKey(string $provenance): string
    {
        return match ($provenance) {
            self::OPEN => 'attendance.punch.missing_checkout',
            self::EMPLOYEE => 'attendance.punch.employee_verified',
            self::SUPERVISOR => 'attendance.punch.supervisor_closed',
            self::AUTO_STALE => 'attendance.punch.auto_closed',
            default => 'attendance.punch.auto_closed',
        };
    }
}

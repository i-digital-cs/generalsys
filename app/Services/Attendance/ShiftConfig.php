<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;

/**
 * Planned-hours profiles as data (PAYROLL_CALCULATION.md §1 / §7) —
 * EXTRA_SHIFT / OVERTIME come from the TL assignment, not this map.
 */
class ShiftConfig
{
    /** @var array<string, float> */
    public const BASE_PLANNED_HOURS = [
        Attendance::SHIFT_FULL_TIME => 8.0,
        Attendance::SHIFT_ONLINE_HALF => 4.0,
        Attendance::SHIFT_ONLINE_FULL => 12.0,
    ];

    public function plannedHours(string $shiftType, ?ShiftAssignment $assignment = null): float
    {
        if (in_array($shiftType, [Attendance::SHIFT_EXTRA, Attendance::SHIFT_OVERTIME], true)) {
            return (float) ($assignment?->expected_hours ?? 0);
        }

        if (! array_key_exists($shiftType, self::BASE_PLANNED_HOURS)) {
            return 0.0;
        }

        return self::BASE_PLANNED_HOURS[$shiftType];
    }
}

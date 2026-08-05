<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;

/**
 * Planned-hours profiles as data (PAYROLL_CALCULATION.md §1 / §7).
 * When a TL assignment exists for the chosen type, use its expected_hours.
 */
class ShiftConfig
{
    /** @var array<string, float> */
    public const BASE_PLANNED_HOURS = [
        Attendance::SHIFT_FULL_TIME => 8.0,
        Attendance::SHIFT_ONLINE_HALF => 4.0,
        Attendance::SHIFT_ONLINE_FULL => 12.0,
    ];

    /**
     * Default work windows [start, end] per shift type — Excel RADWA sheet
     * (PAYROLL_CALCULATION.md §1): FT 10→18 (8h), Online½ 06→10 (4h), Online full 10→22 (12h).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const DEFAULT_TIME_WINDOWS = [
        Attendance::SHIFT_FULL_TIME => ['10:00', '18:00'],
        Attendance::SHIFT_ONLINE_HALF => ['06:00', '10:00'],
        Attendance::SHIFT_ONLINE_FULL => ['10:00', '22:00'],
    ];

    public function plannedHours(string $shiftType, ?ShiftAssignment $assignment = null): float
    {
        if ($assignment !== null && $assignment->shift_type === $shiftType) {
            return (float) $assignment->expected_hours;
        }

        if (! array_key_exists($shiftType, self::BASE_PLANNED_HOURS)) {
            return 0.0;
        }

        return self::BASE_PLANNED_HOURS[$shiftType];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function defaultWindow(string $shiftType): ?array
    {
        return self::DEFAULT_TIME_WINDOWS[$shiftType] ?? null;
    }
}

<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;

/**
 * Planned-hours profiles as data (PAYROLL_CALCULATION.md §1 / §7).
 *
 * Single source of truth for “how many hours this person owes for a shift”:
 * TL assignment expected_hours → employee default profile window → type base.
 * `attendance.planned_hours` is only a write-through cache refreshed by recalculate.
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
     * Planned hours for clock-in / late / early: TL assignment → employee profile window → type default.
     */
    public function resolvePlannedHours(User $user, string $shiftType, ?ShiftAssignment $assignment = null): float
    {
        if ($assignment !== null && $assignment->shift_type === $shiftType) {
            return round((float) $assignment->expected_hours, 2);
        }

        return round($user->plannedHoursFor($shiftType), 2);
    }

    /**
     * Canonical planned hours for an attendance row (never trust a stale column alone).
     */
    public function resolveForAttendance(Attendance $attendance, ?ShiftAssignment $assignment = null): float
    {
        $user = $attendance->relationLoaded('user') ? $attendance->user : $attendance->user()->first();
        if ($user === null) {
            return round((float) ($attendance->getAttributes()['planned_hours'] ?? 0), 2);
        }

        if ($assignment === null) {
            $assignment = ShiftAssignment::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $attendance->date)
                ->where('shift_type', $attendance->shift_type)
                ->where('status', ShiftAssignment::STATUS_SCHEDULED)
                ->first();
        }

        return $this->resolvePlannedHours($user, $attendance->shift_type, $assignment);
    }

    /**
     * Raw worked minutes minus live planned minutes (positive = overtime candidate).
     */
    public function overtimeDeltaMinutes(Attendance $attendance, ?ShiftAssignment $assignment = null): int
    {
        $rawHours = (float) ($attendance->raw_actual_hours ?? $attendance->actual_hours ?? 0);
        $planned = $this->resolveForAttendance($attendance, $assignment);

        return (int) round($rawHours * 60) - (int) round($planned * 60);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function defaultWindow(string $shiftType): ?array
    {
        return self::DEFAULT_TIME_WINDOWS[$shiftType] ?? null;
    }
}

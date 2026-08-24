<?php

namespace App\Http\Requests\Employee;

use App\Models\Attendance;
use App\Models\User;

/**
 * Browser GPS for clock punches (T-7.2 / T-7.8).
 * Employees: required only for FULL_TIME.
 * Drivers: required on every punch (any shift).
 */
final class ClockLocationRules
{
    public static function isRequiredFor(?string $shiftType, ?User $user = null): bool
    {
        if ($user?->requiresPunchLocation()) {
            return true;
        }

        return $shiftType === Attendance::SHIFT_FULL_TIME;
    }

    /** Whether being near the office is expected; outside geofence is flagged for these shifts. */
    public static function expectsOfficeProximity(?string $shiftType, ?User $user = null): bool
    {
        return self::isRequiredFor($shiftType, $user);
    }

    /**
     * Shift types that toggle the GPS block on the clock form (non-driver).
     *
     * @return list<string>
     */
    public static function requiredShiftTypes(?User $user = null): array
    {
        if ($user?->requiresPunchLocation()) {
            return [];
        }

        return [Attendance::SHIFT_FULL_TIME];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesForShift(?string $shiftType, ?User $user = null): array
    {
        if (self::isRequiredFor($shiftType, $user)) {
            return [
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            ];
        }

        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        return self::rulesForShift(Attendance::SHIFT_FULL_TIME, $user);
    }
}

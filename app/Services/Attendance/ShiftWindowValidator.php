<?php

namespace App\Services\Attendance;

use Carbon\Carbon;

/**
 * Ensure start/end windows match planned hours (R9).
 * Blocks typos like FULL_TIME 09:00→01:00 (16h) when planned is 8h.
 * Employee default shifts derive planned hours from the window span (8/10/12h FULL_TIME).
 */
class ShiftWindowValidator
{
    /** Same slack as AttendanceService::alignEndWithPlannedHours. */
    public const TOLERANCE_HOURS = 1.0;

    public const MIN_EMPLOYEE_WINDOW_HOURS = 1.0;

    public const MAX_EMPLOYEE_WINDOW_HOURS = 16.0;

    public function __construct(
        private readonly ShiftConfig $shifts,
    ) {}

    public function windowHours(string $start, string $end): float
    {
        $from = Carbon::parse('2000-01-01 '.$this->normalizeTime($start));
        $to = Carbon::parse('2000-01-01 '.$this->normalizeTime($end));
        if ($to->lte($from)) {
            $to->addDay();
        }

        return round($from->floatDiffInHours($to), 2);
    }

    public function plannedHoursForType(string $shiftType, ?float $plannedOverride = null): float
    {
        if ($plannedOverride !== null) {
            return round($plannedOverride, 2);
        }

        return $this->shifts->plannedHours($shiftType);
    }

    public function isCompatible(
        string $shiftType,
        string $start,
        string $end,
        ?float $plannedOverride = null,
    ): bool {
        $planned = $this->plannedHoursForType($shiftType, $plannedOverride);
        if ($planned <= 0) {
            return true;
        }

        return $this->windowHours($start, $end) <= $planned + self::TOLERANCE_HOURS;
    }

    public function isEmployeeWindowAllowed(string $start, string $end): bool
    {
        $window = $this->windowHours($start, $end);

        return $window >= self::MIN_EMPLOYEE_WINDOW_HOURS
            && $window <= self::MAX_EMPLOYEE_WINDOW_HOURS;
    }

    public function suggestedEnd(string $start, float $plannedHours): string
    {
        $from = Carbon::parse('2000-01-01 '.$this->normalizeTime($start));

        return $from->copy()
            ->addMinutes((int) round(max(0, $plannedHours) * 60))
            ->format('H:i');
    }

    public function errorMessage(
        string $shiftType,
        string $start,
        string $end,
        ?float $plannedOverride = null,
    ): string {
        $planned = $this->plannedHoursForType($shiftType, $plannedOverride);
        $window = $this->windowHours($start, $end);
        $suggested = $this->suggestedEnd($start, $planned);

        return __('employees.validation.shift_window_mismatch', [
            'shift' => __('clock.shifts.'.$shiftType),
            'start' => $this->normalizeTime($start),
            'end' => $this->normalizeTime($end),
            'window' => number_format($window, 2),
            'planned' => number_format($planned, 2),
            'suggested' => $suggested,
        ]);
    }

    public function employeeWindowRangeMessage(string $start, string $end): string
    {
        $window = $this->windowHours($start, $end);

        return __('employees.validation.shift_window_out_of_range', [
            'start' => $this->normalizeTime($start),
            'end' => $this->normalizeTime($end),
            'window' => number_format($window, 2),
            'min' => number_format(self::MIN_EMPLOYEE_WINDOW_HOURS, 0),
            'max' => number_format(self::MAX_EMPLOYEE_WINDOW_HOURS, 0),
        ]);
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time) !== 1) {
            return $time;
        }

        return Carbon::parse('2000-01-01 '.$time)->format('H:i');
    }
}

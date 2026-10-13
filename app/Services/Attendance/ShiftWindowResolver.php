<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Canonical expected start/end windows for a workday + shift type.
 *
 * Assignment window → employee profile / ShiftConfig defaults → align end to planned hours.
 * Used by clock orchestration, late/early math, and UI schedule previews.
 */
final class ShiftWindowResolver
{
    public function __construct(
        private readonly ShiftConfig $shifts,
    ) {}

    /**
     * Expected clock-in for a user/shift on the calendar day of $at.
     */
    public function expectedStartFor(
        User $user,
        string $shiftType,
        ?CarbonInterface $at = null,
        ?ShiftAssignment $assignment = null,
    ): ?Carbon {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        $assignment ??= $this->scheduledAssignment($user, $shiftType, $date);

        $draft = new Attendance([
            'date' => $date,
            'shift_type' => $shiftType,
        ]);

        return $this->expectedStart($user, $draft, $assignment);
    }

    /**
     * Expected clock-out for a user/shift on the calendar day of $at.
     */
    public function expectedEndFor(
        User $user,
        string $shiftType,
        ?CarbonInterface $at = null,
        ?ShiftAssignment $assignment = null,
    ): ?Carbon {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        $assignment ??= $this->scheduledAssignment($user, $shiftType, $date);

        $draft = new Attendance([
            'date' => $date,
            'shift_type' => $shiftType,
        ]);

        return $this->expectedEnd($user, $draft, $assignment);
    }

    public function expectedEndForAttendance(Attendance $attendance, ?ShiftAssignment $assignment = null): ?Carbon
    {
        $user = $attendance->relationLoaded('user')
            ? $attendance->user
            : $attendance->user()->first();
        if ($user === null) {
            return null;
        }

        $assignment ??= $this->scheduledAssignment(
            $user,
            $attendance->shift_type,
            $attendance->date->toDateString(),
        );

        return $this->expectedEnd($user, $attendance, $assignment);
    }

    public function expectedStart(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
    ): ?Carbon {
        $date = $attendance->date->toDateString();

        if ($assignment !== null) {
            return Carbon::parse($date.' '.$assignment->expected_start_time);
        }

        $window = $this->windowForShift($user, $attendance->shift_type);
        if ($window === null) {
            return null;
        }

        return Carbon::parse($date.' '.$window[0]);
    }

    public function expectedEnd(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
    ): ?Carbon {
        $date = $attendance->date->copy();
        $start = null;
        $end = null;

        if ($assignment !== null) {
            $start = Carbon::parse($date->toDateString().' '.$assignment->expected_start_time);
            $end = Carbon::parse($date->toDateString().' '.$assignment->expected_end_time);
            if ($end->lte($start)) {
                $end->addDay();
            }
        } else {
            $window = $this->windowForShift($user, $attendance->shift_type);
            if ($window === null) {
                return null;
            }

            $start = Carbon::parse($date->toDateString().' '.$window[0]);
            $end = Carbon::parse($date->toDateString().' '.$window[1]);
            if ($end->lte($start)) {
                $end->addDay();
            }
        }

        return $this->alignEndWithPlannedHours(
            $start,
            $end,
            $this->shifts->resolvePlannedHours($user, $attendance->shift_type, $assignment),
        );
    }

    /**
     * Prefer the employee’s default window for that shift type; else ShiftConfig;
     * Full-time may fall back to profile work_start/end span.
     *
     * @return array{0: string, 1: string}|null
     */
    public function windowForShift(User $user, string $shiftType): ?array
    {
        $profileWindow = $user->defaultWindowFor($shiftType);
        if ($profileWindow !== null) {
            return $profileWindow;
        }

        $configWindow = $this->shifts->defaultWindow($shiftType);

        if (in_array($shiftType, [
            Attendance::SHIFT_ONLINE_HALF,
            Attendance::SHIFT_ONLINE_FULL,
        ], true)) {
            return $configWindow;
        }

        if ($user->work_start_time !== null && $user->work_end_time !== null) {
            return [(string) $user->work_start_time, (string) $user->work_end_time];
        }

        return $configWindow;
    }

    /**
     * When the profile window is much longer than planned hours (e.g. FT 09:00→01:00 = 16h
     * vs planned 8h), early-leave / display end = start + planned — not the far overnight end.
     * True overnight shifts whose window matches planned (22:00→06:00 = 8h) keep the window end.
     */
    public function alignEndWithPlannedHours(Carbon $start, Carbon $windowEnd, float $plannedHours): Carbon
    {
        if ($plannedHours <= 0) {
            return $windowEnd;
        }

        $windowHours = round($start->floatDiffInHours($windowEnd), 2);
        if ($windowHours <= $plannedHours + 1.0) {
            return $windowEnd;
        }

        return $start->copy()->addMinutes((int) round($plannedHours * 60));
    }

    private function scheduledAssignment(User $user, string $shiftType, string $date): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->first();
    }
}

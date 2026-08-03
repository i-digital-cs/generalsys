<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Clock-in / clock-out + day flags (Gate 2.1–2.2).
 * Overnight: real datetimes only — no Excel +12h hack (PAYROLL_CALCULATION.md §3).
 */
class AttendanceService
{
    public function __construct(private readonly ShiftConfig $shifts) {}

    public function clockIn(User $user, string $shiftType, ?CarbonInterface $at = null): Attendance
    {
        $this->assertKnownShiftType($shiftType);

        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        if ($this->openAttendance($user) !== null) {
            throw AttendanceException::openAttendanceExists();
        }

        if ($this->hasAttendanceForShift($user, $date, $shiftType)) {
            throw AttendanceException::shiftAlreadyRecorded();
        }

        $assignment = $this->resolveAssignment($user, $shiftType, $date);

        return DB::transaction(function () use ($user, $shiftType, $at, $date, $assignment) {
            return Attendance::create([
                'user_id' => $user->id,
                'date' => $date,
                'shift_type' => $shiftType,
                'is_friday_work' => $at->isFriday(),
                'is_day_off_work' => $this->isRestDay($user, $date),
                'is_official_holiday_work' => $this->isOfficialHoliday($date),
                'assigned_by_tl_id' => $assignment?->assigned_by_tl_id,
                'clock_in' => $at,
                'planned_hours' => $this->shifts->plannedHours($shiftType, $assignment),
                'actual_hours' => 0,
                'status' => Attendance::STATUS_PRESENT,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
            ]);
        });
    }

    public function clockOut(User $user, ?CarbonInterface $at = null): Attendance
    {
        $at = Carbon::parse($at ?? now());
        $attendance = $this->openAttendance($user);

        if ($attendance === null) {
            throw AttendanceException::noOpenAttendance();
        }

        return DB::transaction(function () use ($user, $attendance, $at) {
            $assignment = $this->resolveAssignment(
                $user,
                $attendance->shift_type,
                $attendance->date->toDateString(),
            );

            $actualHours = $this->computeWorkedHours($attendance->clock_in, $at);
            $lateMinutes = $this->computeLateMinutes($user, $attendance, $assignment);
            $earlyMinutes = $this->computeEarlyDepartureMinutes($user, $attendance, $assignment, $at);
            $planned = (float) $attendance->planned_hours;

            $attendance->update([
                'clock_out' => $at,
                'actual_hours' => $actualHours,
                'late_minutes' => $lateMinutes,
                'early_departure_minutes' => $earlyMinutes,
                'status' => $this->resolveStatus($lateMinutes, $earlyMinutes),
                'attendance_score' => $this->attendanceScore($actualHours, $planned),
            ]);

            if ($assignment !== null && $assignment->status === ShiftAssignment::STATUS_SCHEDULED) {
                $assignment->update(['status' => ShiftAssignment::STATUS_COMPLETED]);
            }

            return $attendance->refresh();
        });
    }

    public function computeWorkedHours(CarbonInterface $clockIn, CarbonInterface $clockOut): float
    {
        $minutes = Carbon::parse($clockIn)->diffInMinutes(Carbon::parse($clockOut), false);

        return round(max(0, $minutes) / 60, 2);
    }

    public function openAttendance(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->latest('id')
            ->first();
    }

    /**
     * Shift types the employee may pick on the clock screen for $at's date.
     * FT / Online always (employee may switch); EXTRA / OT only when assigned.
     * Types already recorded for that date are excluded (unique user+date+shift).
     *
     * @return list<string>
     */
    public function availableShiftTypes(User $user, ?CarbonInterface $at = null): array
    {
        $date = Carbon::parse($at ?? now())->toDateString();

        $types = ShiftAssignment::BASE_TYPES;

        $premium = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->whereIn('shift_type', ShiftAssignment::PREMIUM_TYPES)
            ->pluck('shift_type')
            ->all();

        $used = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->pluck('shift_type')
            ->all();

        return array_values(array_diff(array_unique([...$types, ...$premium]), $used));
    }

    /**
     * Preferred clock selection: today’s SCHEDULED assignment, else profile default.
     * Falls back to the first still-available type when the preferred one was already used.
     */
    public function preferredShiftType(User $user, ?CarbonInterface $at = null): string
    {
        $available = $this->availableShiftTypes($user, $at);

        if ($available === []) {
            return Attendance::SHIFT_FULL_TIME;
        }

        $assignment = $this->todaysPrimaryAssignment($user, $at);

        if ($assignment !== null && in_array($assignment->shift_type, $available, true)) {
            return $assignment->shift_type;
        }

        $default = $user->shift_type_default;

        if (in_array($default, $available, true)) {
            return $default;
        }

        return $available[0];
    }

    public function hasAttendanceForShift(User $user, string $date, string $shiftType): bool
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->exists();
    }

    public function todaysPrimaryAssignment(User $user, ?CarbonInterface $at = null): ?ShiftAssignment
    {
        $date = Carbon::parse($at ?? now())->toDateString();

        $priority = [
            Attendance::SHIFT_FULL_TIME,
            Attendance::SHIFT_ONLINE_HALF,
            Attendance::SHIFT_ONLINE_FULL,
            Attendance::SHIFT_EXTRA,
            Attendance::SHIFT_OVERTIME,
        ];

        $assignments = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->get();

        return $assignments
            ->sortBy(function (ShiftAssignment $row) use ($priority) {
                $pos = array_search($row->shift_type, $priority, true);

                return $pos === false ? 99 : $pos;
            })
            ->first();
    }

    private function resolveAssignment(User $user, string $shiftType, string $date): ?ShiftAssignment
    {
        $assignment = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->first();

        $needsAssignment = in_array($shiftType, ShiftAssignment::PREMIUM_TYPES, true);

        if ($needsAssignment && $assignment === null) {
            throw AttendanceException::shiftAssignmentRequired($shiftType);
        }

        return $assignment;
    }

    private function computeLateMinutes(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
    ): int {
        $expectedStart = $this->expectedStart($user, $attendance, $assignment);

        if ($expectedStart === null || $attendance->clock_in === null) {
            return 0;
        }

        $late = $expectedStart->diffInMinutes($attendance->clock_in, false);

        return (int) max(0, $late);
    }

    private function computeEarlyDepartureMinutes(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
        CarbonInterface $clockOut,
    ): int {
        $expectedEnd = $this->expectedEnd($user, $attendance, $assignment);

        if ($expectedEnd === null) {
            return 0;
        }

        // Signed minutes from expectedEnd → clockOut; negative means left early.
        $delta = $expectedEnd->diffInMinutes(Carbon::parse($clockOut), false);

        return (int) max(0, -$delta);
    }

    private function expectedStart(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
    ): ?Carbon {
        $date = $attendance->date->toDateString();

        if ($assignment !== null) {
            return Carbon::parse($date.' '.$assignment->expected_start_time);
        }

        if ($user->work_start_time === null) {
            return null;
        }

        return Carbon::parse($date.' '.$user->work_start_time);
    }

    private function expectedEnd(
        User $user,
        Attendance $attendance,
        ?ShiftAssignment $assignment,
    ): ?Carbon {
        $date = $attendance->date->copy();

        if ($assignment !== null) {
            $end = Carbon::parse($date->toDateString().' '.$assignment->expected_end_time);
            $start = Carbon::parse($date->toDateString().' '.$assignment->expected_start_time);

            return $end->lte($start) ? $end->addDay() : $end;
        }

        if ($user->work_end_time === null || $user->work_start_time === null) {
            return null;
        }

        $end = Carbon::parse($date->toDateString().' '.$user->work_end_time);
        $start = Carbon::parse($date->toDateString().' '.$user->work_start_time);

        return $end->lte($start) ? $end->addDay() : $end;
    }

    private function resolveStatus(int $lateMinutes, int $earlyMinutes): string
    {
        if ($lateMinutes > 0) {
            return Attendance::STATUS_LATE;
        }

        if ($earlyMinutes > 0) {
            return Attendance::STATUS_EARLY_LEAVE;
        }

        return Attendance::STATUS_PRESENT;
    }

    private function attendanceScore(float $actualHours, float $plannedHours): float
    {
        if ($plannedHours <= 0) {
            return 0;
        }

        return round(min(100, ($actualHours / $plannedHours) * 100), 2);
    }

    private function isRestDay(User $user, string $date): bool
    {
        return RestDay::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->exists();
    }

    private function isOfficialHoliday(string $date): bool
    {
        return OfficialHoliday::query()->whereDate('date', $date)->exists();
    }

    private function assertKnownShiftType(string $shiftType): void
    {
        $known = [
            Attendance::SHIFT_FULL_TIME,
            Attendance::SHIFT_ONLINE_HALF,
            Attendance::SHIFT_ONLINE_FULL,
            Attendance::SHIFT_EXTRA,
            Attendance::SHIFT_OVERTIME,
        ];

        if (! in_array($shiftType, $known, true)) {
            throw AttendanceException::invalidShiftType($shiftType);
        }
    }
}

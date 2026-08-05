<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Employees\RestDayService;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Clock-in / clock-out + day flags (Gate 2.1–2.2).
 * Overnight: real datetimes only — no Excel +12h hack (PAYROLL_CALCULATION.md §3).
 */
class AttendanceService
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly RestDayService $restDays,
        private readonly DailyReportService $reports,
        private readonly AppNotificationService $notifications,
    ) {}

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

        if (! in_array($shiftType, $this->plannedAllowedTypes($user, $date), true)) {
            throw AttendanceException::planMismatch();
        }

        // Enforcement: a previous day with a missing daily report blocks
        // clocking in until the report is submitted (same-day extra/extra
        // punches are unaffected — their date is not before today's).
        $unreported = $this->reports->latestUnreportedClosedDay($user);
        if ($unreported !== null && $unreported->date->toDateString() < $date) {
            throw AttendanceException::dailyReportRequired();
        }

        $assignment = $this->resolveAssignment($user, $shiftType, $date);

        return DB::transaction(function () use ($user, $shiftType, $at, $date, $assignment) {
            $draft = new Attendance([
                'date' => $date,
                'clock_in' => $at,
                'shift_type' => $shiftType,
            ]);
            $lateMinutes = $this->computeLateMinutes($user, $draft, $assignment);

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
                'status' => $lateMinutes > 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT,
                'late_minutes' => $lateMinutes,
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

        return $this->finalizeClockOut($attendance, $at, automatic: false);
    }

    /**
     * Force-close open punches after expected end + grace (default 60 minutes).
     * Clock-out timestamp = expected_end + grace (counts as worked). Marked automatic.
     *
     * @return int number of rows closed
     */
    public function autoClockOutOverdue(?CarbonInterface $now = null): int
    {
        $now = Carbon::parse($now ?? now());
        $grace = max(0, (int) config('attendance.auto_clock_out_grace_minutes', 60));
        $closed = 0;

        Attendance::query()
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->where('is_auto_clock_out', false)
            ->with('user')
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($now, $grace, &$closed) {
                foreach ($rows as $attendance) {
                    if ($this->tryAutoClockOut($attendance, $now, $grace)) {
                        $closed++;
                    }
                }
            });

        return $closed;
    }

    private function tryAutoClockOut(Attendance $attendance, Carbon $now, int $graceMinutes): bool
    {
        $user = $attendance->user;
        if ($user === null) {
            return false;
        }

        $assignment = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $attendance->date->toDateString())
            ->where('shift_type', $attendance->shift_type)
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->first();

        $expectedEnd = $this->expectedEnd($user, $attendance, $assignment);
        if ($expectedEnd === null) {
            return false;
        }

        $forceAt = $expectedEnd->copy()->addMinutes($graceMinutes);
        if ($now->lt($forceAt)) {
            return false;
        }

        $closed = $this->finalizeClockOut($attendance, $forceAt, automatic: true);
        $this->notifications->attendanceAutoClockOut($closed);

        return true;
    }

    private function finalizeClockOut(
        Attendance $attendance,
        CarbonInterface $at,
        bool $automatic,
    ): Attendance {
        $user = $attendance->user;
        if ($user === null) {
            throw AttendanceException::noOpenAttendance();
        }

        return DB::transaction(function () use ($user, $attendance, $at, $automatic) {
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
                'is_auto_clock_out' => $automatic,
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
     * Phase 6: must match day’s plan (base assignment); EXTRA/OT only if assigned.
     * No plan → profile default only. Types already recorded that day are excluded.
     *
     * @return list<string>
     */
    public function availableShiftTypes(User $user, ?CarbonInterface $at = null): array
    {
        $date = Carbon::parse($at ?? now())->toDateString();
        $allowed = $this->plannedAllowedTypes($user, $date);

        $used = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->pluck('shift_type')
            ->all();

        return array_values(array_diff($allowed, $used));
    }

    /**
     * Types allowed by today’s roster (ignores already-used punches).
     *
     * @return list<string>
     */
    public function plannedAllowedTypes(User $user, string $date): array
    {
        $scheduled = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->pluck('shift_type')
            ->all();

        $baseScheduled = array_values(array_intersect($scheduled, ShiftAssignment::BASE_TYPES));
        $premiumScheduled = array_values(array_intersect($scheduled, ShiftAssignment::PREMIUM_TYPES));

        if ($baseScheduled !== []) {
            return array_values(array_unique([...$baseScheduled, ...$premiumScheduled]));
        }

        $default = $user->shift_type_default ?: Attendance::SHIFT_FULL_TIME;
        if (! in_array($default, ShiftAssignment::BASE_TYPES, true)) {
            $default = Attendance::SHIFT_FULL_TIME;
        }

        return array_values(array_unique([$default, ...$premiumScheduled]));
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

    /**
     * Expected clock-in for today (assignment window, else profile work_start_time).
     */
    public function expectedStartFor(
        User $user,
        ?string $shiftType = null,
        ?CarbonInterface $at = null,
    ): ?Carbon {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();
        $shiftType ??= $this->preferredShiftType($user, $at);

        $assignment = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->first();

        $draft = new Attendance([
            'date' => $date,
            'shift_type' => $shiftType,
        ]);

        return $this->expectedStart($user, $draft, $assignment);
    }

    /**
     * Expected clock-out for today (assignment window, else profile work_end_time).
     */
    public function expectedEndFor(
        User $user,
        ?string $shiftType = null,
        ?CarbonInterface $at = null,
    ): ?Carbon {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();
        $shiftType ??= $this->preferredShiftType($user, $at);

        $assignment = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->first();

        $draft = new Attendance([
            'date' => $date,
            'shift_type' => $shiftType,
        ]);

        return $this->expectedEnd($user, $draft, $assignment);
    }

    /**
     * Minutes late if the employee clocked in at $at (default: now).
     */
    public function previewLateMinutes(
        User $user,
        ?string $shiftType = null,
        ?CarbonInterface $at = null,
    ): int {
        $at = Carbon::parse($at ?? now());
        $expected = $this->expectedStartFor($user, $shiftType, $at);

        if ($expected === null) {
            return 0;
        }

        return (int) max(0, $expected->diffInMinutes($at, false));
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

        $window = $this->windowForShift($user, $attendance->shift_type);
        if ($window === null) {
            return null;
        }

        return Carbon::parse($date.' '.$window[0]);
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

        $window = $this->windowForShift($user, $attendance->shift_type);
        if ($window === null) {
            return null;
        }

        $start = Carbon::parse($date->toDateString().' '.$window[0]);
        $end = Carbon::parse($date->toDateString().' '.$window[1]);

        return $end->lte($start) ? $end->addDay() : $end;
    }

    /**
     * Online ½ / Online full use Excel fixed windows. Full-time (and others)
     * prefer the employee profile schedule (supports overnight 22:00→06:00).
     *
     * @return array{0: string, 1: string}|null
     */
    private function windowForShift(User $user, string $shiftType): ?array
    {
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
        return $this->restDays->isRestDate($user, $date);
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

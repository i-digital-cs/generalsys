<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\AttendanceSegment;
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
        private readonly GeofenceService $geofence,
        private readonly AttendanceTimeCalculator $timeCalculator,
        private readonly AttendanceGapService $gaps,
    ) {}

    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}|null  $location
     */
    public function clockIn(
        User $user,
        string $shiftType,
        ?CarbonInterface $at = null,
        ?array $location = null,
    ): Attendance {
        $this->assertKnownShiftType($shiftType);

        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();

        $this->closeStaleOpenBeforeShiftStart($user, $at, $shiftType);

        if ($this->openAttendance($user) !== null) {
            throw AttendanceException::openAttendanceExists();
        }

        if (! in_array($shiftType, $this->plannedAllowedTypes($user, $date), true)) {
            throw AttendanceException::planMismatch();
        }

        // Early / late / after-end clock-in is allowed (alerts via notifyOutsideShiftWindow).

        if ($user->mustSubmitDailyReports()) {
            $unreported = $this->reports->latestUnreportedClosedDay($user);
            if ($unreported !== null && $unreported->date->toDateString() < $date) {
                throw AttendanceException::dailyReportRequired();
            }
        }

        $assignment = $this->resolveAssignment($user, $shiftType, $date);

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', $shiftType)
            ->with('segments')
            ->first();

        $attendance = DB::transaction(function () use ($user, $shiftType, $at, $date, $assignment, $existing) {
            if ($existing !== null) {
                $lastSegment = $existing->segments->sortByDesc('sequence')->first();
                if ($lastSegment !== null && $lastSegment->clock_out !== null) {
                    $this->gaps->recordGap($existing, $lastSegment->clock_out, $at);
                }

                $sequence = ($lastSegment?->sequence ?? 0) + 1;
                $segment = AttendanceSegment::query()->create([
                    'attendance_id' => $existing->id,
                    'sequence' => $sequence,
                    'clock_in' => $at,
                ]);

                $this->timeCalculator->recalculate(
                    $existing->fresh(['segments', 'user']),
                    $this,
                    $assignment,
                );

                return [$existing->fresh(['segments', 'user']), $segment];
            }

            $draft = new Attendance([
                'date' => $date,
                'clock_in' => $at,
                'shift_type' => $shiftType,
            ]);
            $lateMinutes = $this->computeLateMinutes($user, $draft, $assignment);

            $attendance = Attendance::create([
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
                'raw_actual_hours' => 0,
                'status' => $lateMinutes > 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT,
                'late_minutes' => $lateMinutes,
                'early_departure_minutes' => 0,
            ]);

            $segment = AttendanceSegment::query()->create([
                'attendance_id' => $attendance->id,
                'sequence' => 1,
                'clock_in' => $at,
            ]);

            $this->timeCalculator->recalculate(
                $attendance->fresh(['segments', 'user']),
                $this,
                $assignment,
            );

            return [$attendance->fresh(['segments', 'user']), $segment];
        });

        [$attendance, $segment] = $attendance;
        $segment = $this->applySegmentLocation($segment, 'CLOCK_IN', $location);
        $this->timeCalculator->recalculate($attendance->fresh(['segments', 'user']), $this, $assignment);
        $attendance = $attendance->fresh(['segments', 'user']);
        $this->notifyOutsideShiftWindow($attendance, 'CLOCK_IN', $at);

        return $attendance;
    }

    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}|null  $location
     */
    public function clockOut(User $user, ?CarbonInterface $at = null, ?array $location = null): Attendance
    {
        $at = Carbon::parse($at ?? now());
        $attendance = $this->openAttendance($user);

        if ($attendance === null) {
            throw AttendanceException::noOpenAttendance();
        }

        return $this->finalizeClockOut($attendance, $at, automatic: false, location: $location);
    }

    /**
     * Auto clock-out disabled — employees must clock out themselves.
     * Historical `is_auto_clock_out` rows remain for display only.
     *
     * @return int number of rows closed
     */
    public function autoClockOutOverdue(?CarbonInterface $now = null): int
    {
        return 0;
    }

    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}|null  $location
     */
    private function finalizeClockOut(
        Attendance $attendance,
        CarbonInterface $at,
        bool $automatic,
        ?array $location = null,
    ): Attendance {
        $user = $attendance->user;
        if ($user === null) {
            throw AttendanceException::noOpenAttendance();
        }

        $segment = $attendance->segments()->whereNull('clock_out')->orderByDesc('sequence')->first();
        if ($segment === null) {
            throw AttendanceException::noOpenAttendance();
        }

        $closed = DB::transaction(function () use ($user, $attendance, $segment, $at) {
            $assignment = $this->resolveAssignment(
                $user,
                $attendance->shift_type,
                $attendance->date->toDateString(),
            );

            $segment->update(['clock_out' => $at]);

            return $this->timeCalculator->recalculate(
                $attendance->fresh(['segments', 'user']),
                $this,
                $assignment,
            );
        });

        if ($automatic) {
            $closed->update(['is_auto_clock_out' => true]);
            $this->notifications->attendanceAutoClockOut($closed->fresh(['user']));

            return $closed->refresh();
        }

        $segment = $segment->refresh();
        $segment = $this->applySegmentLocation($segment, 'CLOCK_OUT', $location);
        $closed = $this->timeCalculator->recalculate($closed->fresh(['segments', 'user']), $this);
        $this->notifyOutsideShiftWindow($closed, 'CLOCK_OUT', $at);

        return $closed;
    }

    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}|null  $location
     */
    private function applySegmentLocation(AttendanceSegment $segment, string $kind, ?array $location): AttendanceSegment
    {
        if ($location === null) {
            return $segment;
        }

        $lat = (float) $location['latitude'];
        $lng = (float) $location['longitude'];
        $accuracy = array_key_exists('accuracy', $location) && $location['accuracy'] !== null && $location['accuracy'] !== ''
            ? (int) round((float) $location['accuracy'])
            : null;

        $eval = $this->geofence->evaluate($lat, $lng);
        $prefix = $kind === 'CLOCK_OUT' ? 'clock_out' : 'clock_in';

        $segment->update([
            "{$prefix}_lat" => $lat,
            "{$prefix}_lng" => $lng,
            "{$prefix}_accuracy_m" => $accuracy,
            "{$prefix}_distance_m" => $eval['distance_m'],
            "{$prefix}_outside_geofence" => $eval['outside'],
        ]);

        $segment = $segment->refresh();
        $attendance = $segment->attendance;
        if ($attendance !== null && $eval['outside'] && $eval['distance_m'] !== null) {
            $office = $this->geofence->office();
            $this->notifications->attendanceGeofenceWarning(
                $attendance,
                $kind,
                $eval['distance_m'],
                (int) ($office['radius_m'] ?? config('attendance.location.radius_meters', 1000)),
            );
        }

        return $segment;
    }

    /**
     * Persist browser GPS + soft geofence flags; warn when outside office radius.
     *
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}|null  $location
     */
    private function applyPunchLocation(Attendance $attendance, string $kind, ?array $location): Attendance
    {
        if ($location === null) {
            return $attendance;
        }

        $lat = (float) $location['latitude'];
        $lng = (float) $location['longitude'];
        $accuracy = array_key_exists('accuracy', $location) && $location['accuracy'] !== null && $location['accuracy'] !== ''
            ? (int) round((float) $location['accuracy'])
            : null;

        $eval = $this->geofence->evaluate($lat, $lng);
        $prefix = $kind === 'CLOCK_OUT' ? 'clock_out' : 'clock_in';

        $attendance->update([
            "{$prefix}_lat" => $lat,
            "{$prefix}_lng" => $lng,
            "{$prefix}_accuracy_m" => $accuracy,
            "{$prefix}_distance_m" => $eval['distance_m'],
            "{$prefix}_outside_geofence" => $eval['outside'],
        ]);

        $attendance = $attendance->refresh();

        if ($eval['outside'] && $eval['distance_m'] !== null) {
            $office = $this->geofence->office();
            $this->notifications->attendanceGeofenceWarning(
                $attendance,
                $kind,
                $eval['distance_m'],
                (int) ($office['radius_m'] ?? config('attendance.location.radius_meters', 1000)),
            );
        }

        return $attendance;
    }

    public function computeWorkedHours(CarbonInterface $clockIn, CarbonInterface $clockOut): float
    {
        $minutes = Carbon::parse($clockIn)->diffInMinutes(Carbon::parse($clockOut), false);

        return round(max(0, $minutes) / 60, 2);
    }

    public function openSegment(User $user): ?AttendanceSegment
    {
        return AttendanceSegment::query()
            ->whereNull('clock_out')
            ->whereHas('attendance', fn ($q) => $q->where('user_id', $user->id))
            ->with('attendance')
            ->orderByDesc('id')
            ->first();
    }

    public function openAttendance(User $user): ?Attendance
    {
        return $this->openSegment($user)?->attendance;
    }

    /**
     * Close a forgotten cross-day open punch when starting today's shift (no cron).
     * Triggered on /clock load or clock-in attempt within N minutes of shift start.
     */
    public function closeStaleOpenBeforeShiftStart(
        User $user,
        ?CarbonInterface $at = null,
        ?string $shiftType = null,
    ): ?Attendance {
        $at = Carbon::parse($at ?? now());
        $shiftType ??= $this->preferredShiftType($user, $at);

        $segment = $this->openSegment($user);
        if ($segment === null) {
            return null;
        }

        $attendance = $segment->attendance;
        if ($attendance === null) {
            return null;
        }

        // Same calendar day — employee must clock out manually (multi-punch breaks).
        if ($attendance->date->toDateString() === $at->toDateString()) {
            return null;
        }

        $expectedStart = $this->expectedStartFor($user, $shiftType, $at);
        $leadMinutes = max(0, (int) config('attendance.force_close_before_shift_minutes', 60));

        if ($expectedStart !== null && $at->lt($expectedStart->copy()->subMinutes($leadMinutes))) {
            return null;
        }

        $closeAt = $this->resolveStaleForceCloseTime($attendance, $segment, $at);

        return $this->forceCloseStaleSegment($attendance, $closeAt);
    }

    /**
     * Shift types the employee may pick on the clock screen for $at's date.
     * Early / late / after-end allowed for any remaining planned type that day.
     *
     * @return list<string>
     */
    public function availableShiftTypes(User $user, ?CarbonInterface $at = null): array
    {
        $at = Carbon::parse($at ?? now());

        return $this->remainingPlannedTypes($user, $at->toDateString());
    }

    /**
     * Planned types not punched yet whose shift has already finished
     * (informational — clock-in still allowed with an outside-window alert).
     *
     * @return list<string>
     */
    public function closedUnrecordedShiftTypes(User $user, ?CarbonInterface $at = null): array
    {
        $at = Carbon::parse($at ?? now());
        $remaining = $this->remainingPlannedTypes($user, $at->toDateString());

        return array_values(array_filter(
            $remaining,
            fn (string $type) => $this->shiftWindowHasEnded($user, $type, $at),
        ));
    }

    /**
     * Planned types not punched yet that have not started (next shift still ahead).
     * Informational — early clock-in is still allowed.
     *
     * @return list<string>
     */
    public function upcomingUnrecordedShiftTypes(User $user, ?CarbonInterface $at = null): array
    {
        $at = Carbon::parse($at ?? now());
        $remaining = $this->remainingPlannedTypes($user, $at->toDateString());
        $upcoming = [];

        foreach ($remaining as $type) {
            if (! $this->shiftHasNotStarted($user, $type, $at)) {
                continue;
            }
            $start = $this->expectedStartFor($user, $type, $at);
            $upcoming[] = ['type' => $type, 'start' => $start];
        }

        usort($upcoming, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return array_values(array_map(fn (array $row) => $row['type'], $upcoming));
    }

    /**
     * @return list<string>
     */
    private function remainingPlannedTypes(User $user, string $date): array
    {
        return $this->plannedAllowedTypes($user, $date);
    }

    /**
     * Preferred clock selection: in-window shift first, else earliest start among remaining.
     */
    public function preferredShiftType(User $user, ?CarbonInterface $at = null): string
    {
        $at = Carbon::parse($at ?? now());
        $available = $this->availableShiftTypes($user, $at);

        if ($available === []) {
            return Attendance::SHIFT_FULL_TIME;
        }

        $inWindow = [];
        foreach ($available as $type) {
            if ($this->shiftHasNotStarted($user, $type, $at) || $this->shiftWindowHasEnded($user, $type, $at)) {
                continue;
            }
            $start = $this->expectedStartFor($user, $type, $at);
            $inWindow[] = ['type' => $type, 'start' => $start];
        }

        if ($inWindow !== []) {
            usort($inWindow, fn (array $a, array $b) => $a['start'] <=> $b['start']);

            return $inWindow[0]['type'];
        }

        $byStart = [];
        foreach ($available as $type) {
            $start = $this->expectedStartFor($user, $type, $at);
            $byStart[] = ['type' => $type, 'start' => $start ?? $at->copy()->addYears(10)];
        }
        usort($byStart, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $byStart[0]['type'];
    }

    /**
     * Whether $at falls inside the planned [start, end] window (inclusive edges).
     */
    public function shiftIsClockableNow(User $user, string $shiftType, ?CarbonInterface $at = null): bool
    {
        return true;
    }

    public function shiftHasNotStarted(User $user, string $shiftType, ?CarbonInterface $at = null): bool
    {
        $at = Carbon::parse($at ?? now());
        $start = $this->expectedStartFor($user, $shiftType, $at);

        return $start !== null && $at->lt($start);
    }

    public function shiftWindowHasEnded(User $user, string $shiftType, ?CarbonInterface $at = null): bool
    {
        $at = Carbon::parse($at ?? now());
        $end = $this->expectedEndFor($user, $shiftType, $at);

        return $end !== null && $at->gt($end);
    }

    /**
     * @return 'before_window'|'after_window'|null
     */
    public function outsideShiftWindowReason(User $user, string $shiftType, CarbonInterface $at): ?string
    {
        $at = Carbon::parse($at);
        if ($this->shiftHasNotStarted($user, $shiftType, $at)) {
            return 'before_window';
        }
        if ($this->shiftWindowHasEnded($user, $shiftType, $at)) {
            return 'after_window';
        }

        return null;
    }

    private function notifyOutsideShiftWindow(Attendance $attendance, string $kind, CarbonInterface $at): void
    {
        $user = $attendance->user;
        if ($user === null) {
            return;
        }

        $reason = $this->outsideShiftWindowReason($user, $attendance->shift_type, $at);
        if ($reason === null) {
            return;
        }

        $this->notifications->attendanceOutsideShiftWindow($attendance, $kind, $reason);
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
        $defaults = $user->defaultShiftTypes();

        // Multiple defaults apply every workday. Assigning one type overlays that
        // window — it must not hide the other defaults (T-7.3 / T-7.7).
        if (count($defaults) >= 2) {
            return array_values(array_unique([...$defaults, ...$baseScheduled, ...$premiumScheduled]));
        }

        if ($baseScheduled !== []) {
            return array_values(array_unique([...$baseScheduled, ...$premiumScheduled]));
        }

        return array_values(array_unique([...$defaults, ...$premiumScheduled]));
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

    public function expectedEndForAttendance(Attendance $attendance, ?ShiftAssignment $assignment = null): ?Carbon
    {
        $user = $attendance->user;
        if ($user === null) {
            return null;
        }

        $assignment ??= $this->resolveAssignment(
            $user,
            $attendance->shift_type,
            $attendance->date->toDateString(),
        );

        return $this->expectedEnd($user, $attendance, $assignment);
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
        $shiftType ??= $this->preferredShiftType($user, $at);

        // Resume after a break / already punched this type today — not a late first start.
        if ($this->hasAttendanceForShift($user, $at->toDateString(), $shiftType)) {
            return 0;
        }

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
            $this->shifts->plannedHours($attendance->shift_type, $assignment),
        );
    }

    /**
     * When the profile window is much longer than planned hours (e.g. FT 09:00→01:00 = 16h
     * vs planned 8h), early-leave / display end = start + planned — not the far overnight end.
     * True overnight shifts whose window matches planned (22:00→06:00 = 8h) keep the window end.
     */
    private function alignEndWithPlannedHours(Carbon $start, Carbon $windowEnd, float $plannedHours): Carbon
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

    /**
     * Prefer the employee’s default window for that shift type; else ShiftConfig;
     * Full-time may fall back to profile work_start/end span.
     *
     * @return array{0: string, 1: string}|null
     */
    private function windowForShift(User $user, string $shiftType): ?array
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

    private function resolveStaleForceCloseTime(
        Attendance $attendance,
        AttendanceSegment $segment,
        CarbonInterface $at,
    ): CarbonInterface {
        $expectedEnd = $this->expectedEndForAttendance($attendance);
        $closeAt = $expectedEnd ?? $segment->clock_in->copy()->addMinutes(
            (int) round((float) $attendance->planned_hours * 60),
        );

        if ($closeAt->gt($at)) {
            $closeAt = $at->copy();
        }

        if ($closeAt->lte($segment->clock_in)) {
            $closeAt = $segment->clock_in->copy()->addMinute();
        }

        return $closeAt;
    }

    private function forceCloseStaleSegment(Attendance $attendance, CarbonInterface $closeAt): Attendance
    {
        return $this->finalizeClockOut($attendance, $closeAt, automatic: true);
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

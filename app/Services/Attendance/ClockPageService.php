<?php

namespace App\Services\Attendance;

use App\Http\Requests\Employee\ClockLocationRules;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-model assembly for the employee clock screen (no writes except stale-close via AttendanceService).
 */
final class ClockPageService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DailyReportService $reports,
        private readonly ClockCtaColorService $ctaColors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(User $user): array
    {
        $staleClosed = $this->attendance->closeStaleOpenBeforeShiftStart($user);
        $open = $this->attendance->openAttendance($user);
        if ($open !== null) {
            $open->load(['photos', 'segments', 'gaps']);
        }

        $shiftTypes = $this->attendance->availableShiftTypes($user);
        $upcomingShiftTypes = $this->attendance->upcomingUnrecordedShiftTypes($user);
        $closedShiftTypes = $this->attendance->closedUnrecordedShiftTypes($user);
        $preferredShift = $this->attendance->preferredShiftType($user);
        if ($shiftTypes !== [] && ! in_array($preferredShift, $shiftTypes, true)) {
            $preferredShift = $shiftTypes[0];
        } elseif ($shiftTypes === [] && $upcomingShiftTypes !== []) {
            $preferredShift = $upcomingShiftTypes[0];
        }

        $scheduleShift = $open?->shift_type ?? $preferredShift;
        $showSchedulePreview = $open !== null || $shiftTypes !== [] || $upcomingShiftTypes !== [];
        $expectedStart = $showSchedulePreview
            ? $this->attendance->expectedStartFor($user, $scheduleShift)
            : null;
        $expectedEnd = $showSchedulePreview
            ? $this->attendance->expectedEndFor($user, $scheduleShift)
            : null;

        $today = now()->toDateString();

        return [
            'open' => $open,
            'openSegment' => $open !== null ? $this->attendance->openSegment($user) : null,
            'missingReportDate' => $user->mustSubmitDailyReports()
                ? $this->reports->latestUnreportedClosedDay($user)?->date->toDateString()
                : null,
            'shiftTypes' => $shiftTypes,
            'upcomingShiftTypes' => $upcomingShiftTypes,
            'closedShiftTypes' => ($shiftTypes === [] && $upcomingShiftTypes === [])
                ? $closedShiftTypes
                : [],
            'preferredShift' => $preferredShift,
            'scheduleShift' => $scheduleShift,
            'todaysAssignment' => $this->attendance->todaysPrimaryAssignment($user),
            'expectedStart' => $expectedStart,
            'expectedEnd' => $expectedEnd,
            'previewLateMinutes' => ($open === null && $shiftTypes !== [])
                ? $this->attendance->previewLateMinutes($user, $preferredShift)
                : 0,
            'lateMinutes' => $open !== null ? (int) $open->late_minutes : 0,
            'todayAutoClockOut' => $this->todayFlaggedAttendance($user, $today, auto: true),
            'todayForceClockOut' => $this->todayFlaggedAttendance($user, $today, auto: false),
            'todayAttendances' => $this->todayAttendances($user, $today),
            'staleClosed' => $staleClosed,
            'photoMaxMb' => (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024),
            'photosRequired' => $user->requiresPunchPhotos(),
            'fieldUploadsAllowed' => $user->requiresPunchPhotos() && $open !== null,
            'locationAlwaysRequired' => $user->requiresPunchLocation(),
            'locationRequiredShifts' => ClockLocationRules::requiredShiftTypes($user),
            'cta' => $this->ctaColors->buttonState($user, $open, $preferredShift, $shiftTypes),
            'ctaWindows' => $this->ctaColors->windows(),
        ];
    }

    /**
     * @return Collection<int, Attendance>
     */
    private function todayAttendances(User $user, string $today): Collection
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with(['segments', 'gaps'])
            ->orderBy('clock_in')
            ->get();
    }

    private function todayFlaggedAttendance(User $user, string $today, bool $auto): ?Attendance
    {
        $q = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->orderByDesc('id');

        if ($auto) {
            $q->where('is_auto_clock_out', true);
        } else {
            $q->whereNotNull('force_closed_by');
        }

        return $q->first();
    }
}

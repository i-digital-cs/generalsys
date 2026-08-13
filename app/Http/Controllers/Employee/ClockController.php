<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ClockInRequest;
use App\Http\Requests\Employee\ClockOutRequest;
use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Services\Attendance\AttendancePhotoService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\DailyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Employee clock screen (T-2.3 / T-6.6 / T-7.2) — thin: authorize → service → view/redirect.
 */
class ClockController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DailyReportService $reports,
        private readonly AttendancePhotoService $photos,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $open = $this->attendance->openAttendance($user);
        if ($open !== null) {
            $open->load(['photos']);
        }

        $shiftTypes = $this->attendance->availableShiftTypes($user);
        $preferredShift = $this->attendance->preferredShiftType($user);
        if ($shiftTypes !== [] && ! in_array($preferredShift, $shiftTypes, true)) {
            $preferredShift = $shiftTypes[0];
        }

        $scheduleShift = $open?->shift_type ?? $preferredShift;
        $showSchedulePreview = $open !== null || $shiftTypes !== [];
        $expectedStart = $showSchedulePreview
            ? $this->attendance->expectedStartFor($user, $scheduleShift)
            : null;
        $expectedEnd = $showSchedulePreview
            ? $this->attendance->expectedEndFor($user, $scheduleShift)
            : null;

        $todayAuto = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('is_auto_clock_out', true)
            ->orderByDesc('id')
            ->first();

        return view('employee.clock', [
            'open' => $open,
            'missingReportDate' => $this->reports->latestUnreportedClosedDay($user)?->date->toDateString(),
            'shiftTypes' => $shiftTypes,
            'preferredShift' => $preferredShift,
            'scheduleShift' => $scheduleShift,
            'todaysAssignment' => $this->attendance->todaysPrimaryAssignment($user),
            'expectedStart' => $expectedStart,
            'expectedEnd' => $expectedEnd,
            'previewLateMinutes' => ($open === null && $shiftTypes !== [])
                ? $this->attendance->previewLateMinutes($user, $preferredShift)
                : 0,
            'lateMinutes' => $open !== null ? (int) $open->late_minutes : 0,
            'todayAutoClockOut' => $todayAuto,
            'photoMaxMb' => (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024),
        ]);
    }

    public function clockIn(ClockInRequest $request): RedirectResponse
    {
        try {
            $attendance = $this->attendance->clockIn(
                $request->user(),
                $request->validated('shift_type'),
                location: $this->locationFrom($request),
            );
            $this->photos->storeMany(
                $attendance,
                AttendancePhoto::KIND_CLOCK_IN,
                $request->file('photos', []) ?? [],
            );
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['shift_type' => __($e->getMessage())]);
        }

        $redirect = redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.clocked_in'));

        if ($attendance->clock_in_outside_geofence) {
            $redirect->with('warning', __('clock.flash.outside_geofence', [
                'distance' => $attendance->clock_in_distance_m,
            ]));
        }

        return $redirect;
    }

    public function clockOut(ClockOutRequest $request): RedirectResponse
    {
        try {
            $attendance = $this->attendance->clockOut(
                $request->user(),
                location: $this->locationFrom($request),
            );
            $this->photos->storeMany(
                $attendance,
                AttendancePhoto::KIND_CLOCK_OUT,
                $request->file('photos', []) ?? [],
            );
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['clock_out' => __($e->getMessage())]);
        }

        $warning = $attendance->clock_out_outside_geofence
            ? __('clock.flash.outside_geofence', ['distance' => $attendance->clock_out_distance_m])
            : null;

        // After leaving, route the employee straight to their daily report
        // when the day's report is still missing.
        $date = $attendance->date->toDateString();
        $main = $this->reports->mainAttendanceForDate($request->user(), $date);

        if ($main !== null && blank($main->daily_report)) {
            $redirect = redirect()
                ->route('employee.report', ['date' => $date])
                ->with('success', __('clock.flash.clocked_out_report'));
        } else {
            $redirect = redirect()
                ->route('employee.clock')
                ->with('success', __('clock.flash.clocked_out'));
        }

        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: ?float}
     */
    private function locationFrom(ClockInRequest|ClockOutRequest $request): array
    {
        $validated = $request->validated();

        return [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'accuracy' => isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
        ];
    }
}

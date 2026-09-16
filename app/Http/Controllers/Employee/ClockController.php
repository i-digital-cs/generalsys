<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ClockInRequest;
use App\Http\Requests\Employee\ClockLocationRules;
use App\Http\Requests\Employee\ClockOutRequest;
use App\Http\Requests\Employee\StoreFieldPhotoRequest;
use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\User;
use App\Services\Attendance\AttendancePhotoService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\ClockCtaColorService;
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
        private readonly ClockCtaColorService $ctaColors,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
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

        $todayAuto = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('is_auto_clock_out', true)
            ->orderByDesc('id')
            ->first();

        $todayAttendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->with(['segments', 'gaps'])
            ->orderBy('clock_in')
            ->get();

        return view('employee.clock', [
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
            'todayAutoClockOut' => $todayAuto,
            'todayForceClockOut' => Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
                ->whereNotNull('force_closed_by')
                ->orderByDesc('id')
                ->first(),
            'todayAttendances' => $todayAttendances,
            'staleClosed' => $staleClosed,
            'photoMaxMb' => (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024),
            'photosRequired' => $user->requiresPunchPhotos(),
            'fieldUploadsAllowed' => $user->requiresPunchPhotos() && $open !== null,
            'locationAlwaysRequired' => $user->requiresPunchLocation(),
            'locationRequiredShifts' => ClockLocationRules::requiredShiftTypes($user),
            'cta' => $this->ctaColors->buttonState($user, $open, $preferredShift, $shiftTypes),
            'ctaWindows' => $this->ctaColors->windows(),
        ]);
    }

    public function storeFieldPhotos(StoreFieldPhotoRequest $request): RedirectResponse
    {
        try {
            $stored = $this->photos->storeFieldUploads(
                $request->user(),
                $request->file('photos', []) ?? [],
                $request->validated('photo_descriptions', []) ?? [],
                $this->locationFrom($request) ?? [],
            );
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['photos' => __($e->getMessage())]);
        }

        $redirect = redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.field_uploaded', ['count' => count($stored)]));

        $outside = collect($stored)->first(fn ($p) => $p->outside_geofence);
        if ($outside !== null) {
            $redirect->with('warning', __('clock.flash.outside_geofence', [
                'distance' => $outside->distance_m,
            ]));
        }

        return $redirect;
    }

    public function clockIn(ClockInRequest $request): RedirectResponse
    {
        $user = $request->user();
        $shiftType = $request->validated('shift_type');
        $staleClosed = $this->attendance->closeStaleOpenBeforeShiftStart($user, null, $shiftType);

        try {
            $location = $this->locationFrom($request);
            $attendance = $this->attendance->clockIn(
                $user,
                $shiftType,
                location: $location,
            );
            $this->photos->storeMany(
                $attendance,
                AttendancePhoto::KIND_CLOCK_IN,
                $request->file('photos', []) ?? [],
                $request->validated('photo_descriptions', []) ?? [],
                $this->attendance->openSegment($request->user()),
                $location,
            );
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['shift_type' => __($e->getMessage())]);
        }

        $redirect = redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.clocked_in'));

        if ($staleClosed !== null) {
            $redirect->with('warning', __('clock.flash.stale_force_closed', [
                'date' => $staleClosed->date->translatedFormat('d M Y'),
                'time' => $staleClosed->clock_out?->timezone(config('app.timezone'))->format('H:i'),
            ]));
        }

        if ($attendance->clock_in_outside_geofence) {
            $redirect->with('warning', __('clock.flash.outside_geofence', [
                'distance' => $attendance->clock_in_distance_m,
            ]));
        }

        $outsideReason = $this->attendance->outsideShiftWindowReason(
            $request->user(),
            $attendance->shift_type,
            $attendance->clock_in,
        );
        if ($outsideReason !== null) {
            $redirect->with('warning', __('clock.flash.outside_shift_window_'.$outsideReason, [
                'punch' => __('clock.clock_in'),
            ]));
        }

        return $redirect;
    }

    public function clockOut(ClockOutRequest $request): RedirectResponse
    {
        try {
            $location = $this->locationFrom($request);
            $attendance = $this->attendance->clockOut(
                $request->user(),
                location: $location,
            );
            $this->photos->storeMany(
                $attendance,
                AttendancePhoto::KIND_CLOCK_OUT,
                $request->file('photos', []) ?? [],
                $request->validated('photo_descriptions', []) ?? [],
                $attendance->segments()->orderByDesc('sequence')->first(),
                $location,
            );
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['clock_out' => __($e->getMessage())]);
        }

        $warning = $attendance->clock_out_outside_geofence
            ? __('clock.flash.outside_geofence', ['distance' => $attendance->clock_out_distance_m])
            : null;

        $outsideReason = $this->attendance->outsideShiftWindowReason(
            $request->user(),
            $attendance->shift_type,
            $attendance->clock_out,
        );
        if ($outsideReason !== null) {
            $warning = __('clock.flash.outside_shift_window_'.$outsideReason, [
                'punch' => __('clock.clock_out'),
            ]);
        }

        // After leaving, route straight to the daily report when still missing.
        $date = $attendance->date->toDateString();
        $main = $request->user()->mustSubmitDailyReports()
            ? $this->reports->mainAttendanceForDate($request->user(), $date)
            : null;

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

        $reminder = $this->reportReminderPayload($request->user(), $attendance, $main);
        if ($reminder !== null) {
            $redirect->with('report_reminder', $reminder);
        }

        return $redirect;
    }

    /**
     * @return array{date: string, late_minutes: int, early_minutes: int, report_missing: bool}|null
     */
    private function reportReminderPayload(User $user, Attendance $attendance, ?Attendance $main): ?array
    {
        if (! $user->mustSubmitDailyReports()) {
            return null;
        }

        $date = $attendance->date->toDateString();
        $main ??= $this->reports->mainAttendanceForDate($user, $date);

        return [
            'date' => $date,
            'late_minutes' => (int) $attendance->late_minutes,
            'early_minutes' => (int) $attendance->early_departure_minutes,
            'report_missing' => $main === null || blank($main->daily_report),
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: ?float}|null
     */
    private function locationFrom(ClockInRequest|ClockOutRequest|StoreFieldPhotoRequest $request): ?array
    {
        $validated = $request->validated();
        if (! isset($validated['latitude'], $validated['longitude'])
            || $validated['latitude'] === null
            || $validated['longitude'] === null
            || $validated['latitude'] === ''
            || $validated['longitude'] === '') {
            return null;
        }

        return [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'accuracy' => isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
        ];
    }
}

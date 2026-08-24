@props(['attendance'])

@php
    use App\Services\Attendance\AttendanceTimeReviewService;

    $actor = auth()->user();
    $open = $attendance->clock_out === null && $attendance->hasOpenSegment();
    if ($open) {
        $attendance->loadMissing('user');
    }
    $may = $open
        && $actor !== null
        && app(AttendanceTimeReviewService::class)->actorMayReview($actor, $attendance);
    $confirm = __('clock.end_shift.confirm', [
        'name' => $attendance->user?->localizedName() ?? '',
        'time' => now()->timezone(config('app.timezone'))->format('H:i'),
    ]);
@endphp

@if ($may)
    <form method="POST" action="{{ route('team-leader.attendance.force-clock-out', $attendance) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-warning"
                onclick="return confirm(@js($confirm))">
            {{ __('clock.end_shift.button') }}
        </button>
    </form>
@endif

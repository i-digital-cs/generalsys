@props([
    'attendance',
    'summary' => null,
])

@php
    use App\Services\Attendance\AttendanceService;
    use App\Services\Attendance\AttendanceTimeCalculator;

    $summary = $summary ?? app(AttendanceTimeCalculator::class)->summarize(
        $attendance,
        app(AttendanceService::class),
    );

    $windowBadge = static function (?string $position): string {
        return match ($position) {
            'before_window' => 'text-bg-warning text-dark',
            'after_window' => 'text-bg-warning text-dark',
            'inside_window' => 'text-bg-success',
            default => 'text-bg-secondary',
        };
    };

    $windowLabel = static function (?string $position): ?string {
        if ($position === null) {
            return null;
        }

        return __('attendance_review.window.'.$position);
    };
@endphp

<div {{ $attributes->merge(['class' => 'attendance-shift-timeline']) }}>
    <h4 class="h6 mb-3">{{ __('attendance_review.window.title') }}</h4>

    @if ($summary->windowStart !== null && $summary->windowEnd !== null)
        <div class="attendance-shift-timeline__window mb-3">
            <span class="small text-body-secondary d-block mb-1">
                {{ __('attendance_review.window.planned') }}
            </span>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-primary num-ltr">
                    {{ $summary->windowStart }} – {{ $summary->windowEnd }}
                </span>
                <span class="badge text-bg-light border text-dark num-ltr">
                    {{ number_format($summary->plannedHours, 2) }}h
                </span>
            </div>
        </div>
    @endif

    <ul class="list-unstyled mb-3 small">
        @if ($summary->clockInAt !== null)
            <li class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="text-body-secondary">{{ __('attendance_review.window.clock_in') }}</span>
                <span class="fw-semibold num-ltr">{{ $summary->clockInAt }}</span>
                @if ($windowLabel($summary->clockInWindowPosition))
                    <span @class(['badge', $windowBadge($summary->clockInWindowPosition)])>
                        {{ $windowLabel($summary->clockInWindowPosition) }}
                    </span>
                @endif
            </li>
        @endif
        @if ($summary->clockOutAt !== null)
            <li class="d-flex flex-wrap align-items-center gap-2 mb-0">
                <span class="text-body-secondary">{{ __('attendance_review.window.clock_out') }}</span>
                <span class="fw-semibold num-ltr">{{ $summary->clockOutAt }}</span>
                @if ($windowLabel($summary->clockOutWindowPosition))
                    <span @class(['badge', $windowBadge($summary->clockOutWindowPosition)])>
                        {{ $windowLabel($summary->clockOutWindowPosition) }}
                    </span>
                @endif
            </li>
        @elseif ($summary->open)
            <li class="text-body-secondary mb-0">{{ __('clock.status_open') }}</li>
        @endif
    </ul>

    <div class="attendance-shift-timeline__balance border-top pt-3">
        <span class="small text-body-secondary d-block mb-2">
            {{ __('attendance_review.hours_balance.title') }}
        </span>
        <dl class="row mb-0 small">
            <dt class="col-sm-6">{{ __('attendance_review.hours_balance.worked') }}</dt>
            <dd class="col-sm-6 num-ltr mb-2">{{ number_format($summary->systemRawHours, 2) }}h</dd>
            <dt class="col-sm-6">{{ __('attendance_review.hours_balance.planned') }}</dt>
            <dd class="col-sm-6 num-ltr mb-2">{{ number_format($summary->plannedHours, 2) }}h</dd>
            <dt class="col-sm-6">{{ __('attendance_review.hours_balance.difference') }}</dt>
            <dd class="col-sm-6 mb-0">
                @if ($summary->overtimeDeltaMinutes > 0)
                    <span class="badge text-bg-success num-ltr">
                        {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
                    </span>
                @elseif ($summary->hasUndertime())
                    <span class="badge text-bg-danger num-ltr">
                        {{ __('attendance_review.hours_balance.short', ['minutes' => $summary->undertimeMinutes()]) }}
                    </span>
                @else
                    <span class="badge text-bg-success">
                        {{ __('attendance_review.hours_balance.on_plan') }}
                    </span>
                @endif
            </dd>
        </dl>
    </div>
</div>

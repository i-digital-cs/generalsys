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
            <li class="d-flex flex-wrap align-items-center gap-2 mb-0">
                <span class="text-body-secondary">{{ __('attendance_review.window.clock_out') }}</span>
                <span class="badge text-bg-warning text-dark">{{ __('attendance_review.window.still_open') }}</span>
            </li>
        @endif
    </ul>

    <div class="attendance-shift-timeline__balance border-top">
        <span class="small text-body-secondary d-block mb-2">
            {{ __('attendance_review.hours_balance.title') }}
        </span>
        <dl class="row mb-0 small g-1">
            <dt class="col-6">{{ __('attendance_review.hours_balance.worked') }}</dt>
            <dd class="col-6 num-ltr hp-value mb-1 text-end">{{ number_format($summary->systemRawHours, 2) }}h</dd>
            <dt class="col-6">{{ __('attendance_review.hours_balance.planned') }}</dt>
            <dd class="col-6 num-ltr hp-value mb-1 text-end">{{ number_format($summary->plannedHours, 2) }}h</dd>
            <dt class="col-6">{{ __('attendance_review.hours_balance.difference') }}</dt>
            <dd class="col-6 mb-0 text-end">
                @php $balanceClass = $summary->hoursBalanceClassification(); @endphp
                <span @class([
                    'badge',
                    'text-bg-danger' => $balanceClass === 'deficit',
                    'text-bg-success' => $balanceClass === 'surplus',
                    'text-bg-secondary' => $balanceClass === 'balanced',
                ])>
                    {{ __('attendance_review.hours_balance.classification.'.$balanceClass) }}
                    @if ($summary->undertimeMinutes() > 0)
                        · {{ __('attendance_review.hours_balance.short', ['minutes' => $summary->undertimeMinutes()]) }}
                    @elseif ($summary->extraWorkedMinutes() > 0)
                        · {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
                    @endif
                </span>
            </dd>
        </dl>
    </div>
</div>

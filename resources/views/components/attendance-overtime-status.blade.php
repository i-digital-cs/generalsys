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
    $tl = $summary->overtimeTlRecommendation;
    $admin = $summary->overtimeAdminDecision;
@endphp

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1 align-items-center']) }}>
    @if ($admin !== 'PENDING' && $tl === 'RECOMMEND')
        <span @class([
            'badge',
            'text-bg-success' => $admin === 'APPROVED',
            'text-bg-danger' => $admin === 'REJECTED',
        ])>
            {{ __('attendance_review.breakdown.ot_admin.'.$admin) }}
        </span>
        @if ($summary->approvedOvertimeMinutes > 0)
            <span class="badge text-bg-success">
                +{{ $summary->approvedOvertimeMinutes }} {{ __('attendance_review.overtime.minutes_abbr') }}
            </span>
        @endif
    @elseif ($tl === 'NO_OVERTIME')
        <span class="badge text-bg-secondary">{{ __('attendance_review.breakdown.ot_status.NO_OVERTIME') }}</span>
    @elseif ($tl === 'RECOMMEND')
        <span class="badge text-bg-info text-dark">{{ __('attendance_review.breakdown.ot_status.RECOMMEND') }}</span>
        <span class="badge text-bg-secondary">{{ __('attendance_review.overtime.pending_admin') }}</span>
    @elseif ($summary->needsTlOvertimeReview())
        <span class="badge text-bg-warning text-dark">{{ __('attendance_review.overtime.pending_tl') }}</span>
        @if ($summary->systemSuggestedOvertimeMinutes > 0)
            <span class="badge text-bg-light border text-dark num-ltr">
                {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->systemSuggestedOvertimeMinutes]) }}
            </span>
        @endif
    @elseif ($summary->hasUndertime())
        <span class="badge text-bg-danger num-ltr">
            {{ __('attendance_review.hours_balance.short', ['minutes' => $summary->undertimeMinutes()]) }}
        </span>
    @elseif ($summary->extraWorkedMinutes() > 0)
        <span class="badge text-bg-success num-ltr">
            {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
        </span>
    @endif
</div>

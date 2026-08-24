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
@endphp

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1']) }}>
    @if ($summary->systemRawHours > 0 || $summary->open)
        <span class="badge text-bg-light border text-dark">
            {{ __('attendance_review.breakdown.raw') }}:
            <span class="num-ltr">{{ number_format($summary->systemRawHours, 2) }}h</span>
        </span>
    @endif

    @if (! $summary->open && $summary->appliedCreditedHours > 0)
        <span class="badge text-bg-secondary">
            {{ __('attendance_review.breakdown.credited') }}:
            <span class="num-ltr">{{ number_format($summary->appliedCreditedHours, 2) }}h</span>
        </span>
    @endif

    @if ($summary->appliedLateMinutes > 0)
        <span class="badge text-bg-warning text-dark">
            {{ __('attendance_review.late_minutes') }}:
            <span class="num-ltr">{{ $summary->appliedLateMinutes }}</span>
        </span>
    @endif

    @if ($summary->appliedEarlyMinutes > 0)
        <span class="badge text-bg-info text-dark">
            {{ __('attendance_review.early_minutes') }}:
            <span class="num-ltr">{{ $summary->appliedEarlyMinutes }}</span>
        </span>
    @endif

    @if ($summary->hasGaps())
        <span class="badge text-bg-dark">
            {{ __('attendance_review.breakdown.gap') }}:
            <span class="num-ltr">{{ $summary->gapMinutesTotal }}</span>
        </span>
    @endif

    @if ($summary->hasUndertime())
        <span class="badge text-bg-danger">
            {{ __('attendance_review.hours_balance.short', ['minutes' => $summary->undertimeMinutes()]) }}
        </span>
    @elseif ($summary->extraWorkedMinutes() > 0)
        <span class="badge text-bg-success">
            {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
        </span>
    @endif

    @if ($summary->approvedOvertimeMinutes > 0)
        <span class="badge text-bg-success">
            {{ __('attendance_review.overtime.credited_cap') }}:
            <span class="num-ltr">{{ number_format($summary->creditedCapHours, 2) }}h</span>
        </span>
    @endif

    @if ($summary->recommendedOvertimeMinutes > 0 || $summary->overtimeTlRecommendation !== 'NONE')
        <span class="badge text-bg-primary">
            {{ __('attendance_review.overtime_minutes') }}:
            <span class="num-ltr">{{ $summary->recommendedOvertimeMinutes }}</span>
            @if ($summary->overtimeTlRecommendation !== 'NONE')
                · {{ __('attendance_review.breakdown.ot_status.'.$summary->overtimeTlRecommendation) }}
            @endif
            @if ($summary->overtimeAdminDecision !== 'PENDING')
                · {{ __('attendance_review.breakdown.ot_admin.'.$summary->overtimeAdminDecision) }}
            @endif
        </span>
    @endif

    @if ($summary->approvedOvertimeMinutes > 0)
        <span class="badge text-bg-success">
            {{ __('attendance_review.breakdown.ot_approved') }}:
            <span class="num-ltr">+{{ $summary->approvedOvertimeMinutes }}</span>
        </span>
    @endif
</div>

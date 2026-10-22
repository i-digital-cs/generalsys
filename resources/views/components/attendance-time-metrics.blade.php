@props([
    'attendance',
    'summary' => null,
])

@php
    use App\Services\Attendance\AttendanceCalculationEngine;

    $calc = $calc ?? app(AttendanceCalculationEngine::class)->forAttendance($attendance);
    $summary = $summary ?? $calc->summary;
@endphp

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1']) }}>
    <x-attendance-session-integrity :result="$calc" />

    @if ($summary->systemRawHours > 0 || $summary->open)
        <span class="badge text-bg-light border text-dark">
            {{ __('attendance_review.breakdown.raw') }}:
            <span class="num-ltr">{{ number_format($summary->systemRawHours, 2) }}h</span>
            @if (! $calc->durationVerified() && ! $calc->isOpen())
                <span class="text-body-secondary">({{ __('attendance.integrity.recorded_hours_unverified') }})</span>
            @endif
        </span>
    @endif

    @if (! $summary->open && $summary->appliedCreditedHours > 0)
        <span class="badge text-bg-secondary">
            {{ __('attendance.integrity.credited_hours') }}:
            <span class="num-ltr">{{ number_format($summary->appliedCreditedHours, 2) }}h</span>
        </span>
    @endif

    @if ($calc->lateAgainstPlannedWindowMinutes() > 0)
        <span @class([
            'badge',
            'text-bg-warning text-dark' => $calc->lateDisplayTrustworthy(),
            'text-bg-danger' => ! $calc->lateDisplayTrustworthy(),
        ])>
            {{ $calc->lateDisplayTrustworthy()
                ? __('attendance_review.late_minutes')
                : __('attendance.integrity.late_vs_planned_window') }}:
            <span class="num-ltr">{{ $calc->lateAgainstPlannedWindowMinutes() }}</span>
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
            {{ __('attendance_review.hours_balance.classification.deficit') }}
            · {{ __('attendance_review.hours_balance.short', ['minutes' => $summary->undertimeMinutes()]) }}
        </span>
    @elseif ($summary->extraWorkedMinutes() > 0)
        <span class="badge text-bg-success">
            {{ __('attendance_review.hours_balance.classification.surplus') }}
            · {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
        </span>
    @elseif (! $summary->open)
        <span class="badge text-bg-secondary">
            {{ __('attendance_review.hours_balance.classification.balanced') }}
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

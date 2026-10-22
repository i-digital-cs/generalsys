@props([
    'attendance',
    'compact' => false,
    'review' => false,
    'formAction' => null,
    'showOvertimeSection' => true,
])

@php
    use App\Services\Attendance\AttendanceCalculationEngine;

    $summary = app(AttendanceCalculationEngine::class)->forAttendance($attendance)->summary;
    $formAction = $formAction ?? ($review ? route('team-leader.attendance.time', $attendance) : null);
@endphp

<div {{ $attributes->merge(['class' => $compact ? 'small' : '']) }}>
    @if (! $compact)
        <p class="fw-semibold mb-2">{{ __('attendance_review.comparison.title') }}</p>
    @endif

    @if (! $compact)
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0 attendance-compare-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('attendance_review.comparison.system') }}</th>
                    <th scope="col">{{ __('attendance_review.comparison.credited') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.planned_hours') }}</span>
                        <span class="num-ltr hp-value">{{ number_format($summary->plannedHours, 2) }}h</span>
                    </td>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.planned_hours') }}</span>
                        <span class="num-ltr hp-value">{{ number_format($summary->plannedHours, 2) }}h</span>
                        @if ($summary->approvedOvertimeMinutes > 0)
                            <span class="d-block small text-body-secondary num-ltr mt-1">
                                {{ __('attendance_review.overtime.credited_cap_hint', [
                                    'planned' => number_format($summary->plannedHours, 2),
                                    'minutes' => $summary->approvedOvertimeMinutes,
                                ]) }}
                            </span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.raw_hours') }}</span>
                        <span class="num-ltr hp-value">{{ number_format($summary->systemRawHours, 2) }}h</span>
                    </td>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.raw_hours') }}</span>
                        <span class="num-ltr hp-value">{{ number_format($summary->appliedRawHours, 2) }}h</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.credited_hours') }}</span>
                        <span class="num-ltr hp-value">
                            @if ($summary->open)
                                —
                            @else
                                {{ number_format($summary->systemCreditedHours, 2) }}h
                            @endif
                        </span>
                    </td>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.credited_hours') }}</span>
                        <span @class(['num-ltr', 'hp-value', 'text-primary' => $summary->creditedOverridden])>
                            @if ($summary->open)
                                —
                            @else
                                {{ number_format($summary->appliedCreditedHours, 2) }}h
                            @endif
                        </span>
                        @if ($summary->creditedOverridden)
                            <span class="badge text-bg-primary ms-1">{{ __('attendance_review.comparison.overridden') }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.late_minutes') }}</span>
                        <span class="num-ltr hp-value">{{ $summary->systemLateMinutes }}</span>
                    </td>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.late_minutes') }}</span>
                        <span @class(['num-ltr', 'hp-value', 'text-primary' => $summary->lateOverridden])>{{ $summary->appliedLateMinutes }}</span>
                        @if ($summary->lateOverridden)
                            <span class="badge text-bg-primary ms-1">{{ __('attendance_review.comparison.overridden') }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.early_minutes') }}</span>
                        <span class="num-ltr hp-value">{{ $summary->systemEarlyMinutes }}</span>
                    </td>
                    <td>
                        <span class="hp-metric-label">{{ __('attendance_review.early_minutes') }}</span>
                        <span class="num-ltr hp-value">{{ $summary->appliedEarlyMinutes }}</span>
                    </td>
                </tr>
            </tbody>
            </table>
        </div>

        @if ($summary->hasGaps() || ($showOvertimeSection && ($summary->extraWorkedMinutes() > 0 || $summary->recommendedOvertimeMinutes > 0 || $summary->overtimeTlRecommendation !== 'NONE')))
        <div @class(['mt-3', 'small' => $compact])>
            @if ($summary->hasGaps())
                <p class="fw-semibold mb-1">{{ __('attendance_review.gaps') }}</p>
                <ul class="list-unstyled mb-{{ ($showOvertimeSection && ($summary->extraWorkedMinutes() > 0 || $summary->recommendedOvertimeMinutes > 0 || $summary->overtimeTlRecommendation !== 'NONE')) ? 2 : 0 }}">
                    @foreach ($summary->gaps as $gap)
                        <li class="mb-1">
                            <span class="num-ltr">{{ __('attendance_gaps.interval', ['start' => $gap['start'] ?? '—', 'end' => $gap['end'] ?? '—']) }}</span>
                            · {{ __('attendance_gaps.minutes', ['count' => $gap['minutes']]) }}
                            @if ($gap['admin_decision'] !== \App\Models\AttendanceGap::ADMIN_PENDING)
                                · {{ __('attendance_gaps.status.'.$gap['admin_decision']) }}
                            @elseif ($gap['tl_recommendation'] !== \App\Models\AttendanceGap::TL_PENDING)
                                · {{ __('attendance_gaps.status.'.$gap['tl_recommendation']) }}
                            @else
                                · {{ __('attendance_gaps.pending_tl') }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($showOvertimeSection && ($summary->extraWorkedMinutes() > 0 || $summary->recommendedOvertimeMinutes > 0 || $summary->overtimeTlRecommendation !== 'NONE'))
                <p class="fw-semibold mb-1">{{ __('attendance_review.breakdown.overtime_title') }}</p>
                <x-attendance-overtime-status :attendance="$attendance" :summary="$summary" class="mb-2" />
                <dl class="row mb-0 small">
                    @if ($summary->extraWorkedMinutes() > 0)
                        <dt class="col-sm-6">{{ __('attendance_review.hours_balance.difference') }}</dt>
                        <dd class="col-sm-6 num-ltr">
                            {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
                        </dd>
                    @endif
                    @if ($summary->recommendedOvertimeMinutes > 0 || $summary->overtimeTlRecommendation !== 'NONE')
                        <dt class="col-sm-6">{{ __('attendance_review.overtime_minutes') }}</dt>
                        <dd class="col-sm-6 num-ltr">{{ $summary->recommendedOvertimeMinutes }}</dd>
                        <dt class="col-sm-6">{{ __('attendance_review.breakdown.ot_tl') }}</dt>
                        <dd class="col-sm-6">{{ __('attendance_review.breakdown.ot_status.'.$summary->overtimeTlRecommendation) }}</dd>
                        @if ($summary->overtimeAdminDecision !== 'PENDING')
                            <dt class="col-sm-6">{{ __('attendance_review.breakdown.ot_admin_label') }}</dt>
                            <dd class="col-sm-6">{{ __('attendance_review.breakdown.ot_admin.'.$summary->overtimeAdminDecision) }}</dd>
                        @endif
                    @endif
                    @if ($summary->approvedOvertimeMinutes > 0)
                        <dt class="col-sm-6">{{ __('attendance_review.breakdown.ot_approved') }}</dt>
                        <dd class="col-sm-6 num-ltr">+{{ $summary->approvedOvertimeMinutes }}</dd>
                    @endif
                </dl>
            @endif
        </div>
        @endif
    @else
        <x-attendance-time-metrics :attendance="$attendance" :summary="$summary" />
    @endif

    @if (! $compact && ! $summary->open && ! $summary->hasOverrides() && ! $review)
        <p class="small text-body-secondary mb-0 mt-2">{{ __('attendance_review.comparison.no_override') }}</p>
    @endif

    @if ($review && $formAction && $attendance->clock_out !== null)
        <form method="POST" action="{{ $formAction }}" class="mt-3">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="credited_hours_{{ $attendance->id }}">{{ __('attendance_review.credited_hours') }}</label>
                    <input type="number" step="0.01" min="0" max="24" name="credited_hours"
                           id="credited_hours_{{ $attendance->id }}"
                           value="{{ $attendance->credited_hours ?? $attendance->actual_hours }}"
                           class="form-control">
                    <div class="form-text">{{ __('attendance_review.comparison.system_hint', ['hours' => number_format($summary->systemCreditedHours, 2)]) }}</div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="adjusted_late_{{ $attendance->id }}">{{ __('attendance_review.adjusted_late') }}</label>
                    <input type="number" min="0" max="720" name="adjusted_late_minutes"
                           id="adjusted_late_{{ $attendance->id }}"
                           value="{{ $attendance->adjusted_late_minutes ?? $attendance->late_minutes }}"
                           class="form-control">
                    <div class="form-text">{{ __('attendance_review.comparison.system_hint_minutes', ['minutes' => $summary->systemLateMinutes]) }}</div>
                </div>
            </div>
            <div class="attendance-review-actions">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('attendance_review.save') }}</button>
                @if ($summary->hasOverrides())
                    <button type="submit" name="reset_system" value="1" class="btn btn-sm btn-outline-secondary">
                        {{ __('attendance_review.comparison.use_system') }}
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>

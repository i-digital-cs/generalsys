@props([
    'attendance',
    'summary' => null,
    'filter' => null,
    'compact' => false,
    'embedded' => false,
])

@php
    use App\Services\Attendance\AttendanceService;
    use App\Services\Attendance\AttendanceTimeCalculator;

    $summary = $summary ?? app(AttendanceTimeCalculator::class)->summarize(
        $attendance,
        app(AttendanceService::class),
    );
    $actor = auth()->user();
    $defaultMinutes = $attendance->overtime_tl_recommendation === 'NONE'
        ? max((int) $attendance->recommended_overtime_minutes, $summary->systemSuggestedOvertimeMinutes)
        : (int) $attendance->recommended_overtime_minutes;
    $showTlForm = $actor?->isTeamLeader()
        && ! ($actor?->canManageOperations() ?? false)
        && $summary->needsTlOvertimeReview();
    $showAdminForm = ($actor?->canManageOperations() ?? false)
        && $summary->needsAdminOvertimeReview();
    $showSection = ! $compact && (
        ! $embedded
        || $showTlForm
        || $showAdminForm
        || $summary->recommendedOvertimeMinutes > 0
        || $summary->overtimeTlRecommendation !== 'NONE'
        || $summary->extraWorkedMinutes() > 0
    );
@endphp

@if ($showSection)
    <div {{ $attributes->merge(['class' => $compact ? '' : ($embedded ? 'attendance-detail-section__inner' : 'border rounded p-2 bg-body-tertiary')]) }}>
        @if (! $compact)
            <p class="fw-semibold small mb-2">{{ __('attendance_review.breakdown.overtime_title') }}</p>

            @unless ($embedded)
                <dl class="row mb-2 small">
                    @if ($summary->extraWorkedMinutes() > 0)
                        <dt class="col-sm-6">{{ __('attendance_review.hours_balance.difference') }}</dt>
                        <dd class="col-sm-6 num-ltr">
                            {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
                        </dd>
                    @endif
                    @if ($summary->systemSuggestedOvertimeMinutes > 0 && $attendance->overtime_tl_recommendation === 'NONE')
                        <dt class="col-sm-6">{{ __('attendance_review.overtime.system_suggested') }}</dt>
                        <dd class="col-sm-6 num-ltr">+{{ $summary->systemSuggestedOvertimeMinutes }}</dd>
                    @endif
                    @if ($summary->approvedOvertimeMinutes > 0)
                        <dt class="col-sm-6">{{ __('attendance_review.overtime.credited_cap') }}</dt>
                        <dd class="col-sm-6 num-ltr">
                            {{ __('attendance_review.overtime.credited_cap_hint', [
                                'planned' => number_format($summary->plannedHours, 2),
                                'minutes' => $summary->approvedOvertimeMinutes,
                            ]) }}
                        </dd>
                    @endif
                </dl>
            @endunless

            <x-attendance-overtime-status :attendance="$attendance" :summary="$summary" class="mb-2" />

            @if ($attendance->overtimeTlDecider)
                <div class="small text-body-secondary mb-1">
                    {{ __('attendance_review.overtime.decided_by_tl') }}:
                    {{ $attendance->overtimeTlDecider->localizedName() }}
                </div>
            @endif
            @if ($attendance->overtimeAdminDecider)
                <div class="small text-body-secondary mb-2">
                    {{ __('attendance_review.overtime.decided_by_admin') }}:
                    {{ $attendance->overtimeAdminDecider->localizedName() }}
                </div>
            @endif
        @endif

        @if ($showTlForm)
            <form method="POST" action="{{ route('team-leader.attendance.overtime.tl', $attendance) }}" class="mt-2">
                @csrf
                @if ($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <div class="row g-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label small mb-1">{{ __('attendance_review.overtime_minutes') }}</label>
                        <input type="number" min="0" max="720" name="recommended_overtime_minutes"
                               value="{{ $defaultMinutes }}"
                               class="form-control form-control-sm">
                        <div class="form-text">{{ __('attendance_review.comparison.system_hint_minutes', [
                            'minutes' => $summary->systemSuggestedOvertimeMinutes,
                        ]) }}</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">{{ __('attendance_review.overtime.recommendation') }}</label>
                        <select name="overtime_tl_recommendation" class="form-select form-select-sm">
                            <option value="RECOMMEND">{{ __('attendance_review.recommend_overtime') }}</option>
                            <option value="NO_OVERTIME">{{ __('attendance_review.no_overtime') }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary mt-2">
                    {{ __('attendance_review.recommend_overtime') }}
                </button>
            </form>
        @endif

        @if ($showAdminForm)
            <form method="POST" action="{{ route('team-leader.attendance.overtime.admin', $attendance) }}" class="mt-2">
                @csrf
                @if ($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <p class="small mb-2">
                    {{ __('attendance_review.overtime.admin_hint', [
                        'minutes' => $summary->recommendedOvertimeMinutes,
                        'planned' => number_format($summary->plannedHours, 2),
                    ]) }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="overtime_admin_decision" value="APPROVED" class="btn btn-sm btn-success">
                        {{ __('attendance_gaps.approve') }}
                    </button>
                    <button type="submit" name="overtime_admin_decision" value="REJECTED" class="btn btn-sm btn-outline-danger">
                        {{ __('attendance_gaps.reject') }}
                    </button>
                </div>
            </form>
        @elseif ($compact)
            <span class="small text-body-secondary">—</span>
        @endif
    </div>
@endif

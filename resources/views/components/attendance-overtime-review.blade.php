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
    $showFullSection = ! $compact && (
        ! $embedded
        || $showTlForm
        || $showAdminForm
        || $summary->recommendedOvertimeMinutes > 0
        || $summary->overtimeTlRecommendation !== 'NONE'
        || $summary->extraWorkedMinutes() > 0
    );
@endphp

@if ($compact)
    {{-- Approvals table cell: actions only --}}
    <div {{ $attributes }}>
        @if ($showTlForm)
            <form method="POST" action="{{ route('team-leader.attendance.overtime.tl', $attendance) }}">
                @csrf
                @if ($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="ot_minutes_c_{{ $attendance->id }}">{{ __('attendance_review.overtime_minutes') }}</label>
                        <input type="number" min="0" max="720" name="recommended_overtime_minutes"
                               id="ot_minutes_c_{{ $attendance->id }}"
                               value="{{ $defaultMinutes }}"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ot_rec_c_{{ $attendance->id }}">{{ __('attendance_review.overtime.recommendation') }}</label>
                        <select name="overtime_tl_recommendation" id="ot_rec_c_{{ $attendance->id }}" class="form-select form-select-sm">
                            <option value="RECOMMEND">{{ __('attendance_review.recommend_overtime') }}</option>
                            <option value="NO_OVERTIME">{{ __('attendance_review.no_overtime') }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary mt-2">
                    {{ __('attendance_review.recommend_overtime') }}
                </button>
            </form>
        @elseif ($showAdminForm)
            <form method="POST" action="{{ route('team-leader.attendance.overtime.admin', $attendance) }}">
                @csrf
                @if ($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="overtime_admin_decision" value="APPROVED" class="btn btn-sm btn-success">
                        {{ __('attendance_review.overtime.approve') }}
                    </button>
                    <button type="submit" name="overtime_admin_decision" value="REJECTED" class="btn btn-sm btn-outline-danger">
                        {{ __('attendance_review.overtime.reject') }}
                    </button>
                </div>
            </form>
        @else
            <span class="small text-body-secondary">—</span>
        @endif
    </div>
@elseif ($showFullSection)
    <div {{ $attributes->merge(['class' => $embedded ? 'attendance-detail-section__inner' : 'border rounded p-2 bg-body-tertiary']) }}>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <p class="fw-semibold small mb-0">{{ __('attendance_review.breakdown.overtime_title') }}</p>
            <x-attendance-overtime-status :attendance="$attendance" :summary="$summary" />
        </div>

        @unless ($embedded)
            <dl class="row mb-2 small">
                @if ($summary->extraWorkedMinutes() > 0)
                    <dt class="col-sm-6">{{ __('attendance_review.hours_balance.difference') }}</dt>
                    <dd class="col-sm-6 num-ltr hp-value">
                        {{ __('attendance_review.hours_balance.extra', ['minutes' => $summary->extraWorkedMinutes()]) }}
                    </dd>
                @endif
                @if ($summary->systemSuggestedOvertimeMinutes > 0 && $attendance->overtime_tl_recommendation === 'NONE')
                    <dt class="col-sm-6">{{ __('attendance_review.overtime.system_suggested') }}</dt>
                    <dd class="col-sm-6 num-ltr hp-value">+{{ $summary->systemSuggestedOvertimeMinutes }}</dd>
                @endif
                @if ($summary->approvedOvertimeMinutes > 0)
                    <dt class="col-sm-6">{{ __('attendance_review.overtime.credited_cap') }}</dt>
                    <dd class="col-sm-6 num-ltr hp-value">
                        {{ __('attendance_review.overtime.credited_cap_hint', [
                            'planned' => number_format($summary->plannedHours, 2),
                            'minutes' => $summary->approvedOvertimeMinutes,
                        ]) }}
                    </dd>
                @endif
            </dl>
        @endunless

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

        @if ($showTlForm)
            <form method="POST" action="{{ route('team-leader.attendance.overtime.tl', $attendance) }}" class="mt-2">
                @csrf
                @if ($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="ot_minutes_{{ $attendance->id }}">{{ __('attendance_review.overtime_minutes') }}</label>
                        <input type="number" min="0" max="720" name="recommended_overtime_minutes"
                               id="ot_minutes_{{ $attendance->id }}"
                               value="{{ $defaultMinutes }}"
                               class="form-control">
                        <div class="form-text">{{ __('attendance_review.comparison.system_hint_minutes', [
                            'minutes' => $summary->systemSuggestedOvertimeMinutes,
                        ]) }}</div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="ot_rec_{{ $attendance->id }}">{{ __('attendance_review.overtime.recommendation') }}</label>
                        <select name="overtime_tl_recommendation" id="ot_rec_{{ $attendance->id }}" class="form-select">
                            <option value="RECOMMEND">{{ __('attendance_review.recommend_overtime') }}</option>
                            <option value="NO_OVERTIME">{{ __('attendance_review.no_overtime') }}</option>
                        </select>
                    </div>
                </div>
                <div class="attendance-review-actions">
                    <button type="submit" class="btn btn-sm btn-primary">
                        {{ __('attendance_review.recommend_overtime') }}
                    </button>
                </div>
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
                <div class="attendance-review-actions">
                    <button type="submit" name="overtime_admin_decision" value="APPROVED" class="btn btn-sm btn-success">
                        {{ __('attendance_review.overtime.approve') }}
                    </button>
                    <button type="submit" name="overtime_admin_decision" value="REJECTED" class="btn btn-sm btn-outline-danger">
                        {{ __('attendance_review.overtime.reject') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
@endif

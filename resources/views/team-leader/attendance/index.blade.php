@extends('layouts.app')

@section('title', __('attendance.history.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('attendance.history.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('attendance.history.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('team-leader.attendance') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="employee_id" class="form-label">{{ __('attendance.history.employee') }}</label>
                    <select id="employee_id" name="employee_id" class="form-select">
                        <option value="">{{ __('attendance.history.all_employees') }}</option>
                        @foreach ($employees as $member)
                            @php
                                $label = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                            @endphp
                            <option value="{{ $member->id }}" @selected((int) $employeeId === (int) $member->id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="from" class="form-label">{{ __('attendance.history.from') }}</label>
                    <input type="date" id="from" name="from" value="{{ $from }}"
                           class="form-control @error('from') is-invalid @enderror" required>
                    @error('from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="to" class="form-label">{{ __('attendance.history.to') }}</label>
                    <input type="date" id="to" name="to" value="{{ $to }}"
                           class="form-control @error('to') is-invalid @enderror" required>
                    @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="kind" class="form-label">{{ __('attendance.history.kind') }}</label>
                    <select id="kind" name="kind" class="form-select">
                        @foreach (['all', 'present', 'absent', 'shortfall', 'surplus', 'rest', 'vacation'] as $option)
                            <option value="{{ $option }}" @selected($kind === $option)>
                                {{ __('attendance.history.kinds.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="evidence" class="form-label">{{ __('attendance.history.evidence') }}</label>
                    <select id="evidence" name="evidence" class="form-select">
                        @foreach (['all', 'photos', 'location', 'outside'] as $option)
                            <option value="{{ $option }}" @selected($evidence === $option)>
                                {{ __('attendance.history.evidence_options.'.$option) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-xl-1 d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('attendance.history.apply') }}</button>
                </div>
                <div class="col-12">
                    <a href="{{ route('team-leader.attendance') }}" class="btn btn-sm btn-outline-secondary">
                        {{ __('attendance.history.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2">
            <span class="fw-semibold">{{ __('attendance.history.list_title') }}</span>
            <span class="small text-body-secondary">
                {{ __('attendance.history.count', ['count' => $days->total()]) }}
            </span>
        </div>
        <div class="card-body">
            @if ($days->isEmpty())
                <x-empty-state
                    :title="__('attendance.history.empty_title')"
                    :text="__('attendance.history.empty_text')"
                    icon="bi-calendar-check"
                />
            @else
                <div class="table-responsive border rounded-3">
                    <table class="table table-hr table-attendance-history table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('attendance.history.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance.history.date') }}</th>
                                <th scope="col">{{ __('attendance.history.kind') }}</th>
                                <th scope="col">{{ __('attendance.history.summary') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($days as $row)
                                @php
                                    $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                                    $employeeFilterUrl = route('team-leader.attendance', [
                                        'employee_id' => $row['user']->id,
                                        'from' => $from,
                                        'to' => $to,
                                        'kind' => $kind,
                                        'evidence' => $evidence,
                                    ]);
                                    $employeeUrl = auth()->user()?->canManageOperations()
                                        ? route('admin.employees.edit', $row['user'])
                                        : $employeeFilterUrl;
                                    $modalId = 'att-day-'.$row['user']->id.'-'.str_replace('-', '', $row['date']).'-'.$loop->index;
                                    $late = (int) ($row['late_minutes'] ?? 0);
                                    $gap = (int) ($row['gap_minutes'] ?? 0);
                                    $ot = (int) ($row['recommended_overtime_minutes'] ?? 0);
                                    $teamLeaders = $row['user']->teamLeaderDisplayNames();
                                    $teamLeadersLabel = $teamLeaders !== '' ? $teamLeaders : __('general.not_available');
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ $employeeUrl }}"
                                           class="link-body-emphasis text-decoration-none fw-semibold">
                                            {{ $name }}
                                        </a>
                                        <div class="small text-body-secondary">
                                            {{ __('employees.team_leader') }}:
                                            {{ $teamLeadersLabel }}
                                        </div>
                                    </td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $row['kind'] === 'present',
                                            'text-bg-danger' => $row['kind'] === 'absent',
                                            'text-bg-warning text-dark' => $row['kind'] === 'shortfall' || $row['kind'] === 'rest',
                                            'text-bg-success' => $row['kind'] === 'surplus',
                                            'text-bg-info' => $row['kind'] === 'vacation',
                                        ])>
                                            {{ __('attendance.history.kinds.'.$row['kind']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            @foreach ($row['attendances'] ?? [] as $attPunch)
                                                @if ($attPunch->clock_in !== null)
                                                    <x-attendance-punch-range :attendance="$attPunch" :show-shift="true" />
                                                @endif
                                            @endforeach
                                            <span class="badge text-bg-light border text-dark">
                                                {{ __('dashboard.hours.should_spend') }}:
                                                <span class="num-ltr">{{ number_format($row['planned_hours'], 2) }}h</span>
                                            </span>
                                            <span class="badge text-bg-secondary">
                                                {{ __('dashboard.hours.spent') }}:
                                                <span class="num-ltr">{{ number_format($row['actual_hours'], 2) }}h</span>
                                            </span>
                                            @if ($late > 0)
                                                <span class="badge text-bg-warning text-dark">
                                                    {{ __('clock.late_minutes') }}:
                                                    <span class="num-ltr">{{ $late }}</span>
                                                </span>
                                            @endif
                                            @if ($gap > 0)
                                                <span class="badge text-bg-dark">
                                                    {{ __('attendance_review.breakdown.gap') }}:
                                                    <span class="num-ltr">{{ $gap }}</span>
                                                </span>
                                            @endif
                                            @if ($ot > 0)
                                                <span class="badge text-bg-primary">
                                                    {{ __('attendance_review.overtime_minutes') }}:
                                                    <span class="num-ltr">{{ $ot }}</span>
                                                </span>
                                            @endif
                                            @if ($row['has_photos'])
                                                <span class="badge text-bg-info text-dark">
                                                    <i class="bi bi-camera" aria-hidden="true"></i>
                                                    {{ __('attendance.history.has_photos') }}
                                                </span>
                                            @endif
                                            @foreach ($row['attendances'] ?? [] as $attRow)
                                                <x-attendance-location-badges
                                                    :attendance="$attRow"
                                                    :user="$row['user']"
                                                />
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#{{ $modalId }}">
                                            <i class="bi bi-window" aria-hidden="true"></i>
                                            {{ __('attendance.history.open_details') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach ($days as $row)
                    @php
                        $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                        $modalId = 'att-day-'.$row['user']->id.'-'.str_replace('-', '', $row['date']).'-'.$loop->index;
                        $reviewable = collect($row['attendances'] ?? [])->values();
                        $late = (int) ($row['late_minutes'] ?? 0);
                        $gap = (int) ($row['gap_minutes'] ?? 0);
                        $ot = (int) ($row['recommended_overtime_minutes'] ?? 0);
                        $teamLeaders = $row['user']->teamLeaderDisplayNames();
                        $teamLeadersLabel = $teamLeaders !== '' ? $teamLeaders : __('general.not_available');
                    @endphp
                    <div class="modal fade attendance-detail-modal" id="{{ $modalId }}" tabindex="-1"
                         aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header border-0 pb-0">
                                    <div>
                                        <h2 class="modal-title h5 mb-0" id="{{ $modalId }}-label">
                                            {{ $name }}
                                        </h2>
                                        <p class="small text-body-secondary mb-0 mt-1">
                                            <span class="num-ltr">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                                            · {{ __('attendance.history.kinds.'.$row['kind']) }}
                                            · {{ __('employees.team_leader') }}:
                                            {{ $teamLeadersLabel }}
                                        </p>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="{{ __('general.close') }}"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    <div class="attendance-detail-section mb-3">
                                        <div class="attendance-detail-metrics">
                                            <div>
                                                <h3 class="h6 mb-2">{{ __('attendance.history.hours_column') }}</h3>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('dashboard.hours.should_spend') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ number_format($row['planned_hours'], 2) }}h</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('attendance_review.raw_hours') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ number_format($row['raw_hours'], 2) }}h</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-0">
                                                    <dt>{{ __('dashboard.hours.spent') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ number_format($row['actual_hours'], 2) }}h</dd>
                                                </dl>
                                            </div>
                                            <div>
                                                <h3 class="h6 mb-2">{{ __('attendance.history.minutes_column') }}</h3>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('clock.late_minutes') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ $late }}</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('attendance_review.early_minutes') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ (int) ($row['early_departure_minutes'] ?? 0) }}</dd>
                                                </dl>
                                                <dl class="attendance-detail-metric mb-2">
                                                    <dt>{{ __('attendance_review.breakdown.gap') }}</dt>
                                                    <dd class="num-ltr hp-value">{{ $gap }}</dd>
                                                </dl>
                                                @if ($ot > 0)
                                                    <dl class="attendance-detail-metric mb-0">
                                                        <dt>{{ __('attendance_review.overtime_minutes') }}</dt>
                                                        <dd class="num-ltr hp-value">{{ $ot }}</dd>
                                                    </dl>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($row['has_photos'] || $row['has_location'])
                                        <div class="attendance-detail-section mb-3">
                                            <h3 class="h6 mb-3">{{ __('attendance.history.evidence') }}</h3>
                                            @if ($row['has_photos'])
                                                <x-attendance-photos :photos="$row['photos']" />
                                            @endif
                                            @foreach ($row['attendances'] ?? [] as $attRow)
                                                @if ($attRow->clock_in_lat !== null || $attRow->clock_out_lat !== null)
                                                    <x-attendance-location
                                                        :attendance="$attRow"
                                                        :user="$row['user']"
                                                        class="{{ $loop->first && ! $row['has_photos'] ? '' : 'mt-3' }}"
                                                    />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @forelse ($reviewable as $attendanceRow)
                                        <div @class(['attendance-detail-section', 'mb-3' => ! $loop->last])>
                                            <div class="attendance-shift-header">
                                                <h3 class="h6 mb-0">{{ __('clock.shifts.'.$attendanceRow->shift_type) }}</h3>
                                                <x-attendance-location-badges
                                                    :attendance="$attendanceRow"
                                                    :user="$row['user']"
                                                />
                                                <x-attendance-end-shift :attendance="$attendanceRow" />
                                            </div>

                                            <x-attendance-shift-timeline
                                                :attendance="$attendanceRow"
                                                class="mb-3"
                                            />

                                            @if ($attendanceRow->clock_out === null)
                                                <p class="small text-body-secondary mb-0">{{ __('clock.end_shift.hint') }}</p>
                                            @else
                                                <x-attendance-time-breakdown
                                                    :attendance="$attendanceRow"
                                                    review
                                                    :show-overtime-section="false"
                                                />

                                                <x-attendance-overtime-review
                                                    :attendance="$attendanceRow"
                                                    embedded
                                                    class="mt-0"
                                                />
                                            @endif
                                        </div>
                                    @empty
                                        <div class="attendance-detail-section">
                                            <p class="small text-body-secondary mb-0">
                                                {{ __('attendance.history.no_review') }}
                                            </p>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        {{ __('general.close') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="mt-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <p class="small text-body-secondary mb-0">
                        {{ __('attendance.history.showing', [
                            'from' => $days->firstItem() ?? 0,
                            'to' => $days->lastItem() ?? 0,
                            'total' => $days->total(),
                        ]) }}
                    </p>
                    <nav class="overflow-auto" aria-label="{{ __('attendance.history.title') }}">
                        {{ $days->onEachSide(1)->links() }}
                    </nav>
                </div>
            @endif
        </div>
    </div>
@endsection

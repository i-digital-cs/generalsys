{{-- Classic attendance history --}}
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
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            @php
                                                $kindBadge = match ($row['kind']) {
                                                    'present' => 'text-bg-secondary',
                                                    'absent' => 'text-bg-danger',
                                                    'shortfall', 'rest' => 'text-bg-warning text-dark',
                                                    'surplus' => 'text-bg-success',
                                                    'vacation' => 'text-bg-info',
                                                    default => 'text-bg-light border text-dark',
                                                };
                                                $punctuality = $row['punctuality'] ?? 'none';
                                                $punctualityBadge = match ($punctuality) {
                                                    'on_time' => 'text-bg-success',
                                                    'late' => 'text-bg-warning text-dark',
                                                    'early_leave' => 'text-bg-info text-dark',
                                                    default => 'text-bg-light border text-dark',
                                                };
                                            @endphp
                                            <x-attendance-metric-badge
                                                :label="__('attendance.history.kinds.'.$row['kind'])"
                                                :tooltip-key="$row['kind'] === 'present' ? 'attended' : null"
                                                :badge-class="$kindBadge"
                                            />
                                            @if ($punctuality !== 'none')
                                                <x-attendance-metric-badge
                                                    :label="__('attendance.history.punctuality.'.$punctuality)"
                                                    :tooltip-key="$punctuality === 'late' ? 'late' : null"
                                                    :badge-class="$punctualityBadge"
                                                />
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            @forelse ($row['sessions'] ?? [] as $session)
                                                <x-attendance-punch-range
                                                    :attendance="$session['attendance']"
                                                    :result="$session['calculation']"
                                                    :show-shift="true"
                                                />
                                            @empty
                                                @foreach ($row['attendances'] ?? [] as $attPunch)
                                                    @if ($attPunch->clock_in !== null)
                                                        <x-attendance-punch-range :attendance="$attPunch" :show-shift="true" />
                                                    @endif
                                                @endforeach
                                            @endforelse
                                            @if (! empty($row['needs_review']))
                                                <x-attendance-review-indicator
                                                    :reason-codes="$row['review_reason_codes'] ?? []"
                                                    :tooltip="$row['review_tooltip'] ?? ''"
                                                />
                                            @endif
                                            <span class="badge text-bg-light border text-dark">
                                                {{ __('dashboard.hours.should_spend') }}:
                                                <span class="num-ltr">{{ number_format($row['planned_hours'], 2) }}h</span>
                                            </span>
                                            @if (! empty($row['has_unverified_duration']))
                                                <x-attendance-metric-badge
                                                    :label="__('attendance.integrity.recorded_hours_unverified')"
                                                    :value="number_format($row['recorded_hours'] ?? $row['raw_hours'], 2).'h'"
                                                    tooltip-key="recorded_hours"
                                                    badge-class="text-bg-secondary"
                                                />
                                                <x-attendance-metric-badge
                                                    :label="in_array('MANUAL_OVERRIDE', $row['credited_provenance'] ?? [], true)
                                                        ? __('attendance.integrity.credited_manual')
                                                        : (in_array('OT_APPROVED', $row['credited_provenance'] ?? [], true)
                                                            ? __('attendance.integrity.credited_ot_approved')
                                                            : __('attendance.integrity.credited_policy_cap'))"
                                                    :value="number_format($row['credited_hours'] ?? $row['actual_hours'], 2).'h'"
                                                    tooltip-key="credited_hours"
                                                    badge-class="text-bg-light border text-dark"
                                                />
                                            @else
                                                <x-attendance-metric-badge
                                                    :label="__('attendance.integrity.verified_worked_hours')"
                                                    :value="number_format($row['verified_hours'] ?? $row['actual_hours'], 2).'h'"
                                                    tooltip-key="verified_worked_hours"
                                                    badge-class="text-bg-secondary"
                                                />
                                            @endif
                                            @if ($late > 0)
                                                <x-attendance-metric-badge
                                                    :label="! empty($row['has_shift_review'])
                                                        ? __('attendance.integrity.late_vs_planned_window')
                                                        : __('clock.late_minutes')"
                                                    :value="(string) $late"
                                                    :tooltip-key="! empty($row['has_shift_review']) ? 'late_vs_planned_window' : 'late'"
                                                    :badge-class="! empty($row['has_shift_review']) ? 'text-bg-warning text-dark' : 'text-bg-warning text-dark'"
                                                />
                                            @endif
                                            @if ($gap > 0)
                                                <span class="badge text-bg-dark">
                                                    {{ __('attendance_review.breakdown.gap') }}:
                                                    <span class="num-ltr">{{ $gap }}</span>
                                                </span>
                                            @endif
                                            @php
                                                $otEligible = (int) ($row['overtime_eligible_minutes'] ?? 0);
                                                $otApproved = (int) ($row['overtime_approved_minutes'] ?? 0);
                                            @endphp
                                            @if ($otApproved > 0)
                                                <x-attendance-metric-badge
                                                    :label="__('attendance.integrity.ot_approved')"
                                                    :value="(string) $otApproved"
                                                    tooltip-key="approved_overtime"
                                                    badge-class="text-bg-success"
                                                />
                                            @elseif ($otEligible > 0 || $ot > 0)
                                                <x-attendance-metric-badge
                                                    :label="__('attendance.integrity.ot_eligible')"
                                                    :value="(string) ($otEligible > 0 ? $otEligible : $ot)"
                                                    tooltip-key="eligible_overtime"
                                                    badge-class="text-bg-primary"
                                                />
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

    @include('team-leader.attendance._day-modals')

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

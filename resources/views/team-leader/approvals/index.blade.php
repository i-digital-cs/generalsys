@extends('layouts.app')

@section('title', __('permissions.tl.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">
                {{ ($canDecideLeave ?? true) ? __('permissions.tl.title') : __('attendance_gaps.title') }}
            </h1>
            <p class="text-body-secondary mb-0">
                {{ ($canDecideLeave ?? true) ? __('permissions.tl.subtitle') : __('attendance_gaps.assistant_subtitle') }}
            </p>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap gap-2 mb-3">
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'pending') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'pending']) }}">
                {{ __('permissions.tl.filters.pending') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'decided') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'decided']) }}">
                {{ __('permissions.tl.filters.decided') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if ($filter === 'all') active @endif"
               href="{{ route('team-leader.approvals', ['filter' => 'all']) }}">
                {{ __('permissions.tl.filters.all') }}
            </a>
        </li>
    </ul>

    @if ($canDecideLeave ?? true)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('permissions.tl.section_permissions') }}</h2>
        </div>
        <div class="card-body">
            @if ($permissions->isEmpty())
                <x-empty-state
                    :title="__('permissions.tl.empty_title')"
                    :text="__('permissions.tl.empty_text')"
                    icon="bi-check2-square"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('permissions.fields.date_period') }}</th>
                                <th scope="col">{{ __('permissions.fields.type') }}</th>
                                <th scope="col" class="col-num">{{ __('permissions.fields.interval') }}</th>
                                <th scope="col">{{ __('permissions.fields.status') }}</th>
                                <th scope="col">{{ __('permissions.fields.reason') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row->user?->localizedName() }}</div>
                                        @if (filled($row->user?->teamLeaderDisplayNames()))
                                            <div class="small text-body-secondary">
                                                {{ __('permissions.fields.submitted_for_team') }}:
                                                {{ $row->user->teamLeaderDisplayNames() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">
                                            @if ($row->isAbsence())
                                                {{ $row->request_date->toDateString() }}
                                                –
                                                {{ ($row->end_date ?? $row->request_date)->toDateString() }}
                                            @else
                                                {{ $row->request_date->toDateString() }}
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ __('permissions.types.'.$row->type) }}</td>
                                    <td class="col-num text-nowrap">
                                        @if ($row->isAbsence())
                                            <span class="text-body-secondary">
                                                {{ __('permissions.full_day') }}
                                                @if ($row->absenceDayCount() > 1)
                                                    <span class="num-ltr">({{ $row->absenceDayCount() }})</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="num-ltr">{{ $row->from_time }}–{{ $row->to_time }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-warning' => $row->status === 'PENDING',
                                            'text-bg-success' => $row->status === 'ACCEPTED',
                                            'text-bg-danger' => $row->status === 'REJECTED',
                                            'text-bg-dark' => $row->status === 'FORCED',
                                        ])>
                                            {{ __('permissions.statuses.'.$row->status) }}
                                        </span>
                                        @if ($row->decidedBy)
                                            <div class="small text-body-secondary mt-1">
                                                {{ __('permissions.fields.decided_by') }}:
                                                {{ $row->decidedBy->localizedName() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-body-secondary small">{{ filled($row->reason) ? $row->reason : '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex flex-nowrap gap-2 justify-content-end">
                                            @if ($row->status === 'PENDING')
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.approve', $row) }}">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        {{ __('permissions.tl.accept') }}
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.reject', $row) }}"
                                                      onsubmit="return confirm(@json(__('permissions.tl.reject_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        {{ __('permissions.tl.reject') }}
                                                    </button>
                                                </form>
                                            @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.permissions.force', $row) }}"
                                                      onsubmit="return confirm(@json(__('permissions.force_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">
                                                        {{ __('permissions.force') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $permissions->links() }}
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('attendance_gaps.section') }}</h2>
        </div>
        <div class="card-body">
            @php
                $gapEmptyText = match (true) {
                    ($canManageOperations ?? false) && $filter === 'pending' => __('attendance_gaps.empty_pending_admin'),
                    ($canManageOperations ?? false) && $filter === 'decided' => __('attendance_gaps.empty_decided'),
                    ($canManageOperations ?? false) => __('attendance_gaps.empty_all'),
                    $filter === 'decided' => __('attendance_gaps.empty_decided'),
                    default => __('attendance_gaps.empty_pending_tl'),
                };
            @endphp
            @if (($gaps ?? collect())->isEmpty())
                <x-empty-state
                    :title="__('attendance_gaps.title')"
                    :text="$gapEmptyText"
                    icon="bi-pause-circle"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance.history.date') }}</th>
                                <th scope="col">{{ __('attendance_gaps.shift') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_gaps.section') }}</th>
                                <th scope="col">{{ __('attendance_gaps.tl_recommend') }}</th>
                                <th scope="col">{{ __('attendance_gaps.admin_decision') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gaps as $gap)
                                @php
                                    $attendance = $gap->attendance;
                                    $employee = $attendance?->user;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $employee?->localizedName() }}</div>
                                        @if (filled($employee?->teamLeaderDisplayNames()))
                                            <div class="small text-body-secondary">
                                                {{ __('permissions.fields.submitted_for_team') }}:
                                                {{ $employee->teamLeaderDisplayNames() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ $attendance?->date?->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>{{ $attendance ? __('clock.shifts.'.$attendance->shift_type) : '—' }}</td>
                                    <td class="col-num text-nowrap">
                                        <div class="num-ltr">{{ __('attendance_gaps.interval', [
                                            'start' => $gap->gap_start->format('H:i'),
                                            'end' => $gap->gap_end->format('H:i'),
                                        ]) }}</div>
                                        <div class="small text-body-secondary">{{ __('attendance_gaps.minutes', ['count' => $gap->minutes]) }}</div>
                                    </td>
                                    <td>
                                        <x-attendance-gap-status :gap="$gap" />
                                        @if ($gap->tlDecider)
                                            <div class="small text-body-secondary mt-1">
                                                {{ __('attendance_gaps.decided_by_tl') }}:
                                                {{ $gap->tlDecider->localizedName() }}
                                            </div>
                                        @endif
                                        @if (filled($gap->tl_note))
                                            <div class="small text-body-secondary mt-1">{{ $gap->tl_note }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($gap->admin_decision !== \App\Models\AttendanceGap::ADMIN_PENDING)
                                            <span @class([
                                                'badge',
                                                'text-bg-success' => $gap->admin_decision === \App\Models\AttendanceGap::ADMIN_APPROVED,
                                                'text-bg-danger' => $gap->admin_decision === \App\Models\AttendanceGap::ADMIN_REJECTED,
                                            ])>
                                                {{ __('attendance_gaps.status.'.$gap->admin_decision) }}
                                            </span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('attendance_gaps.pending_admin') }}</span>
                                        @endif
                                        @if ($gap->adminDecider)
                                            <div class="small text-body-secondary mt-1">
                                                {{ __('attendance_gaps.decided_by_admin') }}:
                                                {{ $gap->adminDecider->localizedName() }}
                                            </div>
                                        @endif
                                        @if (filled($gap->admin_note))
                                            <div class="small text-body-secondary mt-1">{{ $gap->admin_note }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($gap->tl_recommendation === \App\Models\AttendanceGap::TL_PENDING && auth()->user()?->isTeamLeader() && ! ($canManageOperations ?? false))
                                            <form method="POST" action="{{ route('team-leader.approvals.gaps.recommend', $gap) }}" class="text-start">
                                                @csrf
                                                <input type="hidden" name="filter" value="{{ $filter }}">
                                                <label class="form-label small mb-1">{{ __('attendance_gaps.note') }}</label>
                                                <textarea name="note" rows="2" maxlength="500" class="form-control form-control-sm mb-2"></textarea>
                                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                    <button type="submit" name="recommendation" value="APPROVE" class="btn btn-sm btn-success">
                                                        {{ __('attendance_gaps.recommend_approve') }}
                                                    </button>
                                                    <button type="submit" name="recommendation" value="REJECT" class="btn btn-sm btn-outline-danger">
                                                        {{ __('attendance_gaps.recommend_reject') }}
                                                    </button>
                                                </div>
                                            </form>
                                        @elseif ($gap->isPendingForAdmin() && ($canManageOperations ?? false))
                                            <form method="POST" action="{{ route('team-leader.approvals.gaps.decide', $gap) }}" class="text-start">
                                                @csrf
                                                <input type="hidden" name="filter" value="{{ $filter }}">
                                                <label class="form-label small mb-1">{{ __('attendance_gaps.note') }}</label>
                                                <textarea name="note" rows="2" maxlength="500" class="form-control form-control-sm mb-2"></textarea>
                                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                    <button type="submit" name="decision" value="APPROVED" class="btn btn-sm btn-success">
                                                        {{ __('attendance_gaps.approve') }}
                                                    </button>
                                                    <button type="submit" name="decision" value="REJECTED" class="btn btn-sm btn-outline-danger">
                                                        {{ __('attendance_gaps.reject') }}
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <span class="small text-body-secondary">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('attendance_review.overtime.section') }}</h2>
        </div>
        <div class="card-body">
            @if ($overtimeRows->isEmpty())
                @php
                    $overtimeEmpty = match (true) {
                        ($canManageOperations ?? false) && $filter === 'pending' => __('attendance_review.overtime.empty_pending_admin'),
                        ($canManageOperations ?? false) && $filter === 'decided' => __('attendance_review.overtime.empty_decided'),
                        ($canManageOperations ?? false) => __('attendance_review.overtime.empty_all'),
                        $filter === 'decided' => __('attendance_review.overtime.empty_decided'),
                        default => __('attendance_review.overtime.empty_pending_tl'),
                    };
                @endphp
                <x-empty-state
                    :title="__('attendance_review.overtime.section')"
                    :text="$overtimeEmpty"
                    icon="bi-clock-history"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('attendance.history.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance.history.date') }}</th>
                                <th scope="col">{{ __('attendance_gaps.shift') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_review.planned_hours') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_review.raw_hours') }}</th>
                                <th scope="col">{{ __('attendance_review.overtime.recommendation') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overtimeRows as $overtimeRow)
                                @php
                                    $employee = $overtimeRow->user;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $employee?->localizedName() }}</div>
                                    </td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ $overtimeRow->date?->translatedFormat('d M Y') }}</span>
                                    </td>
                                    <td>{{ __('clock.shifts.'.$overtimeRow->shift_type) }}</td>
                                    <td class="col-num num-ltr">{{ number_format((float) $overtimeRow->planned_hours, 2) }}h</td>
                                    <td class="col-num num-ltr">{{ number_format((float) $overtimeRow->raw_actual_hours, 2) }}h</td>
                                    <td>
                                        <x-attendance-overtime-status :attendance="$overtimeRow" />
                                    </td>
                                    <td class="text-end">
                                        <x-attendance-overtime-review
                                            :attendance="$overtimeRow"
                                            :filter="$filter"
                                            compact
                                            class="text-start d-inline-block"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($canDecideLeave ?? true)
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h2 class="h6 mb-0">{{ __('vacations.tl.section') }}</h2>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('vacations.tl.credit_hint') }}</p>
        </div>
        <div class="card-body">
            @if ($vacations->isEmpty())
                <x-empty-state
                    :title="__('vacations.tl.empty_title')"
                    :text="__('vacations.tl.empty_text')"
                    icon="bi-calendar2-check"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col">{{ __('vacations.fields.type') }}</th>
                                <th scope="col" class="col-num">{{ __('vacations.fields.range') }}</th>
                                <th scope="col" class="col-num">{{ __('vacations.fields.days_requested') }}</th>
                                <th scope="col" class="col-num">{{ __('vacations.fields.replace_rest_day') }}</th>
                                <th scope="col">{{ __('vacations.fields.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vacations as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row->user?->localizedName() }}</div>
                                        @if (filled($row->user?->teamLeaderDisplayNames()))
                                            <div class="small text-body-secondary">
                                                {{ __('vacations.fields.submitted_for_team') }}:
                                                {{ $row->user->teamLeaderDisplayNames() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ __('vacations.types.'.$row->type) }}</td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr" dir="ltr">
                                            @if ($row->start_date->equalTo($row->end_date))
                                                {{ $row->start_date->toDateString() }}
                                            @else
                                                {{ $row->start_date->toDateString() }} – {{ $row->end_date->toDateString() }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="col-num"><span class="num-ltr">{{ number_format((float) $row->days_requested, 1) }}</span></td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ $row->replace_rest_date?->toDateString() ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-warning' => $row->status === 'PENDING',
                                            'text-bg-success' => $row->status === 'APPROVED',
                                            'text-bg-danger' => $row->status === 'REJECTED',
                                            'text-bg-dark' => $row->status === 'FORCED',
                                        ])>
                                            {{ __('vacations.statuses.'.$row->status) }}
                                        </span>
                                        @if ($row->decidedBy)
                                            <div class="small text-body-secondary mt-1">
                                                {{ __('vacations.fields.decided_by') }}:
                                                {{ $row->decidedBy->localizedName() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-nowrap gap-2 justify-content-end">
                                            @if ($row->status === 'PENDING')
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.approve', $row) }}">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        {{ __('vacations.tl.accept') }}
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.reject', $row) }}"
                                                      onsubmit="return confirm(@json(__('vacations.tl.reject_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        {{ __('vacations.tl.reject') }}
                                                    </button>
                                                </form>
                                            @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                                <form method="POST"
                                                      action="{{ route('team-leader.approvals.vacations.force', $row) }}"
                                                      onsubmit="return confirm(@json(__('vacations.force_confirm')))">
                                                    @csrf
                                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">
                                                        {{ __('vacations.force') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $vacations->links() }}
                </div>
            @endif
        </div>
    </div>
    @endif
@endsection

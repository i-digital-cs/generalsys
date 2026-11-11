{{-- Modern card presentation (working tree) --}}
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

    @php
        $statusPills = ['pending', 'approved', 'rejected', 'all'];
        $pillBase = collect($filterQuery ?? [])->except('filter')->all();
    @endphp
    <ul class="nav nav-pills flex-wrap gap-2 mb-3">
        @foreach ($statusPills as $statusKey)
            <li class="nav-item">
                <a class="nav-link @if ($filter === $statusKey) active @endif"
                   href="{{ route('team-leader.approvals', array_merge($pillBase, ['filter' => $statusKey])) }}">
                    {{ __('permissions.tl.filters.'.$statusKey) }}
                </a>
            </li>
        @endforeach
        @if ($filter === 'decided')
            <li class="nav-item">
                <a class="nav-link active"
                   href="{{ route('team-leader.approvals', array_merge($pillBase, ['filter' => 'decided'])) }}">
                    {{ __('permissions.tl.filters.decided') }}
                </a>
            </li>
        @endif
    </ul>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('team-leader.approvals') }}" class="row g-2 align-items-end">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <div class="col-12 col-md-3">
                    <label for="approvals_month" class="form-label">{{ __('permissions.tl.filter_month') }}</label>
                    <input type="month" id="approvals_month" name="month" class="form-control"
                           value="{{ $filters->month ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <label for="approvals_user_id" class="form-label">{{ __('permissions.tl.filter_user') }}</label>
                    <select id="approvals_user_id" name="user_id" class="form-select">
                        <option value="">{{ __('permissions.tl.filter_user_all') }}</option>
                        @foreach ($filterEmployees ?? [] as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters->userId ?? 0) === (int) $member->id)>
                                {{ $member->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="approvals_q" class="form-label">{{ __('permissions.tl.filter_search') }}</label>
                    <input type="search" id="approvals_q" name="q" class="form-control"
                           value="{{ $filters->q ?? '' }}"
                           placeholder="{{ __('permissions.tl.filter_search_placeholder') }}">
                </div>
                <div class="col-12 col-md-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('permissions.tl.filter_apply') }}</button>
                    <a href="{{ route('team-leader.approvals', ['filter' => $filter]) }}" class="btn btn-outline-secondary">
                        {{ __('permissions.tl.filter_clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

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
                <div class="eval-card-ui" data-eval-card-ui="hr-approvals-permissions">
                    <div class="eval-card-ui__grid">
                        @foreach ($permissions as $row)
                            @php
                                $emp = $row->user;
                                $name = $emp?->localizedName() ?? __('general.not_available');
                                $photoUrl = ($emp?->profile?->personal_photo_path && auth()->user()?->can('view', $emp))
                                    ? route('employees.photo', $emp)
                                    : null;
                                $initial = mb_substr($name, 0, 1);
                                if ($row->isAbsence()) {
                                    $period = $row->request_date->toDateString()
                                        .' – '.($row->end_date ?? $row->request_date)->toDateString();
                                    $interval = __('permissions.full_day')
                                        .($row->absenceDayCount() > 1 ? ' ('.$row->absenceDayCount().')' : '');
                                } else {
                                    $period = $row->request_date->toDateString();
                                    $interval = $row->from_time.'–'.$row->to_time;
                                }
                            @endphp
                            <x-gh.record-card
                                :title="$name"
                                :image-url="$photoUrl"
                                :image-alt="$name"
                                image-variant="circle"
                                :image-placeholder="$initial"
                            >
                                <x-slot:badges>
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--muted' => $row->status === 'PENDING',
                                        'gh-record-card__badge--status' => in_array($row->status, ['ACCEPTED', 'FORCED'], true),
                                    ])>
                                        {{ __('permissions.statuses.'.$row->status) }}
                                    </span>
                                    <span class="gh-record-card__badge gh-record-card__badge--muted">
                                        {{ __('permissions.types.'.$row->type) }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <x-gh.meta-cell icon="bi-calendar3" :value="$period" :ltr="true" />
                                    <x-gh.meta-cell icon="bi-clock" :value="$interval" :ltr="true" />
                                    @if (filled($row->reason))
                                        <x-gh.meta-cell icon="bi-chat-left-text" :value="$row->reason" />
                                    @endif
                                    @if ($row->decidedBy)
                                        <x-gh.meta-cell
                                            icon="bi-person-check"
                                            :value="__('permissions.fields.decided_by').': '.$row->decidedBy->localizedName()"
                                        />
                                    @endif
                                </x-slot:meta>
                                <x-slot:actions>
                                    @if ($row->status === 'PENDING')
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.permissions.approve', $row) }}">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn">
                                                <i class="bi bi-check2" aria-hidden="true"></i>
                                                <span>{{ __('permissions.tl.accept') }}</span>
                                            </button>
                                        </form>
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.permissions.reject', $row) }}"
                                              onsubmit="return confirm(@json(__('permissions.tl.reject_confirm')))">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn gh-board-btn--ghost text-danger">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                <span>{{ __('permissions.tl.reject') }}</span>
                                            </button>
                                        </form>
                                    @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.permissions.force', $row) }}"
                                              onsubmit="return confirm(@json(__('permissions.force_confirm')))">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn gh-board-btn--ghost">
                                                <i class="bi bi-lightning" aria-hidden="true"></i>
                                                <span>{{ __('permissions.force') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </x-slot:actions>
                            </x-gh.record-card>
                        @endforeach
                    </div>
                    <div class="eval-card-ui__pager">{{ $permissions->links() }}</div>
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
                                        @if ($gap->isPendingForTl() && auth()->user()?->isTeamLeader() && ! ($canManageOperations ?? false))
                                            <form method="POST" action="{{ route('team-leader.approvals.gaps.recommend', $gap) }}" class="text-start">
                                                @csrf
                                                @include('team-leader.approvals._filter-hiddens')
                                                @if ($gap->isAwaitingTlRereview())
                                                    <p class="small text-warning mb-2">{{ __('attendance_gaps.rereview_hint') }}</p>
                                                @endif
                                                <label class="form-label small mb-1">{{ __('attendance_gaps.note') }}</label>
                                                <textarea name="note" rows="2" maxlength="500" class="form-control form-control-sm mb-2"></textarea>
                                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                    <button type="submit" name="recommendation" value="APPROVE" class="btn btn-sm btn-success">
                                                        {{ $gap->isAwaitingTlRereview()
                                                            ? __('attendance_gaps.recommend_again')
                                                            : __('attendance_gaps.recommend_approve') }}
                                                    </button>
                                                    <button type="submit" name="recommendation" value="REJECT" class="btn btn-sm btn-outline-danger">
                                                        {{ __('attendance_gaps.recommend_reject') }}
                                                    </button>
                                                </div>
                                            </form>
                                        @elseif ($gap->isPendingForAdmin() && ($canManageOperations ?? false))
                                            <form method="POST" action="{{ route('team-leader.approvals.gaps.decide', $gap) }}" class="text-start">
                                                @csrf
                                                @include('team-leader.approvals._filter-hiddens')
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
                        in_array($filter, ['decided', 'approved', 'rejected'], true) => __('attendance_review.overtime.empty_decided'),
                        ($canManageOperations ?? false) => __('attendance_review.overtime.empty_all'),
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
                                <th scope="col">{{ __('shifts.employee') }}</th>
                                <th scope="col" class="col-num">{{ __('permissions.fields.request_date') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_review.planned_hours') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_review.raw_hours') }}</th>
                                <th scope="col" class="col-num">{{ __('attendance_review.overtime.tl_minutes') }}</th>
                                <th scope="col">{{ __('permissions.fields.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overtimeRows as $overtimeRow)
                                @php
                                    $tlMinutes = (int) $overtimeRow->recommended_overtime_minutes;
                                    $tlRec = (string) $overtimeRow->overtime_tl_recommendation;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $overtimeRow->user?->localizedName() }}</div>
                                        <div class="small text-body-secondary">
                                            {{ __('clock.shifts.'.$overtimeRow->shift_type) }}
                                        </div>
                                    </td>
                                    <td class="col-num text-nowrap">
                                        <span class="num-ltr">{{ $overtimeRow->date?->toDateString() }}</span>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr hp-value">{{ number_format($overtimeRow->resolvedPlannedHours(), 2) }}h</span>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr hp-value">{{ number_format((float) $overtimeRow->raw_actual_hours, 2) }}h</span>
                                    </td>
                                    <td class="col-num">
                                        @if ($tlRec === 'RECOMMEND' && $tlMinutes > 0)
                                            <span class="num-ltr hp-value">+{{ $tlMinutes }}</span>
                                        @elseif ($tlRec === 'NO_OVERTIME')
                                            <span class="text-body-secondary">0</span>
                                        @else
                                            <span class="text-body-secondary">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-attendance-overtime-status :attendance="$overtimeRow" />
                                    </td>
                                    <td class="text-end approvals-ot-actions">
                                        <x-attendance-overtime-review
                                            :attendance="$overtimeRow"
                                            :filter="$filter"
                                            :filter-query="$filterQuery"
                                            compact
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $overtimeRows->links() }}
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
                <div class="eval-card-ui" data-eval-card-ui="hr-approvals-vacations">
                    <div class="eval-card-ui__grid">
                        @foreach ($vacations as $row)
                            @php
                                $emp = $row->user;
                                $name = $emp?->localizedName() ?? __('general.not_available');
                                $photoUrl = ($emp?->profile?->personal_photo_path && auth()->user()?->can('view', $emp))
                                    ? route('employees.photo', $emp)
                                    : null;
                                $initial = mb_substr($name, 0, 1);
                                $range = $row->start_date->equalTo($row->end_date)
                                    ? $row->start_date->toDateString()
                                    : $row->start_date->toDateString().' – '.$row->end_date->toDateString();
                                $daysLabel = number_format((float) $row->days_requested, 1);
                            @endphp
                            <x-gh.record-card
                                :title="$name"
                                :image-url="$photoUrl"
                                :image-alt="$name"
                                image-variant="circle"
                                :image-placeholder="$initial"
                            >
                                <x-slot:badges>
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--muted' => $row->status === 'PENDING',
                                        'gh-record-card__badge--status' => in_array($row->status, ['APPROVED', 'FORCED'], true),
                                    ])>
                                        {{ __('vacations.statuses.'.$row->status) }}
                                    </span>
                                    <span class="gh-record-card__badge gh-record-card__badge--muted">
                                        {{ __('vacations.types.'.$row->type) }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <x-gh.meta-cell icon="bi-calendar3" :value="$range" :ltr="true" />
                                    <x-gh.meta-cell icon="bi-hash" :value="$daysLabel" :ltr="true" />
                                    <x-gh.meta-cell
                                        icon="bi-arrow-repeat"
                                        :value="$row->replace_rest_date?->toDateString() ?? '—'"
                                        :ltr="true"
                                    />
                                    @if ($row->decidedBy)
                                        <x-gh.meta-cell
                                            icon="bi-person-check"
                                            :value="__('vacations.fields.decided_by').': '.$row->decidedBy->localizedName()"
                                        />
                                    @endif
                                </x-slot:meta>
                                <x-slot:actions>
                                    @if ($row->status === 'PENDING')
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.vacations.approve', $row) }}">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn">
                                                <i class="bi bi-check2" aria-hidden="true"></i>
                                                <span>{{ __('vacations.tl.accept') }}</span>
                                            </button>
                                        </form>
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.vacations.reject', $row) }}"
                                              onsubmit="return confirm(@json(__('vacations.tl.reject_confirm')))">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn gh-board-btn--ghost text-danger">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                <span>{{ __('vacations.tl.reject') }}</span>
                                            </button>
                                        </form>
                                    @elseif ($row->status === 'REJECTED' && ($canForce ?? false))
                                        <form method="POST"
                                              action="{{ route('team-leader.approvals.vacations.force', $row) }}"
                                              onsubmit="return confirm(@json(__('vacations.force_confirm')))">
                                            @csrf
                                            @include('team-leader.approvals._filter-hiddens')
                                            <button type="submit" class="gh-board-btn gh-board-btn--ghost">
                                                <i class="bi bi-lightning" aria-hidden="true"></i>
                                                <span>{{ __('vacations.force') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </x-slot:actions>
                            </x-gh.record-card>
                        @endforeach
                    </div>
                    <div class="eval-card-ui__pager">{{ $vacations->links() }}</div>
                </div>
            @endif
        </div>
    </div>
    @endif

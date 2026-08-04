@extends('layouts.app')

@section('title', __('nav.dashboard'))

@section('content')
    <div class="welcome-hero p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="h4 mb-1">
                {{ __('dashboard.welcome', ['name' => app()->getLocale() === 'ar' ? auth()->user()->name_ar : auth()->user()->name_en]) }}
            </h1>
            <p class="mb-0 opacity-75">{{ __('nav.dashboard') }}</p>
        </div>
        <span class="hero-date">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ now()->translatedFormat('l, d F Y') }}
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('dashboard.total_employees')" :value="$stats['total']" icon="bi-people" color="primary" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('dashboard.active_employees')" :value="$stats['active']" icon="bi-person-check" color="success" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('dashboard.inactive_employees')" :value="$stats['inactive']" icon="bi-person-dash" color="warning" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('dashboard.contracts_expiring')" :value="$stats['contracts_expiring']"
                         icon="bi-calendar-x" color="danger" :subtitle="__('dashboard.within_60_days')" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-x text-danger" aria-hidden="true"></i>
                    <h2 class="h6 mb-0">{{ __('dashboard.expiring_contracts') }}</h2>
                </div>
                <div class="card-body pt-2">
                    @forelse ($expiringContracts as $contract)
                        @php
                            $daysLeft = (int) now()->startOfDay()->diffInDays($contract->contract_end);
                        @endphp
                        <div class="contract-row">
                            <div>
                                <div class="fw-semibold">
                                    {{ app()->getLocale() === 'ar' ? $contract->user->name_ar : $contract->user->name_en }}
                                </div>
                                <div class="text-body-secondary small">
                                    {{ __('dashboard.ends_on', ['date' => $contract->contract_end->translatedFormat('d F Y')]) }}
                                </div>
                            </div>
                            <span class="badge text-bg-{{ $daysLeft <= 14 ? 'danger' : 'warning' }}">
                                {{ trans_choice('dashboard.days_left', $daysLeft) }}
                            </span>
                        </div>
                    @empty
                        <x-empty-state :title="__('dashboard.no_expiring_contracts')" icon="bi-calendar-check" />
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge text-primary" aria-hidden="true"></i>
                    <h2 class="h6 mb-0">{{ __('dashboard.quick_actions') }}</h2>
                </div>
                <div class="card-body pt-2 d-grid gap-2">
                    <a class="quick-action" href="{{ route('team-leader.shifts', ['date' => now()->toDateString()]) }}">
                        <span class="quick-action-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('nav.shifts') }}</span>
                    </a>
                    <a class="quick-action" href="{{ route('admin.employees.create') }}">
                        <span class="quick-action-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('employees.add_new') }}</span>
                    </a>
                    <a class="quick-action" href="{{ route('admin.employees.index') }}">
                        <span class="quick-action-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-people" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('nav.employees') }}</span>
                    </a>
                    <a class="quick-action" href="{{ route('admin.settings') }}">
                        <span class="quick-action-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-gear" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('nav.settings') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @php
        $leaderShiftsToday = ($teamShifts ?? collect())
            ->filter(fn ($row) => $row->user
                && $row->user->isTeamLeader()
                && $row->date->toDateString() === now()->toDateString())
            ->values();
    @endphp

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h6 mb-0">{{ __('dashboard.leader_shifts.title') }}</h2>
                            <p class="small text-body-secondary mb-0">{{ __('dashboard.leader_shifts.hint') }}</p>
                        </div>
                        <a href="{{ route('team-leader.shifts', ['date' => now()->toDateString()]) }}"
                           class="btn btn-sm btn-outline-primary">
                            {{ __('dashboard.team_intervals.manage') }}
                        </a>
                    </div>

                    @if ($leaderShiftsToday->isEmpty())
                        <x-empty-state
                            :title="__('dashboard.leader_shifts.empty_title')"
                            :text="__('dashboard.leader_shifts.empty_text')"
                            icon="bi-calendar3"
                        />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('shifts.employee') }}</th>
                                        <th scope="col">{{ __('shifts.shift_type') }}</th>
                                        <th scope="col">{{ __('shifts.start_time') }}</th>
                                        <th scope="col">{{ __('shifts.end_time') }}</th>
                                        <th scope="col" class="text-end">{{ __('shifts.expected_hours') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($leaderShiftsToday as $shift)
                                        @php
                                            $name = app()->getLocale() === 'ar'
                                                ? $shift->user->name_ar
                                                : $shift->user->name_en;
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $name }}</td>
                                            <td>{{ __('clock.shifts.'.$shift->shift_type) }}</td>
                                            <td dir="ltr">{{ \Illuminate\Support\Str::of($shift->expected_start_time)->substr(0, 5) }}</td>
                                            <td dir="ltr">{{ \Illuminate\Support\Str::of($shift->expected_end_time)->substr(0, 5) }}</td>
                                            <td class="text-end" dir="ltr">{{ number_format((float) $shift->expected_hours, 2) }}h</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($showTeamHours ?? false)
        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <h2 class="h6 mb-0">{{ __('dashboard.hours.today_board') }}</h2>
                            <a href="{{ route('team-leader.shifts', ['date' => now()->toDateString()]) }}"
                               class="btn btn-sm btn-outline-primary">
                                {{ __('dashboard.team_intervals.manage') }}
                            </a>
                        </div>
                        @if ($todayStatus->isEmpty())
                            <x-empty-state
                                :title="__('dashboard.hours.no_team_title')"
                                :text="__('dashboard.hours.no_team_text')"
                                icon="bi-people"
                            />
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('shifts.employee') }}</th>
                                            <th scope="col">{{ __('dashboard.hours.status') }}</th>
                                            <th scope="col">{{ __('dashboard.hours.should_spend') }}</th>
                                            <th scope="col">{{ __('dashboard.hours.spent') }}</th>
                                            <th scope="col" class="text-end">{{ __('dashboard.hours.pct') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($todayStatus as $row)
                                            @php
                                                $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">
                                                    <a href="{{ route($dashboardRoute, ['cal' => $calCursor, 'user_id' => $row['user']->id]) }}">
                                                        {{ $name }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <span @class([
                                                        'badge',
                                                        'text-bg-success' => $row['status'] === 'IN',
                                                        'text-bg-secondary' => $row['status'] === 'OUT',
                                                        'text-bg-light' => $row['status'] === 'NONE',
                                                    ])>
                                                        {{ __('dashboard.hours.statuses.'.$row['status']) }}
                                                    </span>
                                                </td>
                                                <td dir="ltr">{{ number_format($row['planned'], 2) }}h</td>
                                                <td dir="ltr">{{ number_format($row['actual'], 2) }}h</td>
                                                <td class="text-end" dir="ltr">
                                                    {{ $row['pct'] === null ? '—' : number_format($row['pct'], 1).'%' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('dashboard.hours.team_month') }}</h2>
                        <p class="small text-body-secondary mb-3">
                            {{ __('dashboard.hours.team_month_hint', ['month' => $calendar['label'] ?? '']) }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('shifts.employee') }}</th>
                                        <th scope="col">{{ __('dashboard.hours.month_planned') }}</th>
                                        <th scope="col">{{ __('dashboard.hours.month_actual') }}</th>
                                        <th scope="col" class="text-end">{{ __('dashboard.hours.pct') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($teamSummary as $row)
                                        @php
                                            $name = app()->getLocale() === 'ar' ? $row['user']->name_ar : $row['user']->name_en;
                                            $active = $focusUser && (int) $focusUser->id === (int) $row['user']->id;
                                        @endphp
                                        <tr @class(['table-active' => $active])>
                                            <td>
                                                <a href="{{ route($dashboardRoute, ['cal' => $calCursor, 'user_id' => $row['user']->id]) }}"
                                                   class="fw-semibold">{{ $name }}</a>
                                            </td>
                                            <td dir="ltr">{{ number_format($row['planned'], 2) }}h</td>
                                            <td dir="ltr">{{ number_format($row['actual'], 2) }}h</td>
                                            <td class="text-end" dir="ltr">
                                                {{ $row['pct'] === null ? '—' : number_format($row['pct'], 1).'%' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <form method="GET" action="{{ route($dashboardRoute) }}" class="row g-2 align-items-end">
                            <input type="hidden" name="cal" value="{{ $calCursor }}">
                            <div class="col-12 col-md-8">
                                <label for="user_id" class="form-label">{{ __('dashboard.hours.focus_employee') }}</label>
                                <select id="user_id" name="user_id" class="form-select">
                                    @foreach (($focusOptions ?? $teamMembers) as $member)
                                        <option value="{{ $member->id }}" @selected($focusUser && (int) $focusUser->id === (int) $member->id)>
                                            @if ((int) $member->id === (int) auth()->id())
                                                {{ __('dashboard.hours.focus_me') }}
                                            @else
                                                {{ app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4 d-grid">
                                <button type="submit" class="btn btn-outline-primary">{{ __('dashboard.hours.apply') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <x-month-calendar
                    :calendar="$calendar"
                    :prev-url="$calPrevUrl"
                    :next-url="$calNextUrl"
                    :title="$focusUser
                        ? __('dashboard.hours.calendar_for', [
                            'name' => (int) $focusUser->id === (int) auth()->id()
                                ? __('dashboard.hours.focus_me')
                                : (app()->getLocale() === 'ar' ? $focusUser->name_ar : $focusUser->name_en),
                        ])
                        : null"
                />
            </div>
        </div>

        @if ($hoursMonth !== null)
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <x-hours-day-table
                        :days="$hoursMonth['days']"
                        :totals="$hoursMonth['totals']"
                        :title="$focusUser
                            ? __('dashboard.hours.day_list_for', [
                                'name' => (int) $focusUser->id === (int) auth()->id()
                                    ? __('dashboard.hours.focus_me')
                                    : (app()->getLocale() === 'ar' ? $focusUser->name_ar : $focusUser->name_en),
                            ])
                            : null"
                    />
                </div>
            </div>
        @endif
    @endif
@endsection

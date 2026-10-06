@extends('layouts.app')

@section('title', __('nav.dashboard'))

@section('content')
    <div class="welcome-hero p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="h4 mb-1">
                {{ __('dashboard.welcome', ['name' => app()->getLocale() === 'ar' ? auth()->user()->name_ar : auth()->user()->name_en]) }}
            </h1>
            <p class="mb-0 opacity-75">{{ __('dashboard.welcome_text') }}</p>
        </div>
        <span class="hero-date">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ now()->translatedFormat('l, d F Y') }}
        </span>
    </div>

    @include('partials.dashboard.missing-reports-card')

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge text-primary" aria-hidden="true"></i>
                    <h2 class="h6 mb-0">{{ __('dashboard.quick_actions') }}</h2>
                </div>
                <div class="card-body pt-2">
                    @if ($quickActions === [])
                        <x-empty-state :title="__('dashboard.welcome_text')" icon="bi-compass" />
                    @else
                        <div class="row g-2">
                            @foreach ($quickActions as $action)
                                <div class="col-12 col-sm-6">
                                    <a class="quick-action" href="{{ $action['url'] }}">
                                        <span class="quick-action-icon bg-primary bg-opacity-10 text-primary">
                                            <i class="bi {{ $action['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="fw-semibold">{{ __($action['key']) }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-event text-success" aria-hidden="true"></i>
                    <h2 class="h6 mb-0">{{ __('dashboard.upcoming_holidays') }}</h2>
                </div>
                <div class="card-body pt-2">
                    @if ($upcomingHolidays->isEmpty())
                        <x-empty-state :title="__('dashboard.no_upcoming_holidays')" icon="bi-calendar-check" />
                    @else
                        <div data-table-filter-root>
                            <x-table-toolbar
                                id="upcoming-holidays"
                                :search-placeholder="__('general.search_placeholder')"
                            />
                            <div class="table-responsive">
                                <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('dashboard.upcoming_holidays') }}</th>
                                            <th scope="col" class="col-num">{{ __('dashboard.hours.date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($upcomingHolidays as $holiday)
                                            @php
                                                $name = app()->getLocale() === 'ar' ? $holiday->name_ar : $holiday->name_en;
                                            @endphp
                                            <tr data-filter-text="{{ $name }} {{ $holiday->name_ar }} {{ $holiday->name_en }} {{ $holiday->date->toDateString() }}">
                                                <td class="fw-semibold">{{ $name }}</td>
                                                <td class="col-num">
                                                    <span class="badge text-bg-success">
                                                        <span class="num-ltr">{{ $holiday->date->translatedFormat('d M') }}</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr data-table-empty-row class="d-none">
                                            <td colspan="2" class="text-body-secondary text-center py-3">
                                                {{ __('general.no_filter_results') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('team-leader.attendance') }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    {{ __('attendance.history.open') }}
                                </a>
                                <a href="{{ route('team-leader.shifts', ['date' => now()->toDateString()]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    {{ __('dashboard.team_intervals.manage') }}
                                </a>
                            </div>
                        </div>

                        @if ($todayStatus->isEmpty())
                            <x-empty-state
                                :title="__('dashboard.hours.no_team_title')"
                                :text="__('dashboard.hours.no_team_text')"
                                icon="bi-people"
                            />
                        @else
                            @include('partials.dashboard.today-board-table')
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

                        @if ($teamSummary->isEmpty())
                            <x-empty-state
                                :title="__('dashboard.hours.no_team_title')"
                                :text="__('dashboard.hours.no_team_text')"
                                icon="bi-people"
                            />
                        @else
                            @include('partials.dashboard.team-month-table')
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-3">
                        <form method="GET" action="{{ route($dashboardRoute) }}" class="row g-2 align-items-end">
                            <div class="col-12 col-md-5">
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
                            <div class="col-12 col-md-4">
                                <label for="cal" class="form-label">{{ __('dashboard.hours.select_month') }}</label>
                                <input id="cal" type="month" name="cal" class="form-control num-ltr" value="{{ $calCursor }}" required>
                            </div>
                            <div class="col-12 col-md-3 d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    {{ __('dashboard.hours.apply') }}
                                </button>
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
            @php
                $focusHoursName = $focusUser
                    ? ((int) $focusUser->id === (int) auth()->id()
                        ? __('dashboard.hours.focus_me')
                        : (app()->getLocale() === 'ar' ? $focusUser->name_ar : $focusUser->name_en))
                    : null;
            @endphp
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <x-hours-month-stats
                        :totals="$hoursMonth['totals']"
                        :ym="$hoursMonth['ym'] ?? null"
                        :name="$focusHoursName"
                        :form-action="route($dashboardRoute)"
                        :user-id="$focusUser?->id"
                    />
                </div>
                <div class="col-12">
                    <x-hours-day-table
                        :days="$hoursMonth['days']"
                        :totals="$hoursMonth['totals']"
                        :title="$focusHoursName
                            ? __('dashboard.hours.day_list_for', ['name' => $focusHoursName])
                            : null"
                    />
                </div>
            </div>
        @endif
    @else
        <div class="row g-3 mt-1">
            <div class="col-12">
                <x-month-calendar
                    :calendar="$calendar"
                    :prev-url="$calPrevUrl"
                    :next-url="$calNextUrl"
                />
            </div>
        </div>
    @endif
@endsection

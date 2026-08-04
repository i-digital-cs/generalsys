@extends('layouts.app')

@section('title', __('dashboard.employee.title'))

@section('content')
    <div class="welcome-hero p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="h4 mb-1">
                {{ __('dashboard.welcome', ['name' => app()->getLocale() === 'ar' ? auth()->user()->name_ar : auth()->user()->name_en]) }}
            </h1>
            <p class="mb-0 opacity-75">{{ __('dashboard.employee.subtitle') }}</p>
        </div>
        <span class="hero-date">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ now()->translatedFormat('l, d F Y') }}
        </span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <x-stat-card
                :title="__('dashboard.employee.status')"
                :value="$isClockedIn ? __('dashboard.employee.clocked_in') : __('dashboard.employee.clocked_out')"
                :icon="$isClockedIn ? 'bi-check-circle' : 'bi-pause-circle'"
                :color="$isClockedIn ? 'success' : 'secondary'"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-stat-card
                :title="__('dashboard.employee.hours_today')"
                :value="number_format($hoursToday, 2)"
                icon="bi-hourglass-split"
                color="info"
                :subtitle="__('dashboard.employee.sessions_count', ['count' => $todaySessions->count()])"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-stat-card
                :title="__('dashboard.hours.month_planned')"
                :value="number_format($hoursMonth['totals']['planned'], 2).'h'"
                icon="bi-calendar-check"
                color="primary"
            />
        </div>
        <div class="col-6 col-md-3">
            <x-stat-card
                :title="__('dashboard.hours.month_actual')"
                :value="number_format($hoursMonth['totals']['actual'], 2).'h'"
                icon="bi-graph-up"
                color="success"
                :subtitle="$hoursMonth['totals']['pct'] === null
                    ? '—'
                    : number_format($hoursMonth['totals']['pct'], 1).'%'"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <x-stat-card
                :title="__('dashboard.employee.preferred_shift')"
                :value="__('clock.shifts.'.$preferredShift)"
                icon="bi-briefcase"
                color="primary"
                :subtitle="$assignment
                    ? __('dashboard.employee.assigned_by_tl')
                    : __('dashboard.employee.from_default')"
            />
        </div>
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center text-lg-start">
                    <h2 class="h5 mb-2">{{ __('dashboard.employee.clock_card_title') }}</h2>
                    <p class="text-body-secondary mb-4">
                        @if ($isClockedIn)
                            {{ __('dashboard.employee.clock_card_open', [
                                'shift' => __('clock.shifts.'.$open->shift_type),
                                'time' => $open->clock_in?->timezone(config('app.timezone'))->format('H:i'),
                            ]) }}
                        @elseif ($assignment)
                            {{ __('dashboard.employee.clock_card_assigned', [
                                'shift' => __('clock.shifts.'.$assignment->shift_type),
                            ]) }}
                        @else
                            {{ __('dashboard.employee.clock_card_idle') }}
                        @endif
                    </p>

                    <a href="{{ route('employee.clock') }}"
                       class="btn btn-{{ $isClockedIn ? 'danger' : 'primary' }} btn-lg w-100 w-lg-auto clock-cta">
                        <i class="bi bi-{{ $isClockedIn ? 'box-arrow-left' : 'box-arrow-in-right' }} me-2"
                           aria-hidden="true"></i>
                        {{ $isClockedIn ? __('dashboard.employee.go_clock_out') : __('dashboard.employee.go_clock_in') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('dashboard.employee.today_sessions') }}</h2>

                    @if ($todaySessions->isEmpty())
                        <x-empty-state
                            :title="__('dashboard.employee.no_sessions')"
                            :text="__('dashboard.employee.no_sessions_text')"
                            icon="bi-clock-history"
                        />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($todaySessions as $session)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ __('clock.shifts.'.$session->shift_type) }}</div>
                                        <div class="small text-body-secondary" dir="ltr">
                                            {{ $session->clock_in?->format('H:i') ?? '—' }}
                                            →
                                            {{ $session->clock_out?->format('H:i') ?? __('dashboard.employee.open_session') }}
                                        </div>
                                        <x-attendance-flags :attendance="$session" class="mt-1" />
                                    </div>
                                    <span class="badge text-bg-{{ $session->clock_out ? 'secondary' : 'success' }}">
                                        {{ $session->clock_out
                                            ? number_format((float) $session->actual_hours, 2).'h'
                                            : __('dashboard.employee.open_session') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <x-month-calendar
                :calendar="$calendar"
                :prev-url="$calPrevUrl"
                :next-url="$calNextUrl"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <x-hours-day-table
                :days="$hoursMonth['days']"
                :totals="$hoursMonth['totals']"
            />
        </div>
    </div>
@endsection

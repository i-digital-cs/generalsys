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
                    @forelse ($upcomingHolidays as $holiday)
                        <div class="contract-row">
                            <span class="fw-semibold">
                                {{ app()->getLocale() === 'ar' ? $holiday->name_ar : $holiday->name_en }}
                            </span>
                            <span class="badge text-bg-success">
                                {{ $holiday->date->translatedFormat('d M') }}
                            </span>
                        </div>
                    @empty
                        <x-empty-state :title="__('dashboard.no_upcoming_holidays')" icon="bi-calendar-check" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

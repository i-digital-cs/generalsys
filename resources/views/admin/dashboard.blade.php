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
                    <a class="quick-action" href="{{ route('admin.employees.create') }}">
                        <span class="quick-action-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('employees.add_new') }}</span>
                    </a>
                    <a class="quick-action" href="{{ route('admin.employees.index') }}">
                        <span class="quick-action-icon bg-success bg-opacity-10 text-success">
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
@endsection

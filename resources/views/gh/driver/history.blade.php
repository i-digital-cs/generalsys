@extends('layouts.app')

@section('title', __('gh.driver.history'))

@section('content')
@php
    $clearSearchQuery = collect($filters ?? [])
        ->except('q')
        ->filter(fn ($v) => filled($v))
        ->all();
@endphp
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver.history') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver.history_hint') }}</p>
        </div>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    <form method="get" action="{{ route('gh.driver.history') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="filter_q">{{ __('gh.driver.search_order') }}</label>
                <div class="input-group">
                    <input id="filter_q" type="search" name="q" class="form-control"
                           value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gh.driver.search_order_placeholder') }}"
                           autocomplete="off"
                           inputmode="search">
                    @if ($hasActiveSearch ?? false)
                        <a href="{{ route('gh.driver.history', $clearSearchQuery) }}"
                           class="btn btn-outline-secondary"
                           title="{{ __('gh.driver.clear_search') }}">{{ __('gh.driver.clear_search') }}</a>
                    @endif
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="filter_type">{{ __('gh.driver.filter_type') }}</label>
                    <select id="filter_type" name="type" class="form-select form-select-sm">
                        <option value="">{{ __('gh.driver.filter_all') }}</option>
                        @foreach ($movementTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                                {{ __('gh.driver.movement_type.'.$type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="filter_status">{{ __('gh.driver.filter_status') }}</label>
                    <select id="filter_status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('gh.driver.filter_all') }}</option>
                        @foreach ($movementStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                {{ __('gh.driver.movement_status.'.$status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="filter_from">{{ __('gh.driver.filter_from') }}</label>
                    <input id="filter_from" type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="filter_to">{{ __('gh.driver.filter_to') }}</label>
                    <input id="filter_to" type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.driver.filter_apply') }}</button>
                    <a href="{{ route('gh.driver.history') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.filter_reset') }}</a>
                </div>
            </div>
        </div>
    </form>

    @forelse ($rows as $movement)
        @include('gh.driver.partials.movement-card', ['movement' => $movement, 'displayTz' => $displayTz])
    @empty
        <p class="text-body-secondary">
            {{ ($hasActiveSearch ?? false) || filled($filters['type'] ?? null) || filled($filters['status'] ?? null) || filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)
                ? __('gh.driver.empty_search')
                : __('gh.driver.empty_history') }}
        </p>
    @endforelse

    @if ($rows->hasPages())
        <div class="mt-3">{{ $rows->links() }}</div>
    @endif
</div>
@endsection

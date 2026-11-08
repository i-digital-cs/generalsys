@extends('layouts.app')

@section('title', __('gh.driver.dashboard'))

@section('content')
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver.dashboard') }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gh.driver.business_day') }}:
                <span class="num-ltr hp-value">{{ $business_date }}</span>
                ({{ $timezone }})
            </p>
        </div>
        <a href="{{ route('gh.driver.history') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.history') }}</a>
    </div>

    <section class="mb-4">
        <h2 class="h6 card-title mb-1">{{ __('gh.driver.today_pickups') }}</h2>
        @if ($actionable_pickups->count() > 1 || $pending_deliveries->count() > 1)
            <p class="text-body-secondary small mb-2">{{ __('gh.driver.sort_hint') }}</p>
        @endif
        <div data-gh-sortable
             data-reorder-url="{{ route('gh.driver.reorder-pickups') }}"
             data-error-message="{{ __('gh.driver.reorder_failed') }}">
            @forelse ($actionable_pickups as $order)
                @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'pickup', 'displayTz' => $displayTz, 'sortable' => true, 'position' => $loop->iteration])
            @empty
                <p class="text-body-secondary small">{{ __('gh.driver.empty_pickups') }}</p>
            @endforelse
        </div>
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-1">{{ __('gh.driver.upcoming_pickups') }}</h2>
        <p class="text-body-secondary small mb-2">{{ __('gh.driver.upcoming_info_hint', ['hours' => $lead_hours]) }}</p>
        @forelse ($upcoming_pickups as $order)
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'pickup', 'displayTz' => $displayTz, 'informational' => true])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_upcoming') }}</p>
        @endforelse
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.pending_deliveries') }}</h2>
        <div data-gh-sortable
             data-reorder-url="{{ route('gh.driver.reorder-deliveries') }}"
             data-error-message="{{ __('gh.driver.reorder_failed') }}">
            @forelse ($pending_deliveries as $order)
                @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'delivery', 'displayTz' => $displayTz, 'sortable' => true, 'position' => $loop->iteration])
            @empty
                <p class="text-body-secondary small">{{ __('gh.driver.empty_deliveries') }}</p>
            @endforelse
        </div>
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.failed_followups') }}</h2>
        @forelse ($failed_followups as $movement)
            @include('gh.driver.partials.movement-card', ['movement' => $movement, 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_failed') }}</p>
        @endforelse
    </section>

    <section class="mb-2">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.recent_completed') }}</h2>
        @forelse ($completed as $movement)
            @include('gh.driver.partials.movement-card', ['movement' => $movement, 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_completed') }}</p>
        @endforelse
    </section>
</div>
@endsection

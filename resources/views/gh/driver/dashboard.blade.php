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
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.today_pickups') }}</h2>
        @forelse ($today_pickups as $order)
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'pickup', 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_pickups') }}</p>
        @endforelse
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.upcoming_pickups') }}</h2>
        @forelse ($upcoming_pickups as $order)
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'pickup', 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_upcoming') }}</p>
        @endforelse
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.pending_deliveries') }}</h2>
        @forelse ($pending_deliveries as $order)
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => 'delivery', 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_deliveries') }}</p>
        @endforelse
    </section>

    <section class="mb-4">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.failed_followups') }}</h2>
        @forelse ($failed_followups as $order)
            @php
                $tt = (int) $order->delivery_driver_id === (int) auth()->id() && $order->status === \App\Support\GeneralHouse\GhOrderStatus::DELIVERY
                    ? 'delivery' : 'pickup';
            @endphp
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => $tt, 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_failed') }}</p>
        @endforelse
    </section>

    <section class="mb-2">
        <h2 class="h6 card-title mb-2">{{ __('gh.driver.recent_completed') }}</h2>
        @forelse ($completed as $order)
            @php
                $tt = (int) $order->delivery_driver_id === (int) auth()->id() ? 'delivery' : 'pickup';
            @endphp
            @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => $tt, 'displayTz' => $displayTz])
        @empty
            <p class="text-body-secondary small">{{ __('gh.driver.empty_completed') }}</p>
        @endforelse
    </section>
</div>
@endsection

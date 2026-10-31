@extends('layouts.app')

@section('title', __('gh.driver.history'))

@section('content')
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.driver.history') }}</h1>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    @forelse ($rows as $order)
        @php
            $tt = (int) $order->delivery_driver_id === (int) auth()->id() ? 'delivery' : 'pickup';
        @endphp
        @include('gh.driver.partials.task-card', ['order' => $order, 'taskType' => $tt, 'displayTz' => $displayTz])
    @empty
        <p class="text-body-secondary">{{ __('gh.driver.empty_history') }}</p>
    @endforelse

    @if ($rows->hasPages())
        <div class="mt-3">{{ $rows->links() }}</div>
    @endif
</div>
@endsection

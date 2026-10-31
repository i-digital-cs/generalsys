@extends('layouts.app')
@section('title', __('gh.reports.orders'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.orders') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters', ['exportRoute' => 'gh.reports.orders'])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.orders.reference') }}</th>
                    <th>{{ __('gh.reports.customer') }}</th>
                    <th>{{ __('gh.reports.customer_type') }}</th>
                    <th>{{ __('gh.reports.zone') }}</th>
                    <th>{{ __('gh.reports.order_status') }}</th>
                    <th>{{ __('gh.orders.pickup_window') }}</th>
                    <th>{{ __('gh.orders.pickup_driver') }}</th>
                    <th>{{ __('gh.orders.delivery_driver') }}</th>
                    <th>{{ __('gh.reports.created') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $o)
                    <tr>
                        <td><a class="num-ltr" href="{{ route('gh.orders.show', $o) }}">{{ $o->reference }}</a></td>
                        <td>{{ $o->customer?->localizedName() }}</td>
                        <td>{{ $o->customer?->type ? __('gh.customer_type.'.$o->customer->type) : '—' }}</td>
                        <td>{{ $o->zone?->localizedName() }}</td>
                        <td>{{ __('gh.order_status.'.$o->status) }}</td>
                        <td class="num-ltr small">
                            {{ $o->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}
                            – {{ $o->pickup_ends_at?->timezone($displayTz)->translatedFormat('H:i') }}
                        </td>
                        <td>{{ $o->pickupDriver?->name_en ?: $o->pickupDriver?->name_ar ?: '—' }}</td>
                        <td>{{ $o->deliveryDriver?->name_en ?: $o->deliveryDriver?->name_ar ?: '—' }}</td>
                        <td class="num-ltr small">{{ $o->created_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-body">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection

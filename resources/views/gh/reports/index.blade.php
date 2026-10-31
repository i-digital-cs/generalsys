@extends('layouts.app')

@section('title', __('gh.reports.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.reports.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.reports.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.dashboard.title') }}</a>
    </div>
    <div class="row g-3">
        @foreach ([
            ['route' => 'gh.reports.invoices', 'key' => 'invoices', 'icon' => 'bi-receipt'],
            ['route' => 'gh.reports.collections', 'key' => 'collections', 'icon' => 'bi-cash-coin'],
            ['route' => 'gh.reports.outstanding', 'key' => 'outstanding', 'icon' => 'bi-exclamation-circle'],
            ['route' => 'gh.reports.b2b', 'key' => 'b2b', 'icon' => 'bi-building'],
            ['route' => 'gh.reports.orders', 'key' => 'orders', 'icon' => 'bi-clipboard-check'],
            ['route' => 'gh.reports.pickup-performance', 'key' => 'pickup_performance', 'icon' => 'bi-box-seam'],
            ['route' => 'gh.reports.delivery-performance', 'key' => 'delivery_performance', 'icon' => 'bi-truck'],
        ] as $item)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route($item['route']) }}" class="card border-0 shadow-sm text-decoration-none text-body h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-icon d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary">
                            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <span class="fw-semibold">{{ __('gh.reports.'.$item['key']) }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection

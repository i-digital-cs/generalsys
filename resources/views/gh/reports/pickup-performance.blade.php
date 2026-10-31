@extends('layouts.app')
@section('title', __('gh.reports.pickup_performance'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.pickup_performance') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters')
    <div class="row g-3">
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.scheduled')" :value="(string)$metrics['scheduled']" icon="bi-calendar" color="primary" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.completed_pickups')" :value="(string)$metrics['completed']" icon="bi-check2" color="success" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.failed_pickup_attempts')" :value="(string)$metrics['failed_attempts']" icon="bi-x-circle" color="warning" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.overdue_pickups')" :value="(string)$metrics['overdue']" icon="bi-alarm" color="danger" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4">
            <x-stat-card :title="__('gh.reports.avg_pickup_lag')" :value="$metrics['avg_pickup_lag_minutes'] === null ? __('gh.dashboard.na') : (string)$metrics['avg_pickup_lag_minutes']" icon="bi-stopwatch" color="info" :show-sparkline="false" />
        </div>
    </div>
</div>
@endsection

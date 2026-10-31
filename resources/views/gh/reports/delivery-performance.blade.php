@extends('layouts.app')
@section('title', __('gh.reports.delivery_performance'))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.delivery_performance') }}</h1>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
    </div>
    @include('gh.reports.partials.filters')
    <div class="alert alert-secondary small">{{ $metrics['overdue_note'] }}</div>
    <div class="row g-3">
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.delivery_assignments')" :value="(string)$metrics['assignments']" icon="bi-person-check" color="primary" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.completed_deliveries')" :value="(string)$metrics['completed']" icon="bi-check2-all" color="success" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.failed_delivery_attempts')" :value="(string)$metrics['failed_attempts']" icon="bi-x-circle" color="warning" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4"><x-stat-card :title="__('gh.reports.pending_deliveries')" :value="(string)$metrics['pending']" icon="bi-hourglass" color="info" :show-sparkline="false" /></div>
        <div class="col-6 col-md-4">
            <x-stat-card :title="__('gh.reports.avg_delivery_hours')" :value="$metrics['avg_delivery_hours'] === null ? __('gh.dashboard.na') : (string)$metrics['avg_delivery_hours']" icon="bi-stopwatch" color="secondary" :show-sparkline="false" />
        </div>
        <div class="col-6 col-md-4">
            <x-stat-card :title="__('gh.dashboard.kpi.overdue_deliveries')" :value="__('gh.dashboard.unavailable')" icon="bi-question-circle" color="secondary" :show-sparkline="false" />
        </div>
    </div>
</div>
@endsection

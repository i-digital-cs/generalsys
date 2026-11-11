@extends('layouts.app')

@section('title', __('gh.driver.dashboard'))

@section('content')
<div class="container-fluid py-2 gh-order-board">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver.dashboard') }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gh.driver.business_day') }}:
                <span class="num-ltr hp-value">{{ $business_date }}</span>
                ({{ $timezone }})
            </p>
        </div>
        <a href="{{ route('gh.driver.history') }}" class="gh-board-btn gh-board-btn--ghost">{{ __('gh.driver.history') }}</a>
    </div>

    @include('gh.partials.order-board-tabs', [
        'tabs' => $board['tabs'],
        'activeTab' => $board['tab'],
        'baseUrl' => route('gh.driver.dashboard'),
    ])

    @if ($board['sortable'])
        <p class="text-body-secondary small mb-2">{{ __('gh.driver.sort_hint') }}</p>
    @endif

    @if ($board['read_only'] && $board['tab'] === 'working')
        <div class="alert alert-info small py-2" role="status">{{ __('gh.board.working_readonly_hint') }}</div>
    @endif

    @include('gh.partials.order-board-groups', [
        'groups' => $board['groups'],
        'role' => 'driver',
        'sortable' => $board['sortable'],
        'reorderUrl' => $board['reorder_url'],
    ])

    @include('gh.partials.order-board-pager', [
        'pager' => $board['pager'] ?? null,
        'baseUrl' => route('gh.driver.dashboard'),
    ])
</div>
@endsection

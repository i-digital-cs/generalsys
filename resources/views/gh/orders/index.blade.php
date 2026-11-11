@extends('layouts.app')

@section('title', __('gh.orders.title'))

@section('content')
    @php
        $secondaryOpen = filled($filters['zone_id'] ?? null) || filled($filters['pickup_driver_id'] ?? null);
    @endphp
    <div class="gh-order-board">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
            <div>
                <h1 class="h4 mb-1">{{ __('gh.orders.title') }}</h1>
                <p class="text-body-secondary small mb-0">{{ __('gh.orders.subtitle') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('create', App\Models\GeneralHouse\GhOrder::class)
                    <a href="{{ route('gh.orders.create') }}" class="gh-board-btn">{{ __('gh.orders.create') }}</a>
                @endcan
            </div>
        </div>

        @include('gh.partials.order-board-tabs', [
            'tabs' => $board['tabs'],
            'activeTab' => $board['tab'],
            'baseUrl' => route('gh.orders.index'),
        ])

        <div class="card gh-board-filters border-0 mb-2">
            <div class="card-body">
                <form method="get" class="gh-board-filter-form" data-gh-board-filters>
                    <input type="hidden" name="tab" value="{{ $board['tab'] }}">
                    <div class="gh-board-filters-primary">
                        <div class="gh-board-filter-search">
                            <label class="form-label mb-1" for="q">{{ __('gh.orders.customer_search') }}</label>
                            <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                                   placeholder="{{ __('gh.board.search_placeholder') }}">
                        </div>

                        <button
                            class="gh-board-btn gh-board-btn--ghost gh-board-filters-toggle"
                            type="button"
                            data-gh-filters-toggle
                            aria-expanded="{{ $secondaryOpen ? 'true' : 'false' }}"
                            aria-controls="gh-board-filters-secondary"
                        >{{ __('gh.board.more_filters') }}</button>

                        <div
                            id="gh-board-filters-secondary"
                            class="gh-board-filters-secondary {{ $secondaryOpen ? 'is-open' : '' }}"
                            data-gh-filters-secondary
                        >
                            <div class="gh-board-filter-field">
                                <label class="form-label mb-1" for="zone_id">{{ __('gh.orders.zone') }}</label>
                                <select id="zone_id" name="zone_id" class="form-select form-select-sm">
                                    <option value="">{{ __('general.all') }}</option>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" @selected((string) ($filters['zone_id'] ?? '') === (string) $zone->id)>{{ $zone->localizedName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="gh-board-filter-field">
                                <label class="form-label mb-1" for="pickup_driver_id">{{ __('gh.orders.pickup_driver') }}</label>
                                <select id="pickup_driver_id" name="pickup_driver_id" class="form-select form-select-sm">
                                    <option value="">{{ __('general.all') }}</option>
                                    @foreach ($drivers as $driver)
                                        <option value="{{ $driver->id }}" @selected((string) ($filters['pickup_driver_id'] ?? '') === (string) $driver->id)>{{ $driver->localizedName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <button class="gh-board-btn gh-board-btn--ghost" type="submit">{{ __('general.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('gh.partials.order-board-groups', [
            'groups' => $board['groups'],
            'role' => 'admin',
            'sortable' => false,
        ])

        @include('gh.partials.order-board-pager', [
            'pager' => $board['pager'] ?? null,
            'baseUrl' => route('gh.orders.index'),
        ])
    </div>
@endsection

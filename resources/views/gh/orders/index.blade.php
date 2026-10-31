@extends('layouts.app')

@section('title', __('gh.orders.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.orders.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.orders.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\GeneralHouse\GhOrder::class)
                <a href="{{ route('gh.orders.create') }}" class="btn btn-primary btn-sm">{{ __('gh.orders.create') }}</a>
            @endcan
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="reference">{{ __('gh.orders.reference') }}</label>
                    <input id="reference" name="reference" class="form-control form-control-sm" value="{{ $filters['reference'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="customer">{{ __('gh.orders.customer_search') }}</label>
                    <input id="customer" name="customer" class="form-control form-control-sm" value="{{ $filters['customer'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="status">{{ __('gh.orders.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.order_status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="zone_id">{{ __('gh.orders.zone') }}</label>
                    <select id="zone_id" name="zone_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected((string) ($filters['zone_id'] ?? '') === (string) $zone->id)>{{ $zone->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="pickup_driver_id">{{ __('gh.orders.pickup_driver') }}</label>
                    <select id="pickup_driver_id" name="pickup_driver_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) ($filters['pickup_driver_id'] ?? '') === (string) $driver->id)>{{ $driver->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="pickup_from">{{ __('gh.orders.pickup_from') }}</label>
                    <input id="pickup_from" name="pickup_from" type="date" class="form-control form-control-sm" value="{{ $filters['pickup_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="pickup_to">{{ __('gh.orders.pickup_to') }}</label>
                    <input id="pickup_to" name="pickup_to" type="date" class="form-control form-control-sm" value="{{ $filters['pickup_to'] ?? '' }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('general.filter') }}</button>
                </div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.orders.empty')" icon="bi-clipboard-check" :text="__('gh.orders.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('gh.orders.reference') }}</th>
                            <th>{{ __('gh.fields.customer') }}</th>
                            <th>{{ __('gh.orders.status') }}</th>
                            <th>{{ __('gh.orders.zone') }}</th>
                            <th>{{ __('gh.orders.pickup_window') }}</th>
                            <th>{{ __('gh.orders.pickup_driver') }}</th>
                            <th class="text-end">{{ __('general.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><a href="{{ route('gh.orders.show', $row) }}" class="hp-value">{{ $row->reference }}</a></td>
                                <td>{{ $row->customer?->localizedName() }}</td>
                                <td><span class="badge text-bg-secondary">{{ __('gh.order_status.'.$row->status) }}</span></td>
                                <td>{{ $row->zone?->localizedName() }}</td>
                                <td class="num-ltr small">
                                    {{ $row->pickup_starts_at?->timezone($row->timezone)->translatedFormat('Y-m-d H:i') }}
                                    –
                                    {{ $row->pickup_ends_at?->timezone($row->timezone)->format('H:i') }}
                                </td>
                                <td>{{ $row->pickupDriver?->localizedName() ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('gh.orders.show', $row) }}" class="btn btn-link btn-sm">{{ __('gh.orders.show') }}</a>
                                    @can('update', $row)
                                        <a href="{{ route('gh.orders.edit', $row) }}" class="btn btn-link btn-sm"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
@endsection

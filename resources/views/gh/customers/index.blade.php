@extends('layouts.app')

@section('title', __('gh.customers.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.customers.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.customers.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\GeneralHouse\GhCustomer::class)
                <a href="{{ route('gh.customers.create') }}" class="btn btn-primary btn-sm">{{ __('gh.customers.create') }}</a>
            @endcan
            <a href="{{ route('gh.zones.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.zones.title') }}</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('gh.customers.search_placeholder') }}"></div>
                <div class="col-md-2"><label class="form-label mb-1" for="type">{{ __('gh.fields.type') }}</label>
                    <select id="type" name="type" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhCustomerType::ALL as $t)
                            <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ __('gh.customer_type.'.$t) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-md-2"><label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-auto"><button class="btn btn-outline-primary btn-sm" type="submit">{{ __('general.filter') }}</button></div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.customers.empty')" icon="bi-people" :text="__('gh.customers.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.fields.name_ar') }}</th><th>{{ __('gh.fields.type') }}</th>
                            <th>{{ __('gh.fields.phone') }}</th><th>{{ __('gh.fields.status') }}</th>
                            <th class="text-end">{{ __('general.actions') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><a href="{{ route('gh.customers.show', $row) }}">{{ $row->localizedName() }}</a></td>
                                <td><span class="badge text-bg-secondary">{{ __('gh.customer_type.'.$row->type) }}</span></td>
                                <td class="hp-value num-ltr">{{ $row->phone }}</td>
                                <td>@include('gh.partials.status-badge', ['status' => $row->status])</td>
                                <td class="text-end">
                                    <a href="{{ route('gh.customers.show', $row) }}" class="btn btn-link btn-sm">{{ __('gh.customers.show') }}</a>
                                    @can('update', $row)<a href="{{ route('gh.customers.edit', $row) }}" class="btn btn-link btn-sm"><i class="bi bi-pencil"></i></a>@endcan
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
    @include('gh.import.partials.import-card', ['entity' => 'customers', 'model' => App\Models\GeneralHouse\GhCustomer::class])
@endsection

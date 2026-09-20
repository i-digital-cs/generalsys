@extends('layouts.app')

@section('title', __('gross.items.title'))

@section('content')
    @php
        $hasFilters = ($filters['q'] ?? '') !== ''
            || ! empty($filters['color_id'])
            || ! empty($filters['size_id'])
            || ! empty($filters['model_id'])
            || ! empty($filters['category_id'])
            || ! empty($filters['low_stock']);
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.items.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.items.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossItem::class)
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('gross.items.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.items.template') }}
                </a>
                <a href="{{ route('gross.items.create') }}" class="btn btn-primary btn-sm">{{ __('gross.items.create') }}</a>
            </div>
        @endcan
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gross.items.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label mb-1" for="category_id">{{ __('gross.fields.category') }}</label>
                    <select id="category_id" name="category_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $cat->id)>
                                {{ $cat->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label mb-1" for="color_id">{{ __('gross.fields.color') }}</label>
                    <select id="color_id" name="color_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($colors as $opt)
                            <option value="{{ $opt->id }}" @selected((string) ($filters['color_id'] ?? '') === (string) $opt->id)>
                                {{ $opt->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-6">
                    <label class="form-label mb-1" for="size_id">{{ __('gross.fields.size') }}</label>
                    <select id="size_id" name="size_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($sizes as $opt)
                            <option value="{{ $opt->id }}" @selected((string) ($filters['size_id'] ?? '') === (string) $opt->id)>
                                {{ $opt->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 col-6">
                    <label class="form-label mb-1" for="model_id">{{ __('gross.fields.model') }}</label>
                    <select id="model_id" name="model_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($models as $opt)
                            <option value="{{ $opt->id }}" @selected((string) ($filters['model_id'] ?? '') === (string) $opt->id)>
                                {{ $opt->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-auto col-md-auto">
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="low_stock"
                               @checked(! empty($filters['low_stock']))>
                        <label class="form-check-label" for="low_stock">{{ __('gross.fields.low_stock') }}</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.items.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($items->isEmpty())
                <x-empty-state
                    :title="$hasFilters ? __('general.no_filter_results') : __('gross.items.empty')"
                    icon="bi-box-seam" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 3.5rem"></th>
                                <th>{{ __('gross.fields.code') }}</th>
                                <th>{{ __('gross.fields.name') }}</th>
                                <th>{{ __('gross.fields.color') }}</th>
                                <th>{{ __('gross.fields.size') }}</th>
                                <th>{{ __('gross.fields.model') }}</th>
                                <th class="col-num">{{ __('gross.fields.on_hand') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr @class(['table-warning' => $item->isLowStock()])>
                                    <td>
                                        <x-gross.item-thumb :item="$item" size="2.5rem" />
                                    </td>
                                    <td class="fw-semibold"><span class="num-ltr">{{ $item->code }}</span></td>
                                    <td>
                                        {{ $item->name }}
                                        @if ($item->isLowStock())
                                            <span class="badge text-bg-warning ms-1">{{ __('gross.fields.low_stock') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->colorOption?->localizedName() ?? '—' }}</td>
                                    <td>{{ $item->sizeOption?->localizedName() ?? '—' }}</td>
                                    <td>{{ $item->modelOption?->localizedName() ?? '—' }}</td>
                                    <td class="col-num"><span class="num-ltr">{{ $item->balance?->qty_on_hand_pieces ?? 0 }}</span></td>
                                    <td>{{ __('gross.statuses.'.$item->status) }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('gross.items.show', $item) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                        @can('update', $item)
                                            <a href="{{ route('gross.items.edit', $item) }}" class="btn btn-sm btn-outline-secondary">{{ __('general.edit') }}</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Gross\GrossItem::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.items.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.items.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.items.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.items.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.items.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="item_file">{{ __('gross.items.import_file') }}</label>
                        <input id="item_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.items.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

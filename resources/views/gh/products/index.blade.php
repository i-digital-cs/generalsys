@extends('layouts.app')

@section('title', __('gh.products.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.products.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.products.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('create', App\Models\GeneralHouse\GhProduct::class)
                <a href="{{ route('gh.products.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('gh.products.create') }}
                </a>
            @endcan
            <a href="{{ route('gh.offerings.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.offerings.title') }}</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                           placeholder="{{ __('gh.products.search_placeholder') }}" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="category_id">{{ __('gh.fields.category') }}</label>
                    <select id="category_id" name="category_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $cat->id)>
                                {{ $cat->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" for="filter_status">{{ __('gh.fields.status') }}</label>
                    <select id="filter_status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['category_id'] ?? '') !== '')
                        <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.products.empty')" icon="bi-box-seam" :text="__('gh.products.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gh.fields.code') }}</th>
                                <th>{{ __('gh.fields.name_ar') }}</th>
                                <th>{{ __('gh.fields.name_en') }}</th>
                                <th>{{ __('gh.fields.category') }}</th>
                                <th>{{ __('gh.fields.status') }}</th>
                                <th class="text-end" style="width: 6rem">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="hp-value"><code>{{ $row->code }}</code></td>
                                    <td>{{ $row->name_ar }}</td>
                                    <td>{{ $row->name_en }}</td>
                                    <td>{{ $row->category?->localizedName() }}</td>
                                    <td>@include('gh.partials.status-badge', ['status' => $row->status])</td>
                                    <td class="text-end text-nowrap">
                                        @can('update', $row)
                                            <a href="{{ route('gh.products.edit', $row) }}" class="btn btn-link btn-sm text-body-secondary p-0 me-2"
                                               title="{{ __('general.edit') }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="visually-hidden">{{ __('general.edit') }}</span>
                                            </a>
                                            <form method="post" action="{{ route('gh.products.destroy', $row) }}" class="d-inline"
                                                  onsubmit="return confirm(@json(__('gh.products.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="{{ __('general.delete') }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('general.delete') }}</span>
                                                </button>
                                            </form>
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
    @include('gh.import.partials.import-card', ['entity' => 'products', 'model' => App\Models\GeneralHouse\GhProduct::class, 'withZip' => true])
@endsection

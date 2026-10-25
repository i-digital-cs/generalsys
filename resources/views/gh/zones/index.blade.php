@extends('layouts.app')

@section('title', __('gh.zones.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.zones.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.zones.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.customers.title') }}</a>
    </div>
    <p class="small text-body-secondary">{{ __('gh.zones.policy_hint') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @can('create', App\Models\GeneralHouse\GhZone::class)
                <form method="post" action="{{ route('gh.zones.store') }}" class="row g-2 align-items-end mb-3 pb-3 border-bottom">
                    @csrf
                    <div class="col-md-2"><label class="form-label mb-1" for="name_ar">{{ __('gh.fields.name_ar') }}</label>
                        <input id="name_ar" name="name_ar" class="form-control form-control-sm" value="{{ old('name_ar') }}" required maxlength="120"></div>
                    <div class="col-md-2"><label class="form-label mb-1" for="name_en">{{ __('gh.fields.name_en') }}</label>
                        <input id="name_en" name="name_en" class="form-control form-control-sm" value="{{ old('name_en') }}" required maxlength="120"></div>
                    <div class="col-md-2"><label class="form-label mb-1" for="min_order_amount">{{ __('gh.fields.min_order_amount') }}</label>
                        <input id="min_order_amount" name="min_order_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ old('min_order_amount', '0.00') }}" required></div>
                    <div class="col-md-2"><label class="form-label mb-1" for="min_order_policy">{{ __('gh.fields.min_order_policy') }}</label>
                        <select id="min_order_policy" name="min_order_policy" class="form-select form-select-sm">
                            @foreach (\App\Support\GeneralHouse\GhMinOrderPolicy::ALL as $p)
                                <option value="{{ $p }}" @selected(old('min_order_policy', 'SURCHARGE') === $p)>{{ __('gh.min_order_policy.'.$p) }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-2"><label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(old('status', 'ACTIVE') === $st)>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-1"><label class="form-label mb-1" for="sort_order">{{ __('gh.fields.sort_order') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" class="form-control form-control-sm" value="{{ old('sort_order', 0) }}"></div>
                    <div class="col-md-auto"><button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.zones.create') }}</button></div>
                </form>
            @endcan

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('gh.zones.search_placeholder') }}"></div>
                <div class="col-md-3"><label class="form-label mb-1" for="filter_status">{{ __('gh.fields.status') }}</label>
                    <select id="filter_status" name="status" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ __('gh.status.'.$st) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button></div>
            </form>

            @if ($rows->isEmpty())
                <x-empty-state :title="__('gh.zones.empty')" icon="bi-geo-alt" :text="__('gh.zones.empty_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.fields.name_ar') }}</th><th>{{ __('gh.fields.name_en') }}</th>
                            <th>{{ __('gh.fields.min_order_amount') }}</th><th>{{ __('gh.fields.min_order_policy') }}</th>
                            <th>{{ __('gh.fields.status') }}</th><th class="text-end">{{ __('general.actions') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row->name_ar }}</td><td>{{ $row->name_en }}</td>
                                <td class="hp-value num-ltr">{{ number_format((float) $row->min_order_amount, 2) }}</td>
                                <td>{{ __('gh.min_order_policy.'.$row->min_order_policy) }}</td>
                                <td>@include('gh.partials.status-badge', ['status' => $row->status])</td>
                                <td class="text-end text-nowrap">
                                    @can('update', $row)
                                        <button type="button" class="btn btn-link btn-sm p-0 me-2" data-bs-toggle="modal" data-bs-target="#gh-zone-edit-modal"
                                            data-action="{{ route('gh.zones.update', $row) }}" data-name-ar="{{ $row->name_ar }}" data-name-en="{{ $row->name_en }}"
                                            data-amount="{{ $row->min_order_amount }}" data-policy="{{ $row->min_order_policy }}" data-status="{{ $row->status }}" data-sort="{{ $row->sort_order }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i></button>
                                        <form method="post" action="{{ route('gh.zones.destroy', $row) }}" class="d-inline" onsubmit="return confirm(@json(__('gh.zones.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i></button>
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

    @can('create', App\Models\GeneralHouse\GhZone::class)
        <div class="modal fade" id="gh-zone-edit-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="post" id="gh-zone-edit-form" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header py-2"><h2 class="modal-title h6 mb-0">{{ __('gh.zones.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-md-6"><label class="form-label" for="z-ar">{{ __('gh.fields.name_ar') }}</label><input id="z-ar" name="name_ar" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="z-en">{{ __('gh.fields.name_en') }}</label><input id="z-en" name="name_en" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label" for="z-amt">{{ __('gh.fields.min_order_amount') }}</label><input id="z-amt" name="min_order_amount" type="number" step="0.01" min="0" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label" for="z-pol">{{ __('gh.fields.min_order_policy') }}</label>
                            <select id="z-pol" name="min_order_policy" class="form-select">@foreach (\App\Support\GeneralHouse\GhMinOrderPolicy::ALL as $p)<option value="{{ $p }}">{{ __('gh.min_order_policy.'.$p) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label" for="z-st">{{ __('gh.fields.status') }}</label>
                            <select id="z-st" name="status" class="form-select">@foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)<option value="{{ $st }}">{{ __('gh.status.'.$st) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label" for="z-sort">{{ __('gh.fields.sort_order') }}</label><input id="z-sort" name="sort_order" type="number" min="0" class="form-control"></div>
                    </div>
                    <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('general.cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('general.save') }}</button></div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                document.getElementById('gh-zone-edit-modal')?.addEventListener('show.bs.modal', function (e) {
                    const b = e.relatedTarget; if (!b) return;
                    document.getElementById('gh-zone-edit-form').action = b.dataset.action || '';
                    document.getElementById('z-ar').value = b.dataset.nameAr || '';
                    document.getElementById('z-en').value = b.dataset.nameEn || '';
                    document.getElementById('z-amt').value = b.dataset.amount || '0';
                    document.getElementById('z-pol').value = b.dataset.policy || 'SURCHARGE';
                    document.getElementById('z-st').value = b.dataset.status || 'ACTIVE';
                    document.getElementById('z-sort').value = b.dataset.sort || '0';
                });
            </script>
        @endpush
    @endcan
@endsection

{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.offerings.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.offerings.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.products.title') }}</a>
            <a href="{{ route('gh.treatments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.treatments.title') }}</a>
        </div>
    </div>

    <p class="small text-body-secondary">{{ __('gh.offerings.price_mode_hint') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('create', App\Models\GeneralHouse\GhProductOffering::class)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="post" action="{{ route('gh.offerings.store') }}" class="gh-offerings-create">
                    @csrf
                    <div class="gh-offerings-create__field">
                        <label class="form-label mb-1" for="product_id">{{ __('gh.fields.product') }}</label>
                        <select id="product_id" name="product_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                    {{ $product->code }} — {{ $product->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gh-offerings-create__field">
                        <label class="form-label mb-1" for="treatment_id">{{ __('gh.fields.treatment') }}</label>
                        <select id="treatment_id" name="treatment_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($treatments as $treatment)
                                <option value="{{ $treatment->id }}" @selected((string) old('treatment_id') === (string) $treatment->id)>
                                    {{ $treatment->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gh-offerings-create__field">
                        <label class="form-label mb-1" for="standard_price">{{ __('gh.fields.standard_price') }}</label>
                        <input id="standard_price" name="standard_price" type="number" step="0.01" min="0"
                               class="form-control form-control-sm" value="{{ old('standard_price') }}" required>
                        <input type="hidden" name="tax_class" value="{{ old('tax_class', config('general_house.default_offering_tax_class', 'STANDARD_RATED')) }}">
                    </div>
                    <div class="gh-offerings-create__field">
                        <label class="form-label mb-1" for="status">{{ __('gh.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                <option value="{{ $st }}" @selected(old('status', 'ACTIVE') === $st)>{{ __('gh.status.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gh-offerings-create__field">
                        <label class="form-label mb-1" for="sort_order">{{ __('gh.fields.sort_order') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" class="form-control form-control-sm"
                               value="{{ old('sort_order', 0) }}">
                    </div>
                    <div class="gh-offerings-create__action">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.offerings.create') }}</button>
                    </div>
                    <p class="gh-offerings-create__hint form-text mb-0">{{ __('gh.offerings.tax_default_hint') }}</p>
                </form>
            </div>
        </div>
    @endcan

    <div class="gh-card-ui" data-gh-card-ui="offerings">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="q">{{ __('general.search') }}</label>
                        <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"
                               placeholder="{{ __('gh.offerings.search_placeholder') }}" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1" for="filter_product">{{ __('gh.fields.product') }}</label>
                        <select id="filter_product" name="product_id" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->localizedName() }}
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
                        @if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['product_id'] ?? '') !== '')
                            <a href="{{ route('gh.offerings.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <x-empty-state :title="__('gh.offerings.empty')" icon="bi-tag" :text="__('gh.offerings.empty_hint')" />
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    @php
                        $productTitle = trim(($row->product?->code ? $row->product->code.' — ' : '').($row->product?->localizedName() ?? '—'));
                        $priceLabel = number_format((float) $row->standard_price, 2).' '.$row->currency;
                    @endphp
                    <x-gh.record-card :title="$productTitle">
                        <x-slot:badges>
                            @include('gh.partials.status-badge', ['status' => $row->status])
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-droplet" :value="$row->treatment?->localizedName() ?? '—'" />
                            <x-gh.meta-cell icon="bi-cash-coin" :value="$priceLabel" :ltr="true" />
                            <x-gh.meta-cell icon="bi-percent" :value="__('gh.tax_class.'.$row->tax_class)" />
                        </x-slot:meta>
                        <x-slot:actions>
                            @can('update', $row)
                                <button type="button" class="gh-board-btn gh-board-btn--ghost"
                                        data-bs-toggle="modal" data-bs-target="#gh-offering-edit-modal"
                                        data-action="{{ route('gh.offerings.update', $row) }}"
                                        data-product-id="{{ $row->product_id }}"
                                        data-treatment-id="{{ $row->treatment_id }}"
                                        data-price="{{ $row->standard_price }}"
                                        data-tax-class="{{ $row->tax_class }}"
                                        data-status="{{ $row->status }}"
                                        data-sort="{{ $row->sort_order }}">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span>{{ __('general.edit') }}</span>
                                </button>
                                <form method="post" action="{{ route('gh.offerings.destroy', $row) }}" class="d-inline"
                                      onsubmit="return confirm(@json(__('gh.offerings.confirm_delete')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="gh-board-btn gh-board-btn--ghost text-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                        <span>{{ __('general.delete') }}</span>
                                    </button>
                                </form>
                            @endcan
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
        @endif
    </div>

    @can('create', App\Models\GeneralHouse\GhProductOffering::class)
        <div class="modal fade" id="gh-offering-edit-modal" tabindex="-1" aria-labelledby="gh-offering-edit-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="post" id="gh-offering-edit-form" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header py-2">
                        <h2 class="modal-title h6 mb-0" id="gh-offering-edit-title">{{ __('gh.offerings.edit') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="gh-off-edit-product">{{ __('gh.fields.product') }}</label>
                                <select id="gh-off-edit-product" name="product_id" class="form-select" required>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->localizedName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gh-off-edit-treatment">{{ __('gh.fields.treatment') }}</label>
                                <select id="gh-off-edit-treatment" name="treatment_id" class="form-select" required>
                                    @foreach ($treatments as $treatment)
                                        <option value="{{ $treatment->id }}">{{ $treatment->localizedName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="gh-off-edit-price">{{ __('gh.fields.standard_price') }}</label>
                                <input id="gh-off-edit-price" name="standard_price" type="number" step="0.01" min="0" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="gh-off-edit-status">{{ __('gh.fields.status') }}</label>
                                <select id="gh-off-edit-status" name="status" class="form-select">
                                    @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                                        <option value="{{ $st }}">{{ __('gh.status.'.$st) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="gh-off-edit-sort">{{ __('gh.fields.sort_order') }}</label>
                                <input id="gh-off-edit-sort" name="sort_order" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-12">
                                <details class="border rounded p-2">
                                    <summary class="small fw-semibold">{{ __('gh.offerings.advanced_tax') }}</summary>
                                    <p class="small text-body-secondary mb-2">{{ __('gh.offerings.advanced_tax_hint') }}</p>
                                    <label class="form-label" for="gh-off-edit-tax">{{ __('gh.fields.tax_class') }}</label>
                                    <select id="gh-off-edit-tax" name="tax_class" class="form-select" required>
                                        @foreach ($taxClasses as $tc)
                                            <option value="{{ $tc }}">{{ __('gh.tax_class.'.$tc) }}</option>
                                        @endforeach
                                    </select>
                                </details>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('general.cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('general.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                (function () {
                    const modal = document.getElementById('gh-offering-edit-modal');
                    if (!modal) return;
                    const form = document.getElementById('gh-offering-edit-form');
                    modal.addEventListener('show.bs.modal', function (event) {
                        const btn = event.relatedTarget;
                        if (!btn) return;
                        form.setAttribute('action', btn.getAttribute('data-action') || '');
                        document.getElementById('gh-off-edit-product').value = btn.getAttribute('data-product-id') || '';
                        document.getElementById('gh-off-edit-treatment').value = btn.getAttribute('data-treatment-id') || '';
                        document.getElementById('gh-off-edit-price').value = btn.getAttribute('data-price') || '';
                        document.getElementById('gh-off-edit-tax').value = btn.getAttribute('data-tax-class') || 'STANDARD_RATED';
                        document.getElementById('gh-off-edit-status').value = btn.getAttribute('data-status') || 'ACTIVE';
                        document.getElementById('gh-off-edit-sort').value = btn.getAttribute('data-sort') || '0';
                    });
                })();
            </script>
        @endpush
    @endcan
    @include('gh.import.partials.import-card', ['entity' => 'offerings', 'model' => App\Models\GeneralHouse\GhProductOffering::class])

@extends('layouts.app')

@php
    $isEdit = $product !== null;
    $title = $isEdit ? __('gh.products.edit') : __('gh.products.create');
    $treatmentsByCategory = $treatmentsByCategory ?? [];
    $offeringsByTreatment = $offeringsByTreatment ?? collect();
    $canManageCategories = $canManageCategories ?? false;
    $oldOfferings = old('offerings', []);
    $initialSelections = [];
    if (is_array($oldOfferings) && $oldOfferings !== []) {
        foreach ($oldOfferings as $row) {
            if (! is_array($row) || empty($row['treatment_id'])) {
                continue;
            }
            $tid = (string) $row['treatment_id'];
            $initialSelections[$tid] = [
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || ($row['enabled'] ?? null) === 1
                    || ($row['enabled'] ?? null) === '1',
                'standard_price' => (string) ($row['standard_price'] ?? ''),
            ];
        }
    } else {
        foreach ($offeringsByTreatment as $offering) {
            $initialSelections[(string) $offering->treatment_id] = [
                'enabled' => $offering->status === \App\Support\GeneralHouse\GhCatalogStatus::ACTIVE,
                'standard_price' => (string) ($offering->standard_price ?? ''),
            ];
        }
    }
    $initialCategoryId = (string) old('category_id', $product->category_id ?? '');
    $pageSize = 12;
@endphp

@section('title', $title)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.products.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.products.back') }}</a>
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
            <form method="post"
                  id="gh-product-form"
                  action="{{ $isEdit ? route('gh.products.update', $product) : route('gh.products.store') }}"
                  enctype="multipart/form-data"
                  class="row g-3"
                  data-is-edit="{{ $isEdit ? '1' : '0' }}"
                  data-page-size="{{ $pageSize }}"
                  data-initial-category="{{ $initialCategoryId }}"
                  data-category-change-warn="{{ __('gh.products.category_change_warn') }}"
                  data-locale="{{ app()->getLocale() }}">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="col-md-4">
                    <label class="form-label" for="category_id">{{ __('gh.fields.category') }}</label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}"
                                @selected($initialCategoryId === (string) $cat->id)>
                                {{ $cat->localizedName() }}
                                @if (! $cat->isActive()) ({{ __('gh.status.INACTIVE') }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="code">{{ __('gh.fields.code') }}</label>
                    <input id="code" name="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code', $product->code ?? '') }}" required maxlength="40" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">{{ __('gh.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" required>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(old('status', $product->status ?? 'ACTIVE') === $st)>
                                {{ __('gh.status.'.$st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_ar">{{ __('gh.fields.name_ar') }}</label>
                    <input id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror"
                           value="{{ old('name_ar', $product->name_ar ?? '') }}" required maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_en">{{ __('gh.fields.name_en') }}</label>
                    <input id="name_en" name="name_en" class="form-control @error('name_en') is-invalid @enderror"
                           value="{{ old('name_en', $product->name_en ?? '') }}" required maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sort_order">{{ __('gh.fields.sort_order') }}</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                           value="{{ old('sort_order', $product->sort_order ?? 0) }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="image">{{ __('gh.fields.image') }}</label>
                    <input id="image" name="image" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    @if ($isEdit && $product->image_path)
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image"
                                   @checked(old('remove_image'))>
                            <label class="form-check-label" for="remove_image">{{ __('gh.fields.remove_image') }}</label>
                        </div>
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="img-thumbnail mt-2" style="max-height: 96px;">
                        @endif
                    @endif
                </div>

                <div class="col-12">
                    <hr class="my-1">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2">
                        <div>
                            <h2 class="h6 mb-0">{{ __('gh.products.services_heading') }}</h2>
                            <p class="text-body-secondary small mb-0">{{ __('gh.products.services_hint') }}</p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge text-bg-primary-subtle border" id="gh-svc-selected-count">
                                {{ __('gh.products.selected_count', ['count' => 0]) }}
                            </span>
                            <span class="badge text-bg-light border">{{ __('gh.fields.currency') }}: AED</span>
                        </div>
                    </div>

                    <div class="row g-2 align-items-end mb-2" id="gh-svc-toolbar" hidden>
                        <div class="col-md-6">
                            <label class="form-label" for="gh-svc-search">{{ __('gh.products.services_search') }}</label>
                            <input type="search" id="gh-svc-search" class="form-control form-control-sm"
                                   placeholder="{{ __('gh.products.services_search_placeholder') }}" autocomplete="off">
                        </div>
                    </div>

                    <div id="gh-svc-empty" class="alert alert-warning py-2 small mb-0" hidden>
                        <div class="mb-1" id="gh-svc-empty-text">{{ __('gh.products.services_empty_category') }}</div>
                        @if ($canManageCategories)
                            <a href="#" id="gh-svc-manage-link" class="alert-link small" hidden>
                                {{ __('gh.products.manage_category_services') }}
                            </a>
                        @endif
                    </div>

                    <div id="gh-svc-no-category" class="alert alert-light border py-2 small mb-0">
                        {{ __('gh.products.services_pick_category') }}
                    </div>

                    <div id="gh-svc-grid" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2" hidden></div>

                    <div class="d-flex flex-wrap gap-2 mt-2" id="gh-svc-more-wrap" hidden>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gh-svc-more">
                            {{ __('gh.products.show_more') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="gh-svc-less" hidden>
                            {{ __('gh.products.show_less') }}
                        </button>
                    </div>

                    <div id="gh-svc-inputs" class="d-none" aria-hidden="true"></div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('gh-product-form');
    if (!form) return;

    const catalog = @json($treatmentsByCategory);
    const selections = @json($initialSelections);
    const manageUrlTemplate = @json(route('gh.categories.treatments', ['category' => 0]));
    const canManage = @json((bool) $canManageCategories);
    const labels = {
        enabled: @json(__('gh.products.col_enabled')),
        price: @json(__('gh.products.col_price')),
        inactive: @json(__('gh.status.INACTIVE')),
        selected: @json(__('gh.products.selected_count')),
        emptyCategory: @json(__('gh.products.services_empty_category')),
        pickCategory: @json(__('gh.products.services_pick_category')),
    };

    const categorySelect = document.getElementById('category_id');
    const grid = document.getElementById('gh-svc-grid');
    const inputs = document.getElementById('gh-svc-inputs');
    const toolbar = document.getElementById('gh-svc-toolbar');
    const searchInput = document.getElementById('gh-svc-search');
    const emptyBox = document.getElementById('gh-svc-empty');
    const emptyText = document.getElementById('gh-svc-empty-text');
    const noCategory = document.getElementById('gh-svc-no-category');
    const moreWrap = document.getElementById('gh-svc-more-wrap');
    const moreBtn = document.getElementById('gh-svc-more');
    const lessBtn = document.getElementById('gh-svc-less');
    const selectedCount = document.getElementById('gh-svc-selected-count');
    const manageLink = document.getElementById('gh-svc-manage-link');
    const pageSize = parseInt(form.getAttribute('data-page-size') || '12', 10);
    const isEdit = form.getAttribute('data-is-edit') === '1';
    const warnMsg = form.getAttribute('data-category-change-warn') || '';
    const locale = form.getAttribute('data-locale') || 'en';

    let visibleLimit = pageSize;
    let lastCategory = categorySelect.value;

    function treatmentName(t) {
        return locale === 'ar' ? (t.name_ar || t.name_en) : (t.name_en || t.name_ar);
    }

    function ensureSelection(id) {
        const key = String(id);
        if (!selections[key]) {
            selections[key] = { enabled: false, standard_price: '' };
        }
        return selections[key];
    }

    function selectedEnabledCountForCategory(categoryId) {
        const list = catalog[String(categoryId)] || [];
        let n = 0;
        list.forEach(function (t) {
            const s = selections[String(t.id)];
            if (s && s.enabled) n += 1;
        });
        return n;
    }

    function updateSelectedBadge() {
        const count = selectedEnabledCountForCategory(categorySelect.value);
        selectedCount.textContent = labels.selected.replace(':count', String(count)).replace('%count%', String(count));
    }

    function writeHiddenInputs() {
        inputs.innerHTML = '';
        const categoryId = categorySelect.value;
        const list = catalog[String(categoryId)] || [];
        list.forEach(function (t, index) {
            const s = ensureSelection(t.id);
            const tid = document.createElement('input');
            tid.type = 'hidden';
            tid.name = 'offerings[' + index + '][treatment_id]';
            tid.value = String(t.id);
            inputs.appendChild(tid);

            if (s.enabled) {
                const en = document.createElement('input');
                en.type = 'hidden';
                en.name = 'offerings[' + index + '][enabled]';
                en.value = '1';
                inputs.appendChild(en);
            }

            const price = document.createElement('input');
            price.type = 'hidden';
            price.name = 'offerings[' + index + '][standard_price]';
            price.value = s.standard_price || '';
            inputs.appendChild(price);
        });
    }

    function filteredTreatments() {
        const categoryId = categorySelect.value;
        const list = (catalog[String(categoryId)] || []).slice();
        const q = (searchInput.value || '').trim().toLowerCase();
        if (!q) return list;
        return list.filter(function (t) {
            return String(t.name_ar || '').toLowerCase().includes(q)
                || String(t.name_en || '').toLowerCase().includes(q);
        });
    }

    function render() {
        const categoryId = categorySelect.value;
        grid.innerHTML = '';
        writeHiddenInputs();
        updateSelectedBadge();

        if (!categoryId) {
            toolbar.hidden = true;
            emptyBox.hidden = true;
            moreWrap.hidden = true;
            grid.hidden = true;
            noCategory.hidden = false;
            if (manageLink) manageLink.hidden = true;
            return;
        }

        noCategory.hidden = true;
        const all = catalog[String(categoryId)] || [];
        if (all.length === 0) {
            toolbar.hidden = true;
            grid.hidden = true;
            moreWrap.hidden = true;
            emptyBox.hidden = false;
            emptyText.textContent = labels.emptyCategory;
            if (manageLink && canManage) {
                manageLink.hidden = false;
                manageLink.setAttribute('href', manageUrlTemplate.replace(/\/0(\/services)?$/, '/' + categoryId + '/services').replace(/\/0$/, '/' + categoryId));
            }
            return;
        }

        emptyBox.hidden = true;
        if (manageLink) manageLink.hidden = true;
        toolbar.hidden = false;
        grid.hidden = false;

        const filtered = filteredTreatments();
        const slice = filtered.slice(0, visibleLimit);
        slice.forEach(function (t) {
            const s = ensureSelection(t.id);
            const col = document.createElement('div');
            col.className = 'col';
            const card = document.createElement('div');
            card.className = 'card h-100 border gh-svc-card ' + (s.enabled ? 'border-primary bg-primary-subtle' : 'bg-body');
            card.dataset.treatmentId = String(t.id);

            const body = document.createElement('div');
            body.className = 'card-body p-2 d-flex flex-column gap-2';

            const top = document.createElement('div');
            top.className = 'd-flex justify-content-between align-items-start gap-2';

            const titleWrap = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'fw-semibold small';
            title.textContent = treatmentName(t);
            titleWrap.appendChild(title);
            if (t.status !== 'ACTIVE') {
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-secondary';
                badge.textContent = labels.inactive;
                titleWrap.appendChild(badge);
            }

            const checkWrap = document.createElement('div');
            checkWrap.className = 'form-check m-0';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.className = 'form-check-input';
            check.id = 'gh_svc_en_' + t.id;
            check.checked = !!s.enabled;
            check.addEventListener('change', function () {
                ensureSelection(t.id).enabled = check.checked;
                priceWrap.hidden = !check.checked;
                card.className = 'card h-100 border gh-svc-card ' + (check.checked ? 'border-primary bg-primary-subtle' : 'bg-body');
                writeHiddenInputs();
                updateSelectedBadge();
            });
            const checkLabel = document.createElement('label');
            checkLabel.className = 'form-check-label visually-hidden';
            checkLabel.setAttribute('for', check.id);
            checkLabel.textContent = labels.enabled + ' — ' + treatmentName(t);
            checkWrap.appendChild(check);
            checkWrap.appendChild(checkLabel);

            top.appendChild(titleWrap);
            top.appendChild(checkWrap);

            const priceWrap = document.createElement('div');
            priceWrap.hidden = !s.enabled;
            const priceLabel = document.createElement('label');
            priceLabel.className = 'form-label small mb-1';
            priceLabel.setAttribute('for', 'gh_svc_price_' + t.id);
            priceLabel.textContent = labels.price;
            const group = document.createElement('div');
            group.className = 'input-group input-group-sm';
            const price = document.createElement('input');
            price.type = 'number';
            price.min = '0';
            price.step = '0.01';
            price.inputMode = 'decimal';
            price.className = 'form-control';
            price.id = 'gh_svc_price_' + t.id;
            price.placeholder = '0.00';
            price.value = s.standard_price || '';
            price.addEventListener('input', function () {
                ensureSelection(t.id).standard_price = price.value;
                writeHiddenInputs();
            });
            const addon = document.createElement('span');
            addon.className = 'input-group-text';
            addon.textContent = 'AED';
            group.appendChild(price);
            group.appendChild(addon);
            priceWrap.appendChild(priceLabel);
            priceWrap.appendChild(group);

            body.appendChild(top);
            body.appendChild(priceWrap);
            card.appendChild(body);
            col.appendChild(card);
            grid.appendChild(col);
        });

        const needsMore = filtered.length > pageSize;
        moreWrap.hidden = !needsMore;
        moreBtn.hidden = visibleLimit >= filtered.length;
        lessBtn.hidden = visibleLimit <= pageSize;
    }

    categorySelect.addEventListener('change', function () {
        const next = categorySelect.value;
        if (isEdit && lastCategory && next && next !== lastCategory && warnMsg) {
            const activeOnOld = selectedEnabledCountForCategory(lastCategory);
            if (activeOnOld > 0 && !window.confirm(warnMsg)) {
                categorySelect.value = lastCategory;
                return;
            }
        }
        lastCategory = next;
        visibleLimit = pageSize;
        if (searchInput) searchInput.value = '';
        render();
    });

    searchInput.addEventListener('input', function () {
        visibleLimit = pageSize;
        render();
    });

    moreBtn.addEventListener('click', function () {
        visibleLimit += pageSize;
        render();
    });
    lessBtn.addEventListener('click', function () {
        visibleLimit = pageSize;
        render();
    });

    form.addEventListener('submit', function () {
        writeHiddenInputs();
    });

    render();
})();
</script>
@endpush

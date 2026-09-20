@extends('layouts.app')

@section('title', __('gross.sales.cashier_title'))

@section('content')
    @php
        $customers = $customers ?? collect();
        $shippingCompanies = $shippingCompanies ?? collect();
        $salesPeople = $salesPeople ?? collect();
        $catalogUrls = $catalogUrls ?? [];
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales.cashier_title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales.cashier_subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gross.sales.create') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales.cashier_classic') }}</a>
            <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.cancel') }}</a>
        </div>
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

    <form method="post"
          action="{{ route('gross.sales.store') }}"
          class="hp-cashier"
          data-gross-cashier
          data-url-categories="{{ $catalogUrls['categories'] ?? '' }}"
          data-url-subcategories="{{ $catalogUrls['subcategories'] ?? '' }}"
          data-url-products="{{ $catalogUrls['products'] ?? '' }}"
          data-url-variants="{{ $catalogUrls['variants'] ?? '' }}">
        @csrf
        <div class="row g-3">
            {{-- Invoice / cart sidebar --}}
            <div class="col-lg-4 order-2 order-lg-1">
                <div class="card border-0 shadow-sm hp-cashier-panel">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h2 class="h6 mb-0">{{ __('gross.sales.cashier_invoice_panel') }}</h2>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mb-2">
                            <label class="form-label" for="customer_id">{{ __('gross.fields.customer') }}</label>
                            <select id="customer_id" name="customer_id" class="form-select form-select-sm" data-cashier-customer>
                                <option value="">—</option>
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}"
                                            data-name="{{ $c->name }}"
                                            data-phone="{{ $c->phone }}"
                                            data-address="{{ $c->address }}"
                                            @selected(old('customer_id') == $c->id)>
                                        {{ $c->name }} — {{ $c->phone }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="customer_name">{{ __('gross.fields.customer_name') }}</label>
                            <input id="customer_name" name="customer_name" class="form-control form-control-sm"
                                   value="{{ old('customer_name') }}" required autocomplete="off">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="customer_phone">{{ __('gross.fields.customer_phone') }}</label>
                            <input id="customer_phone" name="customer_phone" class="form-control form-control-sm"
                                   value="{{ old('customer_phone') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="customer_address">{{ __('gross.fields.address') }}</label>
                            <textarea id="customer_address" name="customer_address" class="form-control form-control-sm" rows="2">{{ old('customer_address') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="sales_user_id">{{ __('gross.fields.sales_person') }}</label>
                            <select id="sales_user_id" name="sales_user_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach ($salesPeople as $person)
                                    <option value="{{ $person->id }}"
                                            @selected((string) old('sales_user_id', auth()->id()) === (string) $person->id)>
                                        {{ $person->localizedName() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="shipping_company_id">{{ __('gross.fields.shipping_company') }}</label>
                            <select id="shipping_company_id" name="shipping_company_id" class="form-select form-select-sm" data-cashier-shipping>
                                <option value="">—</option>
                                @foreach ($shippingCompanies as $sc)
                                    <option value="{{ $sc->id }}" data-name="{{ $sc->name }}"
                                            @selected(old('shipping_company_id') == $sc->id)>{{ $sc->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="shipping_company_name" id="shipping_company_name" value="{{ old('shipping_company_name') }}">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label" for="currency">{{ __('gross.fields.currency') }}</label>
                                <select id="currency" name="currency" class="form-select form-select-sm" required>
                                    @foreach (['EGP', 'AED'] as $cur)
                                        <option value="{{ $cur }}" @selected(old('currency', 'EGP') === $cur)>{{ $cur }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="shipping_fee">{{ __('gross.fields.shipping_fee') }}</label>
                                <input id="shipping_fee" name="shipping_fee" type="number" step="0.01" min="0"
                                       class="form-control form-control-sm" value="{{ old('shipping_fee', 0) }}" data-cashier-shipping-fee>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="discount_mode">{{ __('gross.fields.invoice_discount') }}</label>
                            <div class="input-group input-group-sm">
                                <select id="discount_mode" name="discount_mode" class="form-select" data-cashier-discount-mode style="max-width: 6rem">
                                    <option value="amount" @selected(old('discount_mode', 'amount') === 'amount')>{{ __('gross.fields.discount_as_amount_short') }}</option>
                                    <option value="percent" @selected(old('discount_mode') === 'percent')>%</option>
                                </select>
                                <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0"
                                       class="form-control @if (old('discount_mode', 'amount') !== 'amount') d-none @endif"
                                       data-cashier-discount-amount value="{{ old('discount_amount', 0) }}"
                                       @if (old('discount_mode', 'amount') !== 'amount') disabled @endif>
                                <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100"
                                       class="form-control @if (old('discount_mode', 'amount') !== 'percent') d-none @endif"
                                       data-cashier-discount-percent value="{{ old('discount_percent', 0) }}"
                                       @if (old('discount_mode', 'amount') !== 'percent') disabled @endif>
                            </div>
                        </div>

                        <h3 class="h6">{{ __('gross.sales.lines') }}</h3>
                        <div class="hp-cashier-cart-lines mb-2" data-cashier-cart>
                            <p class="text-body-secondary small mb-0" data-cashier-cart-empty>{{ __('gross.sales.cashier_cart_empty') }}</p>
                            <ul class="list-unstyled mb-0 d-none" data-cashier-cart-list></ul>
                        </div>
                        <div data-cashier-lines-inputs></div>

                        <div class="border-top pt-2 small">
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">{{ __('gross.fields.subtotal') }}</span><span class="num-ltr fw-semibold hp-value" data-cashier-subtotal>0.00</span></div>
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">{{ __('gross.fields.invoice_discount') }}</span><span class="num-ltr hp-value" data-cashier-discount>0.00</span></div>
                            <div class="d-flex justify-content-between"><span class="text-body-secondary">{{ __('gross.fields.shipping_fee') }}</span><span class="num-ltr hp-value" data-cashier-shipping-total>0.00</span></div>
                            <div class="d-flex justify-content-between mt-1"><span class="fw-semibold">{{ __('gross.fields.grand_total') }}</span><span class="num-ltr fw-bold hp-value" data-cashier-grand>0.00</span></div>
                        </div>

                        <div class="mt-3 d-grid gap-2">
                            <button type="submit" class="btn btn-primary" data-cashier-submit disabled>
                                {{ __('gross.sales.cashier_save_draft') }}
                            </button>
                        </div>
                        <div class="mb-0 mt-2">
                            <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
                            <textarea id="notes" name="notes" class="form-control form-control-sm" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Catalog --}}
            <div class="col-lg-8 order-1 order-lg-2">
                <div class="card border-0 shadow-sm hp-cashier-panel">
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" data-cashier-search
                                   placeholder="{{ __('gross.sales.cashier_search_placeholder') }}"
                                   aria-label="{{ __('gross.sales.cashier_search') }}" autocomplete="off">
                        </div>

                        <div class="mb-2">
                            <div class="form-label mb-1">{{ __('gross.sales.cashier_categories') }}</div>
                            <div class="d-flex flex-wrap gap-2" data-cashier-categories>
                                <span class="text-body-secondary small">{{ __('gross.sales.cashier_pick_category') }}</span>
                            </div>
                        </div>
                        <div class="mb-3 d-none" data-cashier-subcategories-wrap>
                            <div class="form-label mb-1">{{ __('gross.sales.cashier_subcategories') }}</div>
                            <div class="d-flex flex-wrap gap-2" data-cashier-subcategories></div>
                        </div>

                        <div class="row g-3" data-cashier-products>
                            <div class="col-12">
                                <p class="text-body-secondary small mb-0" data-cashier-products-hint>{{ __('gross.sales.cashier_pick_category') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Variant modal --}}
    <div class="modal fade" id="cashierVariantModal" tabindex="-1" aria-labelledby="cashierVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="cashierVariantModalLabel" data-cashier-modal-title>—</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
                </div>
                <div class="modal-body">
                    <h3 class="h6">{{ __('gross.sales.cashier_variants') }}</h3>
                    <div class="row g-2 mb-3" data-cashier-variants></div>
                    <div class="mb-3">
                        <label class="form-label" for="cashier_qty">{{ __('gross.sales.cashier_qty') }}</label>
                        <div class="d-flex align-items-center gap-2" style="max-width: 12rem">
                            <button type="button" class="btn btn-outline-secondary" data-cashier-qty-minus aria-label="−">−</button>
                            <input id="cashier_qty" type="number" min="1" value="1" class="form-control text-center" data-cashier-qty>
                            <button type="button" class="btn btn-outline-secondary" data-cashier-qty-plus aria-label="+">+</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('general.cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-cashier-add-variant disabled>{{ __('gross.sales.cashier_add_to_invoice') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-gross-cashier]');
    if (!root) return;

    const urls = {
        categories: root.getAttribute('data-url-categories') || '',
        subcategories: root.getAttribute('data-url-subcategories') || '',
        products: root.getAttribute('data-url-products') || '',
        variants: root.getAttribute('data-url-variants') || '',
    };

    const i18n = {
        emptyProducts: @json(__('gross.sales.cashier_products_empty')),
        pickCategory: @json(__('gross.sales.cashier_pick_category')),
        pickSubcategory: @json(__('gross.sales.cashier_pick_subcategory')),
        onHand: @json(__('gross.sales.cashier_on_hand')),
        priceFrom: @json(__('gross.sales.cashier_price_from')),
        remove: @json(__('gross.sales.cashier_remove_line')),
        cartEmpty: @json(__('gross.sales.cashier_cart_empty')),
    };

    const elCategories = root.querySelector('[data-cashier-categories]');
    const elSubsWrap = root.querySelector('[data-cashier-subcategories-wrap]');
    const elSubs = root.querySelector('[data-cashier-subcategories]');
    const elProducts = root.querySelector('[data-cashier-products]');
    const elSearch = root.querySelector('[data-cashier-search]');
    const elCartList = root.querySelector('[data-cashier-cart-list]');
    const elCartEmpty = root.querySelector('[data-cashier-cart-empty]');
    const elLinesInputs = root.querySelector('[data-cashier-lines-inputs]');
    const elSubmit = root.querySelector('[data-cashier-submit]');
    const modalEl = document.getElementById('cashierVariantModal');
    const elVariants = document.querySelector('[data-cashier-variants]');
    const elModalTitle = document.querySelector('[data-cashier-modal-title]');
    const elQty = document.querySelector('[data-cashier-qty]');
    const elAddVariant = document.querySelector('[data-cashier-add-variant]');

    let categoryId = null;
    let subcategoryId = null;
    let searchTimer = null;
    /** @type {Array<{item_id:number,qty:number,unit_price:number,label:string,size:string,color:string}>} */
    let cart = [];
    let selectedVariant = null;
    let currentGroup = null;

    /** app.js is type=module (deferred); resolve Modal lazily so bootstrap exists. */
    function getModal() {
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function showModal() {
        const instance = getModal();
        if (instance) {
            instance.show();
            return;
        }
        // Fallback if module not ready yet — retry briefly.
        let tries = 0;
        const timer = window.setInterval(function () {
            tries += 1;
            const m = getModal();
            if (m) {
                window.clearInterval(timer);
                m.show();
            } else if (tries >= 40) {
                window.clearInterval(timer);
            }
        }, 50);
    }

    function hideModal() {
        const instance = getModal();
        if (instance) {
            instance.hide();
        }
    }

    function money(n) {
        return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    }

    function fetchJson(url) {
        return fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function chip(label, active, onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary hp-cashier-chip' + (active ? ' active' : '');
        btn.textContent = label;
        btn.addEventListener('click', onClick);
        return btn;
    }

    function loadCategories() {
        fetchJson(urls.categories).then(function (rows) {
            elCategories.innerHTML = '';
            if (!rows.length) {
                elCategories.innerHTML = '<span class="text-body-secondary small">' + i18n.pickCategory + '</span>';
                return;
            }
            rows.forEach(function (row) {
                elCategories.appendChild(chip(row.name, categoryId === row.id, function () {
                    categoryId = row.id;
                    subcategoryId = null;
                    loadCategories();
                    loadSubcategories();
                    setProductsHint(i18n.pickSubcategory);
                }));
            });
        }).catch(function () {
            elCategories.innerHTML = '<span class="text-danger small">' + i18n.pickCategory + '</span>';
        });
    }

    function loadSubcategories() {
        if (!categoryId) {
            elSubsWrap.classList.add('d-none');
            return;
        }
        elSubsWrap.classList.remove('d-none');
        const u = urls.subcategories + '?category_id=' + encodeURIComponent(categoryId);
        fetchJson(u).then(function (rows) {
            elSubs.innerHTML = '';
            if (!rows.length) {
                elSubs.innerHTML = '<span class="text-body-secondary small">' + i18n.pickSubcategory + '</span>';
                return;
            }
            rows.forEach(function (row) {
                elSubs.appendChild(chip(row.name, subcategoryId === row.id, function () {
                    subcategoryId = row.id;
                    loadSubcategories();
                    loadProducts();
                }));
            });
        });
    }

    function setProductsHint(text) {
        elProducts.innerHTML = '<div class="col-12"><p class="text-body-secondary small mb-0">' + text + '</p></div>';
    }

    function loadProducts() {
        const q = (elSearch.value || '').trim();
        if (!subcategoryId && q.length < 1) {
            setProductsHint(categoryId ? i18n.pickSubcategory : i18n.pickCategory);
            return;
        }
        let u = urls.products + '?';
        if (subcategoryId) u += 'subcategory_id=' + encodeURIComponent(subcategoryId) + '&';
        if (q) u += 'q=' + encodeURIComponent(q);
        fetchJson(u).then(function (rows) {
            elProducts.innerHTML = '';
            if (!rows.length) {
                setProductsHint(i18n.emptyProducts);
                return;
            }
            rows.forEach(function (group) {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-xl-3';
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'hp-cashier-product card h-100 text-start p-0 overflow-hidden bg-body';
                let media = '';
                if (group.image_url) {
                    media = '<img src="' + group.image_url + '" alt="" class="hp-cashier-product-img">';
                } else {
                    media = '<div class="hp-cashier-product-placeholder d-flex align-items-center justify-content-center text-body-secondary"><i class="bi bi-image fs-3"></i></div>';
                }
                const priceBit = group.price_from
                    ? '<div class="small text-body-secondary mt-1">' + i18n.priceFrom + ' <span class="num-ltr hp-value">' + group.price_from + '</span></div>'
                    : '';
                const desc = group.description
                    ? '<div class="small text-body-secondary mt-1" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">' + escapeHtml(group.description) + '</div>'
                    : '';
                const cats = [group.category, group.subcategory].filter(Boolean);
                const catBit = cats.length
                    ? '<div class="small text-body-secondary mt-1">' + escapeHtml(cats.join(' · ')) + '</div>'
                    : '';
                card.innerHTML = media
                    + '<div class="p-2">'
                    + '<div class="fw-semibold">' + escapeHtml(group.name) + '</div>'
                    + catBit
                    + desc
                    + priceBit
                    + '</div>';
                card.addEventListener('click', function () {
                    openVariants(group);
                });
                col.appendChild(card);
                elProducts.appendChild(col);
            });
        });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openVariants(group) {
        currentGroup = group;
        selectedVariant = null;
        elAddVariant.disabled = true;
        elQty.value = '1';
        elModalTitle.textContent = group.name;
        const cats = [group.category, group.subcategory].filter(Boolean);
        if (cats.length) {
            elModalTitle.textContent = group.name + ' — ' + cats.join(' · ');
        }
        elVariants.innerHTML = '<div class="col-12"><span class="text-body-secondary small">…</span></div>';
        let u = urls.variants + '?name=' + encodeURIComponent(group.name);
        if (group.subcategory_id) u += '&subcategory_id=' + encodeURIComponent(group.subcategory_id);
        fetchJson(u).then(function (rows) {
            elVariants.innerHTML = '';
            if (!rows.length) {
                elVariants.innerHTML = '<div class="col-12"><span class="text-body-secondary small">' + i18n.emptyProducts + '</span></div>';
                return;
            }
            rows.forEach(function (v) {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'hp-cashier-variant rounded p-2 w-100 bg-body';
                const bits = [v.size, v.color, v.model].filter(Boolean).join(' · ') || v.code;
                btn.innerHTML = '<div class="fw-semibold small">' + escapeHtml(bits) + '</div>'
                    + '<div class="small text-body-secondary num-ltr">' + (v.sale_price || '0.00') + '</div>'
                    + '<div class="small text-body-secondary">' + i18n.onHand + ': <span class="num-ltr">' + v.on_hand + '</span></div>';
                btn.addEventListener('click', function () {
                    elVariants.querySelectorAll('.hp-cashier-variant').forEach(function (n) { n.classList.remove('active'); });
                    btn.classList.add('active');
                    selectedVariant = v;
                    elAddVariant.disabled = false;
                });
                col.appendChild(btn);
                elVariants.appendChild(col);
            });
        }).catch(function () {
            elVariants.innerHTML = '<div class="col-12"><span class="text-danger small">' + i18n.emptyProducts + '</span></div>';
        });
        showModal();
    }

    function renderCart() {
        elCartList.innerHTML = '';
        elLinesInputs.innerHTML = '';
        if (!cart.length) {
            elCartEmpty.classList.remove('d-none');
            elCartList.classList.add('d-none');
            elSubmit.disabled = true;
            recalc();
            return;
        }
        elCartEmpty.classList.add('d-none');
        elCartList.classList.remove('d-none');
        elSubmit.disabled = false;
        cart.forEach(function (line, idx) {
            const li = document.createElement('li');
            li.className = 'd-flex align-items-start justify-content-between gap-2 py-2 border-bottom';
            const meta = [line.size, line.color].filter(Boolean).join(' · ');
            li.innerHTML = '<div><span class="num-ltr fw-semibold">' + line.qty + '</span> × '
                + escapeHtml(line.label)
                + (meta ? '<div class="small text-body-secondary">' + escapeHtml(meta) + '</div>' : '')
                + '<div class="small num-ltr hp-value">' + money(line.unit_price * line.qty) + '</div></div>';
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-link btn-sm text-danger p-0';
            rm.textContent = i18n.remove;
            rm.addEventListener('click', function () {
                cart.splice(idx, 1);
                renderCart();
            });
            li.appendChild(rm);
            elCartList.appendChild(li);

            const prefix = 'lines[' + idx + ']';
            appendHidden(prefix + '[item_id]', line.item_id);
            appendHidden(prefix + '[qty]', line.qty);
            appendHidden(prefix + '[unit]', 'PIECE');
            appendHidden(prefix + '[unit_price]', line.unit_price);
            appendHidden(prefix + '[discount_mode]', 'amount');
            appendHidden(prefix + '[discount_amount]', '0');
            appendHidden(prefix + '[discount_percent]', '0');
        });
        recalc();
    }

    function appendHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        elLinesInputs.appendChild(input);
    }

    function recalc() {
        let subtotal = 0;
        cart.forEach(function (line) {
            subtotal += line.qty * line.unit_price;
        });
        const mode = root.querySelector('[data-cashier-discount-mode]')?.value || 'amount';
        let discount = 0;
        if (mode === 'percent') {
            discount = subtotal * ((Number(root.querySelector('[data-cashier-discount-percent]')?.value) || 0) / 100);
        } else {
            discount = Number(root.querySelector('[data-cashier-discount-amount]')?.value) || 0;
        }
        if (discount > subtotal) discount = subtotal;
        const shipping = Number(root.querySelector('[data-cashier-shipping-fee]')?.value) || 0;
        const grand = subtotal - discount + shipping;
        root.querySelector('[data-cashier-subtotal]').textContent = money(subtotal);
        root.querySelector('[data-cashier-discount]').textContent = money(discount);
        root.querySelector('[data-cashier-shipping-total]').textContent = money(shipping);
        root.querySelector('[data-cashier-grand]').textContent = money(grand);
    }

    root.querySelector('[data-cashier-customer]')?.addEventListener('change', function (e) {
        const opt = e.target.selectedOptions[0];
        if (!opt || !opt.value) return;
        root.querySelector('#customer_name').value = opt.getAttribute('data-name') || '';
        root.querySelector('#customer_phone').value = opt.getAttribute('data-phone') || '';
        root.querySelector('#customer_address').value = opt.getAttribute('data-address') || '';
    });

    root.querySelector('[data-cashier-shipping]')?.addEventListener('change', function (e) {
        const opt = e.target.selectedOptions[0];
        const nameInput = root.querySelector('#shipping_company_name');
        if (nameInput) nameInput.value = opt && opt.value ? (opt.getAttribute('data-name') || '') : '';
    });

    const discountMode = root.querySelector('[data-cashier-discount-mode]');
    discountMode?.addEventListener('change', function () {
        const amount = root.querySelector('[data-cashier-discount-amount]');
        const percent = root.querySelector('[data-cashier-discount-percent]');
        const isPercent = discountMode.value === 'percent';
        if (amount) {
            amount.classList.toggle('d-none', isPercent);
            amount.disabled = isPercent;
        }
        if (percent) {
            percent.classList.toggle('d-none', !isPercent);
            percent.disabled = !isPercent;
        }
        recalc();
    });
    root.querySelector('[data-cashier-discount-amount]')?.addEventListener('input', recalc);
    root.querySelector('[data-cashier-discount-percent]')?.addEventListener('input', recalc);
    root.querySelector('[data-cashier-shipping-fee]')?.addEventListener('input', recalc);

    elSearch?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadProducts, 250);
    });

    document.querySelector('[data-cashier-qty-minus]')?.addEventListener('click', function () {
        const n = Math.max(1, (Number(elQty.value) || 1) - 1);
        elQty.value = String(n);
    });
    document.querySelector('[data-cashier-qty-plus]')?.addEventListener('click', function () {
        elQty.value = String(Math.max(1, (Number(elQty.value) || 1) + 1));
    });

    elAddVariant?.addEventListener('click', function () {
        if (!selectedVariant) return;
        const qty = Math.max(1, Number(elQty.value) || 1);
        const price = Number(selectedVariant.sale_price || 0);
        const existing = cart.find(function (l) { return l.item_id === selectedVariant.id; });
        if (existing) {
            existing.qty += qty;
        } else {
            cart.push({
                item_id: selectedVariant.id,
                qty: qty,
                unit_price: price,
                label: selectedVariant.name,
                size: selectedVariant.size || '',
                color: selectedVariant.color || '',
            });
        }
        renderCart();
        hideModal();
    });

    root.addEventListener('submit', function (e) {
        if (!cart.length) {
            e.preventDefault();
        }
    });

    loadCategories();
    renderCart();
})();
</script>
@endpush

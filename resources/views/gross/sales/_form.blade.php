@php
    $invoice = $invoice ?? null;
    $customers = $customers ?? collect();
    $itemsById = $itemsById ?? collect();
    $salesPeople = $salesPeople ?? collect();
    $oldLines = old('lines', $invoice?->lines?->map(function ($l) {
        $mode = ((float) $l->discount_percent) > 0 ? 'percent' : 'amount';

        return [
            'item_id' => $l->item_id,
            'qty' => $l->qty,
            'unit' => $l->unit,
            'unit_price' => $l->unit_price,
            'discount_mode' => $mode,
            'discount_amount' => $l->discount_amount,
            'discount_percent' => $l->discount_percent,
        ];
    })->all() ?? [[
        'item_id' => '',
        'qty' => 1,
        'unit' => 'PIECE',
        'unit_price' => 0,
        'discount_mode' => 'amount',
        'discount_amount' => 0,
        'discount_percent' => 0,
    ]]);
    $invoiceDiscountMode = old(
        'discount_mode',
        ((float) ($invoice?->discount_percent ?? 0)) > 0 ? 'percent' : 'amount'
    );
@endphp
<div class="row g-3 mb-3" data-gross-invoice-form data-item-search-url="{{ route('gross.items.search') }}">
    <div class="col-md-4">
        <label class="form-label" for="customer_id">{{ __('gross.fields.customer') }}</label>
        <select id="customer_id" name="customer_id" class="form-select" data-gross-customer-select>
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}"
                        data-name="{{ $c->name }}"
                        data-phone="{{ $c->phone }}"
                        data-address="{{ $c->address }}"
                        @selected(old('customer_id', $invoice?->customer_id) == $c->id)>
                    {{ $c->name }} — {{ $c->phone }}
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ __('gross.sales.customer_pick_hint') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="customer_name">{{ __('gross.fields.customer_name') }}</label>
        <input id="customer_name" name="customer_name" list="gross-invoice-customer-names"
               class="form-control @error('customer_name') is-invalid @enderror"
               value="{{ old('customer_name', $invoice?->customer_name) }}" required autocomplete="off"
               placeholder="{{ __('gross.customers.name_suggest_hint') }}">
        <datalist id="gross-invoice-customer-names">
            @foreach ($customers as $c)
                <option value="{{ $c->name }}"></option>
            @endforeach
        </datalist>
        @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="customer_phone">{{ __('gross.fields.customer_phone') }}</label>
        <input id="customer_phone" name="customer_phone" class="form-control" value="{{ old('customer_phone', $invoice?->customer_phone) }}" required>
    </div>
    <div class="col-12">
        <label class="form-label" for="customer_address">{{ __('gross.fields.address') }}</label>
        <textarea id="customer_address" name="customer_address" class="form-control" rows="2">{{ old('customer_address', $invoice?->customer_address) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sales_user_id">{{ __('gross.fields.sales_person') }}</label>
        <select id="sales_user_id" name="sales_user_id" class="form-select">
            <option value="">—</option>
            @foreach ($salesPeople as $person)
                <option value="{{ $person->id }}"
                        @selected((string) old('sales_user_id', $invoice?->sales_user_id ?? auth()->id()) === (string) $person->id)>
                    {{ $person->localizedName() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="shipping_company_id">{{ __('gross.fields.shipping_company') }}</label>
        <select id="shipping_company_id" name="shipping_company_id" class="form-select">
            <option value="">—</option>
            @foreach ($shippingCompanies as $sc)
                <option value="{{ $sc->id }}" @selected(old('shipping_company_id', $invoice?->shipping_company_id) == $sc->id)>{{ $sc->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="currency">{{ __('gross.fields.currency') }}</label>
        <select id="currency" name="currency" class="form-select" required>
            @foreach (['EGP', 'AED'] as $cur)
                <option value="{{ $cur }}" @selected(old('currency', $invoice?->currency ?? 'EGP') === $cur)>{{ $cur }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="shipping_fee">{{ __('gross.fields.shipping_fee') }}</label>
        <input id="shipping_fee" name="shipping_fee" type="number" step="0.01" min="0" class="form-control"
               value="{{ old('shipping_fee', $invoice?->shipping_fee ?? 0) }}" data-gross-shipping-fee>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="discount_mode">{{ __('gross.fields.invoice_discount') }}</label>
        <div class="input-group">
            <select id="discount_mode" name="discount_mode" class="form-select" data-gross-invoice-discount-mode style="max-width: 8rem">
                <option value="amount" @selected($invoiceDiscountMode === 'amount')>{{ __('gross.fields.discount_as_amount') }}</option>
                <option value="percent" @selected($invoiceDiscountMode === 'percent')>{{ __('gross.fields.discount_as_percent') }}</option>
            </select>
            <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0"
                   class="form-control @if ($invoiceDiscountMode !== 'amount') d-none @endif"
                   data-gross-invoice-discount-amount
                   value="{{ old('discount_amount', $invoice?->discount_amount ?? 0) }}"
                   @if ($invoiceDiscountMode !== 'amount') disabled @endif>
            <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100"
                   class="form-control @if ($invoiceDiscountMode !== 'percent') d-none @endif"
                   data-gross-invoice-discount-percent
                   value="{{ old('discount_percent', $invoice?->discount_percent ?? 0) }}"
                   @if ($invoiceDiscountMode !== 'percent') disabled @endif>
            <span class="input-group-text" data-gross-invoice-discount-suffix>
                {{ $invoiceDiscountMode === 'percent' ? '%' : __('gross.fields.amount') }}
            </span>
        </div>
        <div class="form-text">{{ __('gross.sales.discount_help') }}</div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <div>
        <h2 class="h6 mb-0">{{ __('gross.sales.lines') }}</h2>
        <div class="form-text mb-0">{{ __('gross.sales.item_search_hint') }}</div>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm" data-gross-add-line>
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('gross.sales.add_line') }}
    </button>
</div>
@error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="table-responsive mb-3">
    <table class="table table-hr table-sm align-middle mb-0" data-gross-lines-table>
        <thead>
            <tr>
                <th style="min-width: 14rem">{{ __('gross.fields.item') }}</th>
                <th style="width: 5rem">{{ __('gross.fields.size') }}</th>
                <th style="width: 5.5rem">{{ __('gross.fields.qty') }}</th>
                <th style="width: 7rem">{{ __('gross.fields.unit_price') }}</th>
                <th style="width: 11rem">{{ __('gross.fields.line_discount') }}</th>
                <th style="width: 6rem" class="col-num">{{ __('gross.fields.line_total') }}</th>
                <th style="width: 2.5rem"></th>
            </tr>
        </thead>
        <tbody data-gross-lines-body>
            @foreach ($oldLines as $i => $line)
                @include('gross.sales._line', ['index' => $i, 'line' => $line, 'itemsById' => $itemsById])
            @endforeach
        </tbody>
    </table>
</div>

<div class="card border-0 bg-body-tertiary mb-3" data-gross-totals>
    <div class="card-body py-2">
        <div class="row g-2 small">
            <div class="col-6 col-md-3">
                <div class="text-body-secondary">{{ __('gross.fields.subtotal') }}</div>
                <div class="fw-semibold num-ltr" data-total-subtotal>0.00</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-body-secondary">{{ __('gross.fields.invoice_discount') }}</div>
                <div class="fw-semibold num-ltr" data-total-discount>0.00</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-body-secondary">{{ __('gross.fields.shipping_fee') }}</div>
                <div class="fw-semibold num-ltr" data-total-shipping>0.00</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-body-secondary">{{ __('gross.fields.grand_total') }}</div>
                <div class="fw-semibold num-ltr fs-6" data-total-grand>0.00</div>
            </div>
        </div>
    </div>
</div>

<template id="gross-invoice-line-template">
    @include('gross.sales._line', [
        'index' => '__INDEX__',
        'line' => [
            'item_id' => '',
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 0,
            'discount_mode' => 'amount',
            'discount_amount' => 0,
            'discount_percent' => 0,
        ],
        'itemsById' => collect(),
    ])
</template>

<div class="mb-3">
    <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
    <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $invoice?->notes) }}</textarea>
</div>

@push('scripts')
<script>
(function () {
    const form = document.querySelector('[data-gross-invoice-form]')?.closest('form');
    const body = document.querySelector('[data-gross-lines-body]');
    const template = document.getElementById('gross-invoice-line-template');
    const addBtn = document.querySelector('[data-gross-add-line]');
    const searchUrl = document.querySelector('[data-item-search-url]')?.getAttribute('data-item-search-url') || '';
    const emptyLabel = @json(__('gross.sales.item_search_empty'));
    let lineIndex = body ? body.querySelectorAll('[data-gross-line-row]').length : 0;

    function money(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function lineNet(row) {
        const qty = Number(row.querySelector('[data-line-qty]')?.value || 0);
        const price = Number(row.querySelector('[data-line-price]')?.value || 0);
        const gross = qty * price;
        const mode = row.querySelector('[data-line-discount-mode]')?.value || 'amount';
        let discount = 0;
        if (mode === 'percent') {
            discount = gross * (Number(row.querySelector('[data-line-discount-percent]')?.value || 0) / 100);
        } else {
            discount = Number(row.querySelector('[data-line-discount-amount]')?.value || 0);
        }
        if (discount > gross) discount = gross;
        return Math.max(0, gross - discount);
    }

    function recalc() {
        let subtotal = 0;
        body?.querySelectorAll('[data-gross-line-row]').forEach(function (row) {
            const net = lineNet(row);
            const el = row.querySelector('[data-line-total]');
            if (el) el.textContent = money(net);
            subtotal += net;
        });
        const mode = document.querySelector('[data-gross-invoice-discount-mode]')?.value || 'amount';
        let invDiscount = 0;
        if (mode === 'percent') {
            invDiscount = subtotal * (Number(document.querySelector('[data-gross-invoice-discount-percent]')?.value || 0) / 100);
        } else {
            invDiscount = Number(document.querySelector('[data-gross-invoice-discount-amount]')?.value || 0);
        }
        if (invDiscount > subtotal) invDiscount = subtotal;
        const shipping = Number(document.querySelector('[data-gross-shipping-fee]')?.value || 0);
        const grand = Math.max(0, subtotal - invDiscount + shipping);
        const set = function (sel, val) {
            const n = document.querySelector(sel);
            if (n) n.textContent = money(val);
        };
        set('[data-total-subtotal]', subtotal);
        set('[data-total-discount]', invDiscount);
        set('[data-total-shipping]', shipping);
        set('[data-total-grand]', grand);
    }

    function syncInvoiceDiscountMode() {
        const mode = document.querySelector('[data-gross-invoice-discount-mode]');
        if (!mode) return;
        const amount = document.querySelector('[data-gross-invoice-discount-amount]');
        const percent = document.querySelector('[data-gross-invoice-discount-percent]');
        const suffix = document.querySelector('[data-gross-invoice-discount-suffix]');
        const isPercent = mode.value === 'percent';
        if (amount) {
            amount.classList.toggle('d-none', isPercent);
            amount.disabled = isPercent;
        }
        if (percent) {
            percent.classList.toggle('d-none', !isPercent);
            percent.disabled = !isPercent;
        }
        if (suffix) {
            suffix.textContent = isPercent ? '%' : @json(__('gross.fields.amount'));
        }
        recalc();
    }

    function syncLineDiscountMode(row) {
        const mode = row.querySelector('[data-line-discount-mode]');
        if (!mode) return;
        const amount = row.querySelector('[data-line-discount-amount]');
        const percent = row.querySelector('[data-line-discount-percent]');
        const isPercent = mode.value === 'percent';
        if (amount) {
            amount.classList.toggle('d-none', isPercent);
            amount.disabled = isPercent;
        }
        if (percent) {
            percent.classList.toggle('d-none', !isPercent);
            percent.disabled = !isPercent;
        }
        recalc();
    }

    function wireItemPicker(row) {
        const root = row.querySelector('[data-item-picker]');
        const hidden = row.querySelector('[data-line-item]');
        const input = row.querySelector('[data-line-item-search]');
        const menu = row.querySelector('[data-line-item-results]');
        const priceInput = row.querySelector('[data-line-price]');
        const sizeCell = row.querySelector('[data-line-size]');
        if (!root || !hidden || !input || !menu || !searchUrl) return;

        // Escape .table-responsive overflow clipping.
        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }
        row._itemResultsMenu = menu;

        let timer = null;
        let seq = 0;
        let lastLabel = input.value;

        function closeMenu() {
            menu.classList.remove('show');
            input.setAttribute('aria-expanded', 'false');
        }

        function positionMenu() {
            const rect = input.getBoundingClientRect();
            const gap = 4;
            const maxH = 256;
            const spaceBelow = window.innerHeight - rect.bottom - gap;
            const spaceAbove = rect.top - gap;
            const openUp = spaceBelow < 140 && spaceAbove > spaceBelow;
            const height = Math.max(120, Math.min(maxH, openUp ? spaceAbove : spaceBelow));
            menu.style.position = 'fixed';
            menu.style.left = Math.max(8, rect.left) + 'px';
            menu.style.width = Math.max(rect.width, 240) + 'px';
            menu.style.right = 'auto';
            menu.style.zIndex = '2000';
            menu.style.maxHeight = height + 'px';
            menu.style.overflowY = 'auto';
            if (openUp) {
                menu.style.top = 'auto';
                menu.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
            } else {
                menu.style.bottom = 'auto';
                menu.style.top = (rect.bottom + gap) + 'px';
            }
        }

        function openMenu() {
            positionMenu();
            menu.classList.add('show');
            input.setAttribute('aria-expanded', 'true');
        }

        function selectItem(item) {
            hidden.value = String(item.id);
            input.value = item.label || '';
            lastLabel = input.value;
            if (priceInput && item.sale_price !== null && item.sale_price !== undefined && item.sale_price !== '') {
                priceInput.value = item.sale_price;
            }
            if (sizeCell) {
                sizeCell.textContent = item.size ? item.size : '—';
            }
            closeMenu();
            recalc();
        }

        function renderResults(items) {
            menu.innerHTML = '';
            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'dropdown-item disabled text-body-secondary';
                empty.textContent = emptyLabel;
                menu.appendChild(empty);
                openMenu();
                return;
            }
            items.forEach(function (item) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dropdown-item text-wrap';
                btn.textContent = item.label;
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectItem(item);
                });
                menu.appendChild(btn);
            });
            openMenu();
        }

        input.addEventListener('input', function () {
            if (input.value !== lastLabel) {
                hidden.value = '';
            }
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 1) {
                closeMenu();
                menu.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                const my = ++seq;
                fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (my !== seq) return;
                    renderResults(Array.isArray(data) ? data : []);
                }).catch(function () {
                    if (my !== seq) return;
                    closeMenu();
                });
            }, 250);
        });

        input.addEventListener('focus', function () {
            if (menu.children.length) openMenu();
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });

        window.addEventListener('resize', function () {
            if (menu.classList.contains('show')) positionMenu();
        });
        window.addEventListener('scroll', function () {
            if (menu.classList.contains('show')) positionMenu();
        }, true);

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target) && !menu.contains(e.target)) closeMenu();
        });
    }

    function wireRow(row) {
        wireItemPicker(row);
        row.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', recalc);
            el.addEventListener('change', recalc);
        });
        const mode = row.querySelector('[data-line-discount-mode]');
        if (mode) {
            mode.addEventListener('change', function () { syncLineDiscountMode(row); });
            syncLineDiscountMode(row);
        }
        const removeBtn = row.querySelector('[data-line-remove]');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const rows = body.querySelectorAll('[data-gross-line-row]');
                if (rows.length <= 1) return;
                if (row._itemResultsMenu) row._itemResultsMenu.remove();
                row.remove();
                recalc();
            });
        }
    }

    function addLine() {
        if (!template || !body) return;
        const html = template.innerHTML.replaceAll('__INDEX__', String(lineIndex++));
        const wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        body.appendChild(row);
        wireRow(row);
        recalc();
        row.querySelector('[data-line-item-search]')?.focus();
    }

    document.querySelector('[data-gross-invoice-discount-mode]')?.addEventListener('change', syncInvoiceDiscountMode);
    document.querySelector('[data-gross-invoice-discount-amount]')?.addEventListener('input', recalc);
    document.querySelector('[data-gross-invoice-discount-percent]')?.addEventListener('input', recalc);
    document.querySelector('[data-gross-shipping-fee]')?.addEventListener('input', recalc);
    syncInvoiceDiscountMode();

    document.querySelector('[data-gross-customer-select]')?.addEventListener('change', function (e) {
        const opt = e.target.options[e.target.selectedIndex];
        if (!opt || !opt.value) return;
        const name = document.getElementById('customer_name');
        const phone = document.getElementById('customer_phone');
        const address = document.getElementById('customer_address');
        if (name) name.value = opt.getAttribute('data-name') || '';
        if (phone) phone.value = opt.getAttribute('data-phone') || '';
        if (address) address.value = opt.getAttribute('data-address') || '';
    });

    body?.querySelectorAll('[data-gross-line-row]').forEach(wireRow);
    addBtn?.addEventListener('click', addLine);
    if (form) {
        form.addEventListener('input', recalc);
    }
    recalc();
})();
</script>
@endpush

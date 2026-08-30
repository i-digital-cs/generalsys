@php
    $invoice = $invoice ?? null;
    $suppliers = $suppliers ?? collect();
    $items = $items ?? collect();
    $oldLines = old('lines', $invoice?->lines?->map(function ($l) {
        $mode = ((float) $l->discount_percent) > 0 ? 'percent' : 'amount';

        return [
            'item_id' => $l->item_id,
            'qty' => $l->qty,
            'unit' => $l->unit,
            'buy_price' => $l->buy_price,
            'sale_price' => $l->sale_price,
            'discount_mode' => $mode,
            'discount_amount' => $l->discount_amount,
            'discount_percent' => $l->discount_percent,
        ];
    })->all() ?? [[
        'item_id' => '',
        'qty' => 1,
        'unit' => 'PIECE',
        'buy_price' => 0,
        'sale_price' => 0,
        'discount_mode' => 'amount',
        'discount_amount' => 0,
        'discount_percent' => 0,
    ]]);
    $invoiceDiscountMode = old(
        'discount_mode',
        ((float) ($invoice?->discount_percent ?? 0)) > 0 ? 'percent' : 'amount'
    );
@endphp
<div class="row g-3 mb-3" data-gross-purchase-form>
    <div class="col-md-4">
        <label class="form-label" for="supplier_id">{{ __('gross.fields.supplier') }}</label>
        <select id="supplier_id" name="supplier_id" class="form-select" data-gross-supplier-select>
            <option value="">—</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}"
                        data-name="{{ $s->name }}"
                        data-phone="{{ $s->phone }}"
                        data-address="{{ $s->address }}"
                        @selected(old('supplier_id', $invoice?->supplier_id) == $s->id)>
                    {{ $s->name }} — {{ $s->phone }}
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ __('gross.purchases.supplier_pick_hint') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="supplier_name">{{ __('gross.fields.supplier_name') }}</label>
        <input id="supplier_name" name="supplier_name" list="gross-purchase-supplier-names"
               class="form-control @error('supplier_name') is-invalid @enderror"
               value="{{ old('supplier_name', $invoice?->supplier_name) }}" required autocomplete="off"
               placeholder="{{ __('gross.suppliers.name_suggest_hint') }}">
        <datalist id="gross-purchase-supplier-names">
            @foreach ($suppliers as $s)
                <option value="{{ $s->name }}"></option>
            @endforeach
        </datalist>
        @error('supplier_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="supplier_phone">{{ __('gross.fields.supplier_phone') }}</label>
        <input id="supplier_phone" name="supplier_phone" class="form-control" value="{{ old('supplier_phone', $invoice?->supplier_phone) }}" required>
    </div>
    <div class="col-12">
        <label class="form-label" for="supplier_address">{{ __('gross.fields.address') }}</label>
        <textarea id="supplier_address" name="supplier_address" class="form-control" rows="2">{{ old('supplier_address', $invoice?->supplier_address) }}</textarea>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="currency">{{ __('gross.fields.currency') }}</label>
        <select id="currency" name="currency" class="form-select" required>
            @foreach (['EGP', 'AED'] as $cur)
                <option value="{{ $cur }}" @selected(old('currency', $invoice?->currency ?? 'EGP') === $cur)>{{ $cur }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-5">
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
        <div class="form-text">{{ __('gross.purchases.discount_help') }}</div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <h2 class="h6 mb-0">{{ __('gross.purchases.lines') }}</h2>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <input type="search" class="form-control form-control-sm" style="min-width: 12rem"
               placeholder="{{ __('gross.purchases.item_filter_placeholder') }}"
               data-gross-item-filter autocomplete="off">
        <button type="button" class="btn btn-outline-primary btn-sm" data-gross-add-line>
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('gross.purchases.add_line') }}
        </button>
    </div>
</div>
@error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="table-responsive mb-3">
    <table class="table table-hr table-sm align-middle mb-0" data-gross-lines-table>
        <thead>
            <tr>
                <th style="min-width: 12rem">{{ __('gross.fields.item') }}</th>
                <th style="width: 5rem">{{ __('gross.fields.size') }}</th>
                <th style="width: 5rem">{{ __('gross.fields.qty') }}</th>
                <th style="width: 6.5rem">{{ __('gross.fields.buy_price') }}</th>
                <th style="width: 6.5rem">{{ __('gross.fields.sale_price') }}</th>
                <th style="width: 11rem">{{ __('gross.fields.line_discount') }}</th>
                <th style="width: 6rem" class="col-num">{{ __('gross.fields.line_total') }}</th>
                <th style="width: 2.5rem"></th>
            </tr>
        </thead>
        <tbody data-gross-lines-body>
            @foreach ($oldLines as $i => $line)
                @include('gross.purchases._line', ['index' => $i, 'line' => $line, 'items' => $items])
            @endforeach
        </tbody>
    </table>
</div>

<div class="card border-0 bg-body-tertiary mb-3" data-gross-totals>
    <div class="card-body py-2">
        <div class="row g-2 small">
            <div class="col-6 col-md-4">
                <div class="text-body-secondary">{{ __('gross.fields.subtotal') }}</div>
                <div class="fw-semibold num-ltr" data-total-subtotal>0.00</div>
            </div>
            <div class="col-6 col-md-4">
                <div class="text-body-secondary">{{ __('gross.fields.invoice_discount') }}</div>
                <div class="fw-semibold num-ltr" data-total-discount>0.00</div>
            </div>
            <div class="col-6 col-md-4">
                <div class="text-body-secondary">{{ __('gross.fields.grand_total') }}</div>
                <div class="fw-semibold num-ltr fs-6" data-total-grand>0.00</div>
            </div>
        </div>
    </div>
</div>

<template id="gross-purchase-line-template">
    @include('gross.purchases._line', [
        'index' => '__INDEX__',
        'line' => [
            'item_id' => '',
            'qty' => 1,
            'unit' => 'PIECE',
            'buy_price' => 0,
            'sale_price' => 0,
            'discount_mode' => 'amount',
            'discount_amount' => 0,
            'discount_percent' => 0,
        ],
        'items' => $items,
    ])
</template>

<div class="mb-3">
    <label class="form-label" for="notes">{{ __('gross.fields.notes') }}</label>
    <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $invoice?->notes) }}</textarea>
</div>

@push('scripts')
<script>
(function () {
    const body = document.querySelector('[data-gross-lines-body]');
    const template = document.getElementById('gross-purchase-line-template');
    const addBtn = document.querySelector('[data-gross-add-line]');
    const filterInput = document.querySelector('[data-gross-item-filter]');
    let lineIndex = body ? body.querySelectorAll('[data-gross-line-row]').length : 0;

    function money(n) {
        return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function lineNet(row) {
        const qty = Number(row.querySelector('[data-line-qty]')?.value || 0);
        const price = Number(row.querySelector('[data-line-buy]')?.value || 0);
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
        const grand = Math.max(0, subtotal - invDiscount);
        const set = function (sel, val) {
            const n = document.querySelector(sel);
            if (n) n.textContent = money(val);
        };
        set('[data-total-subtotal]', subtotal);
        set('[data-total-discount]', invDiscount);
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
    }

    function bindRow(row) {
        row.querySelector('[data-line-item]')?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const sale = row.querySelector('[data-line-sale]');
            const size = row.querySelector('[data-line-size]');
            if (sale && opt?.dataset.sale) sale.value = opt.dataset.sale;
            if (size) size.textContent = opt?.dataset.size || '—';
            recalc();
        });
        row.querySelector('[data-line-discount-mode]')?.addEventListener('change', function () {
            syncLineDiscountMode(row);
            recalc();
        });
        row.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', recalc);
            el.addEventListener('change', recalc);
        });
        row.querySelector('[data-line-remove]')?.addEventListener('click', function () {
            if (body.querySelectorAll('[data-gross-line-row]').length <= 1) return;
            row.remove();
            recalc();
        });
        syncLineDiscountMode(row);
    }

    body?.querySelectorAll('[data-gross-line-row]').forEach(bindRow);

    addBtn?.addEventListener('click', function () {
        if (!template || !body) return;
        const html = template.innerHTML.replace(/__INDEX__/g, String(lineIndex++));
        const wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        body.appendChild(row);
        bindRow(row);
        recalc();
    });

    document.querySelector('[data-gross-supplier-select]')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) return;
        const name = document.getElementById('supplier_name');
        const phone = document.getElementById('supplier_phone');
        const address = document.getElementById('supplier_address');
        if (name) name.value = opt.dataset.name || '';
        if (phone) phone.value = opt.dataset.phone || '';
        if (address) address.value = opt.dataset.address || '';
    });

    document.querySelector('[data-gross-invoice-discount-mode]')?.addEventListener('change', syncInvoiceDiscountMode);
    document.querySelectorAll('[data-gross-invoice-discount-amount], [data-gross-invoice-discount-percent]')
        .forEach(function (el) { el.addEventListener('input', recalc); });

    filterInput?.addEventListener('input', function () {
        const q = (this.value || '').trim().toLowerCase();
        body?.querySelectorAll('[data-line-item]').forEach(function (sel) {
            Array.from(sel.options).forEach(function (opt, i) {
                if (i === 0) return;
                const hay = (opt.dataset.search || opt.textContent || '').toLowerCase();
                opt.hidden = q !== '' && !hay.includes(q);
            });
        });
    });

    syncInvoiceDiscountMode();
    recalc();
})();
</script>
@endpush

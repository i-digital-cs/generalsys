@extends('layouts.app')

@section('title', __('gh.invoices.builder'))

@section('content')
@php
    $isRtl = app()->getLocale() === 'ar';
    $readonly = ! $invoice->isDraft();
    $meta = $catalogMeta ?? ['mode' => 'B2C', 'groups' => [], 'ooc_groups' => [], 'message' => null, 'contract_reference' => null];
    $label = fn ($ar, $en) => $isRtl ? $ar : ($en ?: $ar);
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.builder') }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gh.orders.reference') }}:
                <span class="hp-value num-ltr">{{ $invoice->order_reference }}</span>
                · {{ __('gh.invoice_status.'.$invoice->status) }}
                @if (! empty($meta['contract_reference']))
                    · <span class="num-ltr">{{ $meta['contract_reference'] }}</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.orders.show', $invoice->order_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.show') }}</a>
            <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if ($invoice->lines->where('pricing_review_required', true)->isNotEmpty())
        <div class="alert alert-warning small">{{ __('gh.invoices.ooc_pending_banner') }}</div>
    @endif

    @if ($belowMinimum ?? false)
        <div class="alert alert-warning small" id="gh-min-order-warn">
            {{ __('gh.invoices.min_order_shortfall', [
                'minimum' => number_format((float) $effectiveMinimum, 2),
                'shortfall' => number_format((float) $minShortfall, 2),
                'subtotal' => number_format((float) $invoice->lines_after_discounts, 2),
            ]) }}
        </div>
    @endif

    <div class="row g-3 flex-{{ $isRtl ? 'row-reverse' : 'row' }}">
        <div class="col-lg-5 order-2 order-lg-1">
            <div class="card border-0 shadow-sm sticky-lg-top" style="top:1rem">
                <div class="card-header bg-transparent">
                    <h2 class="h6 card-title mb-0">{{ __('gh.invoices.current') }}</h2>
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <span class="form-label d-block mb-0">{{ __('gh.customers.title') }}</span>
                        <span class="hp-value">{{ $isRtl ? $invoice->customer_name_ar : ($invoice->customer_name_en ?: $invoice->customer_name_ar) }}</span>
                        <span class="badge text-bg-secondary">{{ __('gh.customer_type.'.$invoice->customer_type) }}</span>
                    </p>

                    @if ($readonly)
                        @include('gh.invoices.partials.totals', ['invoice' => $invoice])
                        <a href="{{ route('gh.invoices.show', $invoice) }}" class="btn btn-primary w-100">{{ __('gh.invoices.view') }}</a>
                    @else
                        <form method="post" action="{{ route('gh.invoices.update', $invoice) }}" id="gh-invoice-draft-form">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="draft_version" value="{{ $invoice->draft_version }}">

                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle" id="gh-inv-lines">
                                    <thead>
                                    <tr>
                                        <th>{{ __('gh.fields.product') }}</th>
                                        <th style="width:4.5rem">{{ __('gh.orders.quantity') }}</th>
                                        <th style="width:5.5rem">{{ __('gh.fields.unit_price') }}</th>
                                        <th class="text-end">{{ __('gh.invoices.line_total') }}</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($invoice->lines as $i => $line)
                                        <tr data-offering-id="{{ $line->offering_id }}" class="{{ $line->pricing_review_required ? 'table-warning' : '' }}">
                                            <td>
                                                <div class="fw-semibold small">{{ $line->localizedProductName() }}</div>
                                                <div class="text-body-secondary small">{{ $line->localizedTreatmentName() }}</div>
                                                @if ($line->price_source === 'STAFF' || $line->pricing_review_required || $line->staff_price_approved)
                                                    <span class="badge text-bg-warning">{{ __('gh.invoices.out_of_contract') }}</span>
                                                @endif
                                                <input type="hidden" name="lines[{{ $i }}][offering_id]" value="{{ $line->offering_id }}">
                                                <input type="hidden" name="lines[{{ $i }}][received_line_id]" value="{{ $line->received_line_id }}">
                                                <input type="hidden" name="lines[{{ $i }}][quantity_received]" value="{{ $line->quantity_received }}">
                                                <input type="hidden" name="lines[{{ $i }}][item_discount_type]" value="{{ $line->item_discount_type }}">
                                                <input type="hidden" name="lines[{{ $i }}][item_discount_value]" value="{{ $line->item_discount_value }}">
                                                @if ($line->pricing_review_required || $line->staff_price_approved || $line->price_source === 'STAFF')
                                                    <input type="hidden" name="lines[{{ $i }}][out_of_contract]" value="1">
                                                    <input type="hidden" name="lines[{{ $i }}][approve_price]" value="{{ $line->staff_price_approved || ! $line->pricing_review_required ? '1' : '0' }}">
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" value="{{ $line->quantity }}">
                                            </td>
                                            <td>
                                                @if ($line->pricing_review_required || $line->staff_price_approved || $line->price_source === 'STAFF')
                                                    <input type="number" min="0" step="0.01" class="form-control form-control-sm"
                                                           name="lines[{{ $i }}][unit_price]" value="{{ $line->unit_price }}"
                                                           @if($line->pricing_review_required) required @endif>
                                                @else
                                                    <span class="hp-value num-ltr small"><x-money :amount="$line->unit_price" currency="AED" /></span>
                                                @endif
                                            </td>
                                            <td class="text-end hp-value num-ltr"><x-money :amount="$line->line_total" currency="AED" /></td>
                                            <td><button type="button" class="btn btn-link btn-sm text-danger gh-remove-line" aria-label="{{ __('general.delete') }}">&times;</button></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label" for="invoice_discount_type">{{ __('gh.invoices.invoice_discount') }}</label>
                                    <select name="invoice_discount_type" id="invoice_discount_type" class="form-select form-select-sm">
                                        @foreach (['NONE','FIXED','PERCENT'] as $t)
                                            <option value="{{ $t }}" @selected($invoice->invoice_discount_type===$t)>{{ __('gh.discount_type.'.$t) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="invoice_discount_value">{{ __('gh.invoices.discount_value') }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="invoice_discount_value" id="invoice_discount_value" value="{{ $invoice->invoice_discount_value }}">
                                </div>
                            </div>

                            <div class="border rounded p-2 mb-3" id="gh-min-order-panel"
                                 data-zone-min="{{ $zoneMinimum }}">
                                <label class="form-label mb-1" for="min_order_effective_amount">{{ __('gh.invoices.minimum_order') }}</label>
                                <p class="small text-body-secondary mb-2">
                                    {{ __('gh.invoices.zone_minimum') }}:
                                    <span class="hp-value num-ltr">{{ number_format((float) $zoneMinimum, 2) }}</span> AED
                                </p>
                                @if ($canEditMinimum ?? false)
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm mb-2"
                                           name="min_order_effective_amount" id="min_order_effective_amount"
                                           value="{{ old('min_order_effective_amount', $effectiveMinimum) }}">
                                    <div id="gh-min-order-reason-wrap" class="{{ ($minChanged ?? false) || old('min_order_reason') ? '' : 'd-none' }}">
                                        <label class="form-label" for="min_order_reason">{{ __('gh.invoices.min_reason') }}</label>
                                        <input type="text" class="form-control form-control-sm" name="min_order_reason" id="min_order_reason"
                                               value="{{ old('min_order_reason', $invoice->minOrderOverride?->reason) }}" maxlength="500"
                                               placeholder="{{ __('gh.invoices.min_reason_placeholder') }}">
                                    </div>
                                @else
                                    <p class="hp-value num-ltr mb-0">{{ number_format((float) $effectiveMinimum, 2) }} AED</p>
                                @endif
                            </div>

                            @include('gh.invoices.partials.totals', ['invoice' => $invoice])

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-outline-primary">{{ __('gh.invoices.save_draft') }}</button>
                            </div>
                        </form>

                        <form method="post" action="{{ route('gh.invoices.issue', $invoice) }}" class="mt-2" onsubmit="return confirm(@json(__('gh.invoices.confirm_issue')))">
                            @csrf
                            <input type="hidden" name="draft_version" value="{{ $invoice->draft_version }}">
                            <button type="submit" class="btn btn-primary w-100" @disabled(
                                $invoice->lines->isEmpty()
                                || $invoice->lines->where('pricing_review_required', true)->isNotEmpty()
                                || ($belowMinimum ?? false)
                            )>
                                {{ __('gh.invoices.issue') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7 order-1 order-lg-2">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <h2 class="h6 card-title mb-0">{{ __('gh.invoices.catalog') }}</h2>
                    @unless ($readonly)
                        <input type="search" class="form-control form-control-sm" style="max-width:14rem" id="gh-cat-search" placeholder="{{ __('gh.invoices.search_catalog') }}">
                    @endunless
                </div>
                <div class="card-body">
                    @if ($readonly)
                        <p class="text-body-secondary small mb-0">{{ __('gh.invoices.issued_readonly') }}</p>
                    @else
                        @if (! empty($meta['message']))
                            <div class="alert alert-warning small">{{ $meta['message'] }}</div>
                        @endif

                        <div class="d-flex flex-wrap gap-2 mb-3" id="gh-cat-filters">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-cat="">{{ __('general.all') }}</button>
                            @foreach ($categories as $cat)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cat="{{ $cat->id }}">{{ $label($cat->name_ar, $cat->name_en) }}</button>
                            @endforeach
                        </div>

                        <div class="row g-2" id="gh-catalog-grid"
                             data-locale="{{ app()->getLocale() }}"
                             data-ooc-label="{{ __('gh.invoices.out_of_contract') }}"
                             data-no-treat="{{ __('gh.orders.product_no_treatments') }}">
                            @foreach ($meta['groups'] as $group)
                                @include('gh.invoices.partials.catalog-product-card', ['group' => $group, 'ooc' => false])
                            @endforeach
                        </div>

                        @if (($meta['mode'] ?? '') === 'B2B_CONTRACT')
                            <div class="mt-4 pt-3 border-top">
                                <button class="btn btn-outline-warning btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gh-ooc-panel" aria-expanded="false">
                                    {{ __('gh.invoices.add_out_of_contract') }}
                                </button>
                                <div class="collapse mt-3" id="gh-ooc-panel">
                                    <p class="small text-body-secondary">{{ __('gh.invoices.ooc_hint') }}</p>
                                    <input type="search" class="form-control form-control-sm mb-2" id="gh-ooc-search" placeholder="{{ __('gh.invoices.search_catalog') }}">
                                    <div class="row g-2" id="gh-ooc-grid">
                                        @forelse ($meta['ooc_groups'] as $group)
                                            @include('gh.invoices.partials.catalog-product-card', ['group' => $group, 'ooc' => true])
                                        @empty
                                            <p class="small text-body-secondary">{{ __('gh.invoices.ooc_empty') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@unless ($readonly)
@push('scripts')
<script>
(() => {
  const form = document.getElementById('gh-invoice-draft-form');
  const tbody = document.querySelector('#gh-inv-lines tbody');
  const root = document.getElementById('gh-catalog-grid');
  const oocLabel = root?.dataset.oocLabel || 'OOC';
  const noTreat = root?.dataset.noTreat || '';
  const reindex = () => {
    [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
      tr.querySelectorAll('[name^="lines["]').forEach((input) => {
        input.name = input.name.replace(/lines\[\d+]/, `lines[${i}]`);
      });
    });
  };
  tbody?.addEventListener('click', (e) => {
    if (e.target.closest('.gh-remove-line')) {
      e.target.closest('tr')?.remove();
      reindex();
    }
  });

  const addLine = (payload) => {
    if (!payload.offeringId) {
      window.alert(noTreat);
      return;
    }
    const i = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    if (payload.ooc) tr.classList.add('table-warning');
    const thumb = payload.image
      ? `<img class="gh-product-thumb" src="${payload.image}" alt="" width="40" height="40" loading="lazy">`
      : `<span class="gh-product-thumb gh-product-thumb--fallback" aria-hidden="true"><i class="bi bi-image"></i></span>`;
    const badge = payload.ooc ? `<span class="badge text-bg-warning">${oocLabel}</span>` : '';
    const priceCell = payload.ooc
      ? `<input type="number" min="0" step="0.01" class="form-control form-control-sm" name="lines[${i}][unit_price]" value="" required>`
      : `<span class="hp-value num-ltr small">${payload.price}</span>`;
    tr.innerHTML = `<td><div class="d-flex align-items-center gap-2">${thumb}<div><div class="fw-semibold small"></div><div class="text-body-secondary small"></div>${badge}</div></div>
      <input type="hidden" name="lines[${i}][offering_id]" value="${payload.offeringId}">
      <input type="hidden" name="lines[${i}][item_discount_type]" value="NONE">
      <input type="hidden" name="lines[${i}][item_discount_value]" value="0">
      ${payload.ooc ? `<input type="hidden" name="lines[${i}][out_of_contract]" value="1"><input type="hidden" name="lines[${i}][approve_price]" value="1">` : ''}
      </td>
      <td><input type="number" min="1" class="form-control form-control-sm" name="lines[${i}][quantity]" value="1"></td>
      <td>${priceCell}</td>
      <td class="text-end small text-body-secondary">—</td>
      <td><button type="button" class="btn btn-link btn-sm text-danger gh-remove-line">&times;</button></td>`;
    tr.querySelector('.fw-semibold').textContent = payload.product;
    tr.querySelector('.text-body-secondary').textContent = payload.treatment;
    tbody.appendChild(tr);
  };

  document.querySelectorAll('.gh-add-product').forEach((btn) => {
    btn.addEventListener('click', () => {
      const card = btn.closest('[data-product-card]');
      const treatSel = card?.querySelector('[data-treatment-select]');
      const opt = treatSel?.selectedOptions?.[0];
      addLine({
        offeringId: opt?.value || btn.dataset.offeringId || '',
        product: card?.dataset.productName || btn.dataset.product || '',
        treatment: opt?.textContent?.trim() || btn.dataset.treatment || '',
        price: opt?.dataset.price || btn.dataset.price || '0',
        image: card?.dataset.image || btn.dataset.image || '',
        ooc: card?.dataset.ooc === '1' || btn.dataset.ooc === '1',
      });
    });
  });

  const filterGrid = (gridId, searchId, cat) => {
    const q = (document.getElementById(searchId)?.value || '').toLowerCase();
    document.querySelectorAll(`#${gridId} [data-product-card]`).forEach((card) => {
      const okCat = !cat || String(card.dataset.cat) === String(cat);
      const okQ = !q || (card.dataset.name || '').includes(q);
      card.classList.toggle('d-none', !(okCat && okQ));
    });
  };
  document.querySelectorAll('#gh-cat-filters [data-cat]').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('#gh-cat-filters .btn').forEach((x) => x.classList.remove('active', 'btn-outline-primary'));
      b.classList.add('active', 'btn-outline-primary');
      filterGrid('gh-catalog-grid', 'gh-cat-search', b.dataset.cat);
    });
  });
  document.getElementById('gh-cat-search')?.addEventListener('input', () => {
    const active = document.querySelector('#gh-cat-filters .btn.active');
    filterGrid('gh-catalog-grid', 'gh-cat-search', active?.dataset.cat || '');
  });
  document.getElementById('gh-ooc-search')?.addEventListener('input', () => filterGrid('gh-ooc-grid', 'gh-ooc-search', ''));

  const minInput = document.getElementById('min_order_effective_amount');
  const reasonWrap = document.getElementById('gh-min-order-reason-wrap');
  const panel = document.getElementById('gh-min-order-panel');
  const syncMinReason = () => {
    if (!minInput || !reasonWrap || !panel) return;
    const zone = Number(panel.dataset.zoneMin || 0);
    const val = Number(minInput.value || 0);
    const changed = Math.abs(val - zone) > 0.00001;
    reasonWrap.classList.toggle('d-none', !changed);
  };
  minInput?.addEventListener('input', syncMinReason);
  syncMinReason();
})();
</script>
@endpush
@endunless
@endsection

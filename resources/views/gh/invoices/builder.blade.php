@extends('layouts.app')

@section('title', __('gh.invoices.builder'))

@section('content')
@php
    $isRtl = app()->getLocale() === 'ar';
    $readonly = ! $invoice->isDraft();
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.builder') }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gh.orders.reference') }}:
                <span class="hp-value num-ltr">{{ $invoice->order_reference }}</span>
                · {{ __('gh.invoice_status.'.$invoice->status) }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.orders.show', $invoice->order_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.show') }}</a>
            <a href="{{ route('gh.invoices.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.title') }}</a>
        </div>
    </div>

    @if ($invoice->min_order_adjustment_amount > 0 && ! $invoice->min_order_tax_class)
        <div class="alert alert-warning small">{{ __('gh.invoices.min_tax_unresolved_hint') }}</div>
    @endif

    <div class="row g-3 flex-{{ $isRtl ? 'row-reverse' : 'row' }}">
        {{-- Invoice summary (start in LTR / end in RTL via flex-row-reverse) --}}
        <div class="col-lg-5 order-2 order-lg-1">
            <div class="card border-0 shadow-sm sticky-lg-top" style="top:1rem">
                <div class="card-header bg-transparent">
                    <h2 class="h6 card-title mb-0">{{ __('gh.invoices.current') }}</h2>
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <span class="form-label d-block mb-0">{{ __('gh.customers.title') }}</span>
                        <span class="hp-value">{{ $isRtl ? $invoice->customer_name_ar : ($invoice->customer_name_en ?: $invoice->customer_name_ar) }}</span>
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
                                        <th style="width:5rem">{{ __('gh.orders.quantity') }}</th>
                                        <th class="text-end">{{ __('gh.invoices.line_total') }}</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($invoice->lines as $i => $line)
                                        <tr data-offering-id="{{ $line->offering_id }}">
                                            <td>
                                                <div class="fw-semibold small">{{ $line->localizedProductName() }}</div>
                                                <div class="text-body-secondary small">{{ $line->localizedTreatmentName() }}</div>
                                                <input type="hidden" name="lines[{{ $i }}][offering_id]" value="{{ $line->offering_id }}">
                                                <input type="hidden" name="lines[{{ $i }}][received_line_id]" value="{{ $line->received_line_id }}">
                                                <input type="hidden" name="lines[{{ $i }}][quantity_received]" value="{{ $line->quantity_received }}">
                                                <input type="hidden" name="lines[{{ $i }}][item_discount_type]" value="{{ $line->item_discount_type }}">
                                                <input type="hidden" name="lines[{{ $i }}][item_discount_value]" value="{{ $line->item_discount_value }}">
                                                @if ($line->qty_differs_from_received)
                                                    <span class="badge text-bg-warning">{{ __('gh.invoices.qty_differs') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" value="{{ $line->quantity }}">
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
                                <div class="col-6">
                                    <label class="form-label" for="min_order_override_type">{{ __('gh.invoices.min_override') }}</label>
                                    <select name="min_order_override_type" id="min_order_override_type" class="form-select form-select-sm">
                                        @foreach (['APPLY_DEFAULT','OVERRIDE_AMOUNT','WAIVE'] as $t)
                                            <option value="{{ $t }}" @selected($invoice->min_order_override_type===$t)>{{ __('gh.min_override.'.$t) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="min_order_override_amount">{{ __('gh.invoices.min_override_amount') }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="min_order_override_amount" id="min_order_override_amount" value="{{ $invoice->min_order_effective_amount }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="min_order_reason">{{ __('gh.invoices.min_reason') }}</label>
                                    <input type="text" class="form-control form-control-sm" name="min_order_reason" id="min_order_reason" value="{{ old('min_order_reason') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="min_order_tax_class">{{ __('gh.invoices.min_tax_class') }}</label>
                                    <select name="min_order_tax_class" id="min_order_tax_class" class="form-select form-select-sm">
                                        <option value="">{{ __('gh.invoices.tax_unresolved') }}</option>
                                        @foreach (['STANDARD_RATED','ZERO_RATED','EXEMPT','OUT_OF_SCOPE'] as $tc)
                                            <option value="{{ $tc }}" @selected(($invoice->min_order_tax_class ?? config('general_house.min_order_surcharge_tax_class'))===$tc)>{{ __('gh.tax_class.'.$tc) }}</option>
                                        @endforeach
                                    </select>
                                    <p class="small text-body-secondary mb-0">{{ __('gh.invoices.min_tax_hint') }}</p>
                                </div>
                            </div>

                            @include('gh.invoices.partials.totals', ['invoice' => $invoice])

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-outline-primary">{{ __('gh.invoices.save_draft') }}</button>
                            </div>
                        </form>

                        <form method="post" action="{{ route('gh.invoices.issue', $invoice) }}" class="mt-2" onsubmit="return confirm(@json(__('gh.invoices.confirm_issue')))">
                            @csrf
                            <input type="hidden" name="draft_version" value="{{ $invoice->draft_version }}">
                            <button type="submit" class="btn btn-primary w-100">{{ __('gh.invoices.issue') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Catalog --}}
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
                        <div class="d-flex flex-wrap gap-2 mb-3" id="gh-cat-filters">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-cat="">{{ __('general.all') }}</button>
                            @foreach ($categories as $cat)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cat="{{ $cat->id }}">{{ $isRtl ? $cat->name_ar : ($cat->name_en ?: $cat->name_ar) }}</button>
                            @endforeach
                        </div>
                        <div class="row g-2" id="gh-catalog-grid">
                            @foreach ($catalog as $item)
                                <div class="col-6 col-md-4 gh-cat-card" data-cat="{{ $item['category_id'] }}" data-name="{{ strtolower($item['product_name'].' '.$item['treatment_name']) }}">
                                    <div class="border rounded p-2 h-100 d-flex flex-column">
                                        <div class="fw-semibold small">{{ $item['product_name'] }}</div>
                                        <div class="text-body-secondary small mb-2">{{ $item['treatment_name'] }}</div>
                                        <div class="hp-value num-ltr small mb-2"><x-money :amount="$item['unit_price']" currency="AED" /></div>
                                        <button type="button"
                                                class="btn btn-sm btn-primary mt-auto gh-add-offering"
                                                data-offering-id="{{ $item['offering_id'] }}"
                                                data-product="{{ $item['product_name'] }}"
                                                data-treatment="{{ $item['treatment_name'] }}"
                                                data-price="{{ $item['unit_price'] }}">
                                            {{ __('gh.invoices.add') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
  const reindex = () => {
    [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
      tr.querySelectorAll('input[name^="lines["]').forEach((input) => {
        input.name = input.name.replace(/lines\[\d+]/, `lines[${i}]`);
      });
    });
  };
  document.querySelectorAll('.gh-remove-line').forEach((btn) => {
    btn.addEventListener('click', () => { btn.closest('tr')?.remove(); reindex(); });
  });
  document.querySelectorAll('.gh-add-offering').forEach((btn) => {
    btn.addEventListener('click', () => {
      const i = tbody.querySelectorAll('tr').length;
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><div class="fw-semibold small">${btn.dataset.product}</div><div class="text-body-secondary small">${btn.dataset.treatment}</div>
        <input type="hidden" name="lines[${i}][offering_id]" value="${btn.dataset.offeringId}">
        <input type="hidden" name="lines[${i}][item_discount_type]" value="NONE">
        <input type="hidden" name="lines[${i}][item_discount_value]" value="0"></td>
        <td><input type="number" min="1" class="form-control form-control-sm" name="lines[${i}][quantity]" value="1"></td>
        <td class="text-end small text-body-secondary">—</td>
        <td><button type="button" class="btn btn-link btn-sm text-danger gh-remove-line">&times;</button></td>`;
      tr.querySelector('.gh-remove-line').addEventListener('click', () => { tr.remove(); reindex(); });
      tbody.appendChild(tr);
    });
  });
  const search = document.getElementById('gh-cat-search');
  const filterCat = (cat) => {
    document.querySelectorAll('.gh-cat-card').forEach((card) => {
      const okCat = !cat || String(card.dataset.cat) === String(cat);
      const q = (search?.value || '').toLowerCase();
      const okQ = !q || (card.dataset.name || '').includes(q);
      card.classList.toggle('d-none', !(okCat && okQ));
    });
  };
  document.querySelectorAll('#gh-cat-filters [data-cat]').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('#gh-cat-filters .btn').forEach((x) => x.classList.remove('active', 'btn-outline-primary'));
      b.classList.add('active', 'btn-outline-primary');
      filterCat(b.dataset.cat);
    });
  });
  search?.addEventListener('input', () => {
    const active = document.querySelector('#gh-cat-filters .btn.active');
    filterCat(active?.dataset.cat || '');
  });
})();
</script>
@endpush
@endunless
@endsection

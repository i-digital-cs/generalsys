@extends('layouts.app')

@section('title', __('gh.debit_adjustments.create'))

@section('content')
@php
    $localeAr = app()->getLocale() === 'ar';
@endphp
<div class="container-fluid py-3">
    <div class="mb-3">
        <h1 class="h4 mb-1">{{ __('gh.debit_adjustments.create') }}</h1>
        <p class="text-body-secondary small mb-0">
            {{ __('gh.invoices.number') }}:
            <a class="num-ltr" href="{{ route('gh.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
            @if ($invoice->order)
                · {{ __('gh.orders.show') }}:
                <a class="num-ltr" href="{{ route('gh.orders.show', $invoice->order) }}">{{ $invoice->order_reference }}</a>
            @endif
        </p>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.invoice_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.debit_adjustments.debits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['debits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.credits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['credits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.net_invoiced') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['net_invoiced']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.payments.confirmed_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['confirmed_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.remaining_due') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining_due']" currency="AED" /></span></div>
                </div>
            </div>

            @if ($invoice->debitAdjustments->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.debit_adjustments.existing') }}</h2></div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($invoice->debitAdjustments as $existing)
                            <li class="list-group-item d-flex justify-content-between">
                                <a class="num-ltr" href="{{ route('gh.debit-adjustments.show', $existing) }}">{{ $existing->number ?: '#'.$existing->id }}</a>
                                <span>{{ __('gh.da_status.'.$existing->status) }} · <x-money :amount="$existing->total_debit" currency="AED" /></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-info small mb-0">{{ __('gh.debit_adjustments.create_hint') }}</div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" action="{{ route('gh.debit-adjustments.store', $invoice) }}" id="da-form">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <input type="hidden" name="issue_now" value="1">

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="reason_code">{{ __('gh.debit_adjustments.reason_code') }}</label>
                                <select name="reason_code" id="reason_code" class="form-select" required>
                                    @foreach ($reasons as $code)
                                        <option value="{{ $code }}" @selected(old('reason_code')===$code)>{{ __('gh.da_reason.'.$code) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="reason_text">{{ __('gh.debit_adjustments.reason_text') }}</label>
                                <input type="text" name="reason_text" id="reason_text" class="form-control" value="{{ old('reason_text') }}" required maxlength="2000">
                            </div>
                        </div>

                        <h2 class="h6">{{ __('gh.debit_adjustments.lines') }}</h2>
                        <div id="da-lines" class="d-grid gap-3 mb-3">
                            <div class="border rounded p-2 da-line" data-index="0">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">{{ __('gh.debit_adjustments.line_type') }}</label>
                                        <select name="lines[0][line_type]" class="form-select form-select-sm da-type" required>
                                            @foreach ($lineTypes as $lt)
                                                <option value="{{ $lt }}">{{ __('gh.da_line_type.'.$lt) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 da-offering-wrap">
                                        <label class="form-label">{{ __('gh.fields.offering') }}</label>
                                        <select name="lines[0][offering_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($offerings as $off)
                                                @php
                                                    $label = ($localeAr ? ($off->product?->name_ar ?: $off->product?->name_en) : ($off->product?->name_en ?: $off->product?->name_ar))
                                                        .' / '.($localeAr ? ($off->treatment?->name_ar ?: $off->treatment?->name_en) : ($off->treatment?->name_en ?: $off->treatment?->name_ar));
                                                @endphp
                                                <option value="{{ $off->id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 da-invoice-line-wrap d-none">
                                        <label class="form-label">{{ __('gh.debit_adjustments.invoice_line') }}</label>
                                        <select name="lines[0][invoice_line_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($invoice->lines as $iline)
                                                <option value="{{ $iline->id }}">
                                                    {{ $localeAr ? ($iline->product_name_ar ?: $iline->product_name_en) : ($iline->product_name_en ?: $iline->product_name_ar) }}
                                                    ({{ $iline->quantity }} × {{ $iline->unit_price }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label">{{ __('gh.orders.quantity') }}</label>
                                        <input type="number" min="1" name="lines[0][quantity]" class="form-control form-control-sm text-end" value="1" required>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label">{{ __('gh.invoices.unit_price') }}</label>
                                        <input type="number" min="0.01" step="0.01" name="lines[0][unit_price]" class="form-control form-control-sm text-end" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="da-add-line">{{ __('gh.debit_adjustments.add_line') }}</button>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('gh.debit_adjustments.issue_now') }}</button>
                            <a href="{{ route('gh.invoices.show', $invoice) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="da-line-template">
    <div class="border rounded p-2 da-line" data-index="__INDEX__">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">{{ __('gh.debit_adjustments.line_type') }}</label>
                <select name="lines[__INDEX__][line_type]" class="form-select form-select-sm da-type" required>
                    @foreach ($lineTypes as $lt)
                        <option value="{{ $lt }}">{{ __('gh.da_line_type.'.$lt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 da-offering-wrap">
                <label class="form-label">{{ __('gh.fields.offering') }}</label>
                <select name="lines[__INDEX__][offering_id]" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($offerings as $off)
                        @php
                            $label = ($localeAr ? ($off->product?->name_ar ?: $off->product?->name_en) : ($off->product?->name_en ?: $off->product?->name_ar))
                                .' / '.($localeAr ? ($off->treatment?->name_ar ?: $off->treatment?->name_en) : ($off->treatment?->name_en ?: $off->treatment?->name_ar));
                        @endphp
                        <option value="{{ $off->id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 da-invoice-line-wrap d-none">
                <label class="form-label">{{ __('gh.debit_adjustments.invoice_line') }}</label>
                <select name="lines[__INDEX__][invoice_line_id]" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($invoice->lines as $iline)
                        <option value="{{ $iline->id }}">
                            {{ $localeAr ? ($iline->product_name_ar ?: $iline->product_name_en) : ($iline->product_name_en ?: $iline->product_name_ar) }}
                            ({{ $iline->quantity }} × {{ $iline->unit_price }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('gh.orders.quantity') }}</label>
                <input type="number" min="1" name="lines[__INDEX__][quantity]" class="form-control form-control-sm text-end" value="1" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('gh.invoices.unit_price') }}</label>
                <input type="number" min="0.01" step="0.01" name="lines[__INDEX__][unit_price]" class="form-control form-control-sm text-end" required>
            </div>
        </div>
    </div>
</template>

<script>
(() => {
    const root = document.getElementById('da-lines');
    const tpl = document.getElementById('da-line-template');
    let next = 1;

    function syncType(row) {
        const type = row.querySelector('.da-type')?.value;
        const offering = row.querySelector('.da-offering-wrap');
        const invLine = row.querySelector('.da-invoice-line-wrap');
        const isAdditional = type === 'ADDITIONAL_ITEM';
        offering?.classList.toggle('d-none', !isAdditional);
        invLine?.classList.toggle('d-none', isAdditional);
    }

    root.addEventListener('change', (e) => {
        if (e.target.classList.contains('da-type')) {
            syncType(e.target.closest('.da-line'));
        }
    });

    document.getElementById('da-add-line')?.addEventListener('click', () => {
        const html = tpl.innerHTML.replaceAll('__INDEX__', String(next++));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        root.appendChild(row);
        syncType(row);
    });

    root.querySelectorAll('.da-line').forEach(syncType);
})();
</script>
@endsection

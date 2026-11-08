@extends('layouts.app')

@section('title', __('gh.credit_notes.create'))

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h1 class="h4 mb-1">{{ __('gh.credit_notes.create') }}</h1>
        <p class="text-body-secondary small mb-0">
            {{ __('gh.invoices.number') }}:
            <a class="num-ltr" href="{{ route('gh.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
        </p>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.credit_notes.invoice_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.credits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['credits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.net_invoiced') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['net_invoiced']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.payments.confirmed_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['confirmed_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.remaining_due') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining_due']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.customer_credit') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['customer_credit']" currency="AED" /></span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" action="{{ route('gh.credit-notes.store', $invoice) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="reason_code">{{ __('gh.credit_notes.reason_code') }}</label>
                                <select name="reason_code" id="reason_code" class="form-select" required>
                                    @foreach ($reasons as $code)
                                        <option value="{{ $code }}" @selected(old('reason_code')===$code)>{{ __('gh.cn_reason.'.$code) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="reason_text">{{ __('gh.credit_notes.reason_text') }}</label>
                                <input type="text" name="reason_text" id="reason_text" class="form-control" value="{{ old('reason_text') }}" required maxlength="2000">
                            </div>
                        </div>

                        <h2 class="h6">{{ __('gh.credit_notes.lines') }}</h2>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                <tr>
                                    <th>{{ __('gh.credit_notes.line') }}</th>
                                    <th class="text-end">{{ __('gh.credit_notes.qty_available') }}</th>
                                    <th class="text-end" style="min-width:7rem">{{ __('gh.credit_notes.qty_credit') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($invoice->lines as $i => $line)
                                    <tr>
                                        <td>
                                            <div>{{ app()->getLocale() === 'ar' ? ($line->product_name_ar ?: $line->product_name_en) : ($line->product_name_en ?: $line->product_name_ar) }}</div>
                                            <div class="small text-body-secondary num-ltr">{{ __('gh.tax_class.'.$line->tax_class) }} · <x-money :amount="$line->line_total" currency="AED" /></div>
                                            <input type="hidden" name="lines[{{ $i }}][invoice_line_id]" value="{{ $line->id }}">
                                        </td>
                                        <td class="text-end num-ltr">{{ $line->quantity }}</td>
                                        <td>
                                            <input type="number" min="0" max="{{ $line->quantity }}" class="form-control form-control-sm text-end"
                                                   name="lines[{{ $i }}][quantity_credited]"
                                                   value="{{ old('lines.'.$i.'.quantity_credited', 0) }}">
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-body-secondary">{{ __('gh.credit_notes.create_hint') }}</p>
                        <button type="submit" class="btn btn-primary">{{ __('gh.credit_notes.save_draft') }}</button>
                        <a href="{{ route('gh.invoices.show', $invoice) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', __('gh.refunds.create'))

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h1 class="h4 mb-1">{{ __('gh.refunds.create') }}</h1>
        <p class="text-body-secondary small mb-0">
            {{ __('gh.invoices.number') }}:
            <a class="num-ltr" href="{{ route('gh.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
        </p>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.refunds.balance_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.credits_issued') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['credits_issued']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.net_invoiced') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['net_invoiced']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.payments.confirmed_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['confirmed_total']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.paid_out_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['refunds_paid_out']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>{{ __('gh.credit_notes.customer_credit') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['customer_credit']" currency="AED" /></span></div>
                    <p class="small text-body-secondary mb-0 mt-2">{{ __('gh.refunds.refundable_hint') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" action="{{ route('gh.refunds.store', $invoice) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="amount">{{ __('gh.refunds.amount') }}</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount"
                                       value="{{ old('amount', $balance['customer_credit']) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="reason_code">{{ __('gh.refunds.reason_code') }}</label>
                                <select name="reason_code" id="reason_code" class="form-select" required>
                                    <option value="CUSTOMER_REFUND">{{ __('gh.rf_reason.CUSTOMER_REFUND') }}</option>
                                    <option value="CREDIT_NOTE_PAYOUT">{{ __('gh.rf_reason.CREDIT_NOTE_PAYOUT') }}</option>
                                    <option value="OTHER">{{ __('gh.rf_reason.OTHER') }}</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="reason_text">{{ __('gh.refunds.reason_text') }}</label>
                                <input type="text" class="form-control" name="reason_text" id="reason_text" value="{{ old('reason_text') }}" required maxlength="2000">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="evidence_notes">{{ __('gh.refunds.evidence') }}</label>
                                <textarea class="form-control" name="evidence_notes" id="evidence_notes" rows="2" maxlength="2000">{{ old('evidence_notes') }}</textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('gh.refunds.request') }}</button>
                            <a href="{{ route('gh.invoices.show', $invoice) }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

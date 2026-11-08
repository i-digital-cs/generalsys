@extends('layouts.app')

@section('title', __('gh.refunds.show'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.refunds.show') }}</h1>
            <p class="text-body-secondary small mb-0 num-ltr">{{ $refund->reference ?: __('gh.refunds.pending_ref') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary align-self-center">{{ __('gh.rf_status.'.$refund->status) }}</span>
            <a href="{{ route('gh.refunds.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.refunds.title') }}</a>
            @if ($refund->invoice)
                <a href="{{ route('gh.invoices.show', $refund->invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('gh.invoices.view') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.refunds.details') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.amount') }}</span><span class="hp-value num-ltr"><x-money :amount="$refund->amount" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.method') }}</span><span>{{ __('gh.payment_method.CASH') }}</span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.reason_code') }}</span><span>{{ __('gh.rf_reason.'.$refund->reason_code) }}</span></div>
                    <p class="mt-2 mb-0">{{ $refund->reason_text }}</p>
                    @if ($refund->evidence_notes)
                        <p class="mt-2 mb-0 text-body-secondary">{{ $refund->evidence_notes }}</p>
                    @endif
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.refunds.balance_context') }}</h2></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.customer_credit') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['customer_credit']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.refunds.paid_out_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['refunds_paid_out']" currency="AED" /></span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('gh.credit_notes.remaining_due') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining_due']" currency="AED" /></span></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.refunds.actions') }}</h2></div>
                <div class="card-body d-grid gap-2">
                    @if ($refund->status === 'REQUESTED')
                        @can('submit', $refund)
                            <form method="post" action="{{ route('gh.refunds.submit', $refund) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('gh.refunds.submit') }}</button></form>
                        @endcan
                        @can('cancel', $refund)
                            <form method="post" action="{{ route('gh.refunds.cancel', $refund) }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('general.cancel') }}</button></form>
                        @endcan
                    @endif
                    @if ($refund->status === 'PENDING_APPROVAL')
                        @can('approve', $refund)
                            <form method="post" action="{{ route('gh.refunds.approve', $refund) }}">@csrf<button class="btn btn-success btn-sm">{{ __('gh.refunds.approve') }}</button></form>
                        @endcan
                        @can('reject', $refund)
                            <form method="post" action="{{ route('gh.refunds.reject', $refund) }}" class="d-grid gap-1">
                                @csrf
                                <label class="form-label" for="rejection_reason">{{ __('gh.refunds.rejection_reason') }}</label>
                                <input class="form-control form-control-sm" name="rejection_reason" id="rejection_reason" required>
                                <button class="btn btn-outline-danger btn-sm">{{ __('gh.refunds.reject') }}</button>
                            </form>
                        @endcan
                    @endif
                    @if ($refund->status === 'APPROVED')
                        @can('payOut', $refund)
                            <form method="post" action="{{ route('gh.refunds.pay-out', $refund) }}">@csrf
                                <button class="btn btn-warning btn-sm">{{ __('gh.refunds.pay_out') }}</button>
                                <p class="small text-body-secondary mb-0 mt-1">{{ __('gh.refunds.pay_out_hint') }}</p>
                            </form>
                        @endcan
                        @can('cancel', $refund)
                            <form method="post" action="{{ route('gh.refunds.cancel', $refund) }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('general.cancel') }}</button></form>
                        @endcan
                    @endif
                    @if ($refund->status === 'PAID_OUT')
                        <p class="small text-success mb-0">{{ __('gh.refunds.paid_out_note') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.refunds.audit') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($refund->events as $event)
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span>{{ $event->event_type }} → {{ $event->to_status }}</span>
                            <span class="text-body-secondary num-ltr">{{ $event->actor?->name_en ?? $event->actor?->name_ar }} · {{ optional($event->occurred_at)->timezone(config('general_house.timezone'))->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

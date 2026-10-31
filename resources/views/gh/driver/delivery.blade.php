@extends('layouts.app')

@section('title', $order->reference)

@section('content')
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $order->reference }}</h1>
            <p class="mb-0">
                <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
                <span class="text-body-secondary small">· {{ __('gh.driver.task_delivery') }}</span>
            </p>
        </div>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    @include('gh.driver.partials.address-block', ['order' => $order])

    @if ($awaitingInvoice)
        <div class="alert alert-secondary">{{ __('gh.orders.delivery_awaiting_invoice') }}</div>
    @endif

    @if ($paymentHint)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.payment_hint') }}</h2></div>
            <div class="card-body">
                <p class="mb-1">
                    <span class="badge text-bg-{{ $paymentHint['status'] === 'PAID' ? 'success' : ($paymentHint['status'] === 'PARTIAL' ? 'warning' : 'secondary') }}">
                        {{ __('gh.payment_status.'.$paymentHint['status']) }}
                    </span>
                </p>
                @if ($paymentHint['is_unpaid'])
                    <div class="alert alert-warning py-2 small mb-2">{{ __('gh.driver.unpaid_delivery_warning') }}</div>
                    <p class="small mb-0">
                        {{ __('gh.payments.remaining') }}:
                        <span class="hp-value num-ltr"><x-money :amount="$paymentHint['remaining']" currency="AED" /></span>
                    </p>
                @endif
                <p class="small text-body-secondary mb-0 mt-2">{{ __('gh.driver.no_collect_hint') }}</p>
            </div>
        </div>
    @endif

    @if ($order->operational_notes || $order->delivery_notes)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.ops_notes') }}</h2></div>
            <div class="card-body small">
                @if ($order->operational_notes)<p class="mb-1">{{ $order->operational_notes }}</p>@endif
                @if ($order->delivery_notes)<p class="mb-0 text-body-secondary">{{ $order->delivery_notes }}</p>@endif
            </div>
        </div>
    @endif

    @if ($canConfirm)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.confirm_delivery') }}</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.orders.confirm-delivery', $order) }}" id="gh-driver-delivery-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="delivery_occurred_at">{{ __('gh.orders.actual_delivery_at') }}</label>
                        <input id="delivery_occurred_at" type="datetime-local" name="occurred_at" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="delivery_notes">{{ __('gh.orders.delivery_notes') }}</label>
                        <textarea id="delivery_notes" name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="gh-confirm-delivery-btn"
                            onclick="this.disabled=true; this.form.submit();">{{ __('gh.orders.confirm_delivery') }}</button>
                </form>
            </div>
        </div>
    @elseif ($order->status === \App\Support\GeneralHouse\GhOrderStatus::COMPLETED)
        <div class="alert alert-success">{{ __('gh.flash.delivery_confirmed') }}</div>
    @endif

    @if ($canFail)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.failed_delivery') }}</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.orders.failed-delivery', $order) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="fail_reason">{{ __('gh.driver.fail_reason') }}</label>
                        <select id="fail_reason" name="reason_code" class="form-select" required>
                            <option value="">{{ __('gh.driver.select_reason') }}</option>
                            @foreach ($failReasons as $code)
                                <option value="{{ $code }}">{{ __('gh.attempt_reason.'.$code) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="fail_notes">{{ __('gh.fields.notes') }}</label>
                        <textarea id="fail_notes" name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning btn-lg w-100">{{ __('gh.orders.record_failed_delivery') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if ($order->attempts->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.attempts') }}</h2></div>
            <ul class="list-group list-group-flush small">
                @foreach ($order->attempts as $attempt)
                    <li class="list-group-item">
                        <span class="badge text-bg-warning">{{ $attempt->attempt_type }}</span>
                        <span class="num-ltr">{{ $attempt->occurred_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</span>
                        @if (!empty($attempt->payload['reason_code']))
                            · {{ __('gh.attempt_reason.'.$attempt->payload['reason_code']) }}
                        @endif
                        @if ($attempt->notes) — {{ $attempt->notes }} @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection

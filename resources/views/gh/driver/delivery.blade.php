@extends('layouts.app')

@section('title', $order->reference)

@section('content')
@php
    $deliveryPaymentEnabled = $deliveryPaymentEnabled ?? false;
    $hasBalance = ($paymentHint['is_unpaid'] ?? false) && isset($paymentHint['remaining']);
    $needsMethod = $deliveryPaymentEnabled && $hasBalance && ($canConfirm ?? false);
    $showCashControls = $deliveryPaymentEnabled && ($canConfirm ?? false);
    $displayAmount = $hasBalance ? ($paymentHint['remaining'] ?? null) : null;
@endphp
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $order->reference }}</h1>
            <p class="mb-0 d-flex flex-wrap align-items-center gap-1">
                @include('gh.partials.order-status-badge', ['status' => $order->status])
                @include('gh.partials.order-warning-badges', ['warnings' => $warnings ?? []])
                <span class="text-body-secondary small">· {{ __('gh.driver.task_delivery') }}</span>
            </p>
        </div>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    @include('gh.driver.partials.address-block', ['order' => $order])

    @if ($awaitingInvoice)
        <div class="alert alert-secondary">{{ __('gh.orders.delivery_awaiting_invoice') }}</div>
    @endif

    @if ($paymentHint && $displayAmount !== null)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.remaining_due_label') }}</h2></div>
            <div class="card-body">
                <p class="mb-0 hp-value num-ltr fs-5">
                    <x-money :amount="$displayAmount" currency="AED" />
                </p>
                @unless ($paymentHint['is_unpaid'])
                    <div class="alert alert-success py-2 small mb-0 mt-2">{{ __('gh.driver.already_paid') }}</div>
                @endunless
            </div>
        </div>
    @elseif ($showCashControls && ! $paymentHint)
        <div class="alert alert-secondary small">{{ __('gh.driver.cash_optional_no_invoice') }}</div>
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
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.confirm_delivery_and_pay') }}</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.orders.confirm-delivery', $order) }}" id="gh-driver-delivery-form">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                    <div class="mb-3">
                        <label class="form-label" for="delivery_occurred_at">{{ __('gh.driver.delivery_time') }}</label>
                        <input id="delivery_occurred_at" type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at') }}">
                        <x-gh.uae-tz-notice compact />
                    </div>

                    @if ($showCashControls)
                        <fieldset class="mb-3">
                            <legend class="form-label">{{ __('gh.driver.payment_method') }}</legend>
                            <div class="d-grid gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_cash" value="CASH"
                                           @checked(old('payment_method', $needsMethod ? 'CASH' : '') === 'CASH')
                                           {{ $needsMethod ? 'required' : '' }}>
                                    <label class="form-check-label" for="pay_cash">{{ __('gh.driver.method_CASH') }}</label>
                                </div>
                                @if ($needsMethod)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="pay_online" value="ONLINE"
                                               @checked(old('payment_method') === 'ONLINE')>
                                        <label class="form-check-label" for="pay_online">{{ __('gh.driver.method_ONLINE') }}</label>
                                    </div>
                                @endif
                            </div>
                            @error('payment_method')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </fieldset>

                        <div class="mb-3" id="gh-cash-amount-wrap" hidden>
                            <label class="form-label" for="cash_collected_amount">{{ __('gh.driver.cash_collected_prompt') }}</label>
                            <input id="cash_collected_amount" type="number" inputmode="decimal" step="0.01" min="0.01"
                                   name="cash_collected_amount" class="form-control form-control-lg"
                                   value="{{ old('cash_collected_amount', $displayAmount) }}"
                                   placeholder="0.00">
                            <div class="form-text">{{ __('gh.driver.cash_collected_hint') }}</div>
                            @error('cash_collected_amount')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="delivery_notes">{{ __('gh.orders.delivery_notes') }}</label>
                        <textarea id="delivery_notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="gh-confirm-delivery-btn">
                        {{ __('gh.driver.confirm_delivery_and_pay') }}
                    </button>
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
                        <span class="num-ltr"><x-gh.dubai-time :at="$attempt->occurred_at" :badge="true" /></span>
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

@if ($canConfirm && $showCashControls)
@push('scripts')
<script>
(() => {
  const form = document.getElementById('gh-driver-delivery-form');
  const btn = document.getElementById('gh-confirm-delivery-btn');
  const wrap = document.getElementById('gh-cash-amount-wrap');
  const amount = document.getElementById('cash_collected_amount');
  const syncCash = () => {
    const cash = form?.querySelector('input[name="payment_method"][value="CASH"]');
    const on = !!(cash && cash.checked);
    if (wrap) wrap.hidden = !on;
    if (amount) {
      amount.required = on;
      if (!on) amount.value = amount.value; // keep typed value when toggling
    }
  };
  form?.querySelectorAll('input[name="payment_method"]').forEach((el) => {
    el.addEventListener('change', syncCash);
  });
  syncCash();
  form?.addEventListener('submit', () => { if (btn) btn.disabled = true; });
})();
</script>
@endpush
@endif
@endsection

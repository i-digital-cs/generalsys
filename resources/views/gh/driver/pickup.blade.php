@extends('layouts.app')

@section('title', $order->reference)

@section('content')
<div class="container-fluid py-3" style="max-width:40rem">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $order->reference }}</h1>
            <p class="mb-0">
                <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
                <span class="text-body-secondary small">· {{ __('gh.driver.task_pickup') }}</span>
            </p>
        </div>
        <a href="{{ route('gh.driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver.back') }}</a>
    </div>

    @include('gh.driver.partials.address-block', ['order' => $order])

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.pickup_time') }}</h2></div>
        <div class="card-body small">
            <span class="hp-value num-ltr">
                {{ $order->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}
            </span>
            @if ($order->scheduling_notes)
                <p class="mt-2 mb-0">{{ $order->scheduling_notes }}</p>
            @endif
            @if ($order->operational_notes)
                <p class="mt-2 mb-0 text-body-secondary">{{ $order->operational_notes }}</p>
            @endif
        </div>
    </div>

    @if ($expectedPaymentMethod)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.payment_method') }}</h2></div>
            <div class="card-body">
                <span class="badge text-bg-secondary">{{ __('gh.driver.method_'.$expectedPaymentMethod) }}</span>
                <p class="small text-body-secondary mb-0 mt-2">{{ __('gh.driver.pickup_no_payment_hint') }}</p>
            </div>
        </div>
    @endif

    @if ($canConfirm)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.confirm_pickup_clothes') }}</h2></div>
            <div class="card-body">
                <p class="small text-body-secondary">{{ __('gh.orders.confirm_package_pickup_hint') }}</p>
                <form method="post" action="{{ route('gh.orders.confirm-pickup', $order) }}" id="gh-driver-pickup-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="pickup_occurred_at">{{ __('gh.driver.pickup_time') }}</label>
                        <input id="pickup_occurred_at" type="datetime-local" name="occurred_at" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pickup_notes">{{ __('gh.orders.pickup_notes') }}</label>
                        <textarea id="pickup_notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="gh-confirm-pickup-btn">{{ __('gh.driver.confirm_pickup_clothes') }}</button>
                </form>
            </div>
        </div>
    @elseif ($order->status === \App\Support\GeneralHouse\GhOrderStatus::WORKING)
        <div class="alert alert-success">{{ __('gh.flash.pickup_confirmed') }}</div>
    @elseif ($notYetEligible)
        <div class="alert alert-warning" role="alert">
            <p class="mb-1">{{ $notYetEligible }}</p>
            <p class="small mb-0">
                {{ __('gh.driver.pickup_time') }}:
                <span class="num-ltr">{{ $order->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</span>
            </p>
        </div>
    @endif

    @if ($canFail)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.failed_pickup') }}</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.orders.failed-pickup', $order) }}">
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
                    <button type="submit" class="btn btn-outline-warning btn-lg w-100">{{ __('gh.orders.record_failed_pickup') }}</button>
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

@if ($canConfirm)
@push('scripts')
<script>
(() => {
  const form = document.getElementById('gh-driver-pickup-form');
  const btn = document.getElementById('gh-confirm-pickup-btn');
  form?.addEventListener('submit', () => { if (btn) btn.disabled = true; });
})();
</script>
@endpush
@endif
@endsection

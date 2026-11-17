@extends('layouts.app')

@section('title', __('gh.driver_cash.title').' — '.$driver->localizedName())

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $driver->localizedName() }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.driver-cash.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.title') }}</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <x-stat-card :title="__('gh.driver_cash.collected_total')" :value="$snapshot['collected_total']" icon="bi-cash-stack" color="primary" :show-sparkline="false" />
        </div>
        <div class="col-md-4">
            <x-stat-card :title="__('gh.driver_cash.settled_total')" :value="$snapshot['settled_total']" icon="bi-check2-circle" color="success" :show-sparkline="false" />
        </div>
        <div class="col-md-4">
            <x-stat-card :title="__('gh.driver_cash.outstanding')" :value="$snapshot['outstanding']" icon="bi-wallet2" color="warning" :show-sparkline="false" />
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.settle') }}</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('gh.driver-cash.settle', $driver) }}" class="row g-2">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="col-md-3">
                    <label class="form-label" for="settle_amount">{{ __('gh.driver_cash.settle_amount') }}</label>
                    <input id="settle_amount" type="number" step="0.01" min="0.01" name="amount" class="form-control" required
                           value="{{ old('amount', $snapshot['outstanding']) }}">
                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="receipt_reference">{{ __('gh.driver_cash.receipt_reference') }}</label>
                    <input id="receipt_reference" type="text" name="receipt_reference" class="form-control" maxlength="191" value="{{ old('receipt_reference') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="settle_notes">{{ __('gh.driver_cash.notes') }}</label>
                    <input id="settle_notes" type="text" name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">{{ __('gh.driver_cash.settle') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.collections') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
                            <th>{{ __('gh.driver_cash.order') }}</th>
                            <th class="text-end">AED</th>
                            <th>{{ __('gh.driver_cash.payment_link') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($collections as $c)
                            <tr>
                                <td class="num-ltr">{{ $c->reference }}</td>
                                <td class="num-ltr"><a href="{{ route('gh.orders.show', $c->order_id) }}">{{ $c->order?->reference }}</a></td>
                                <td class="text-end num-ltr hp-value"><x-money :amount="$c->amount" currency="AED" /></td>
                                <td class="small">
                                    @if ($c->payment_id)
                                        <span class="num-ltr">#{{ $c->payment_id }}</span>
                                    @else
                                        {{ __('gh.driver_cash.unlinked_payment') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent">{{ $collections->links() }}</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.settlements') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr>
                            <th>{{ __('gh.driver_cash.settlement_ref') }}</th>
                            <th class="text-end">AED</th>
                            <th>{{ __('gh.driver_cash.receipt_reference') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($settlements as $s)
                            <tr>
                                <td class="num-ltr">{{ $s->reference }}</td>
                                <td class="text-end num-ltr hp-value"><x-money :amount="$s->amount" currency="AED" /></td>
                                <td>{{ $s->receipt_reference ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent">{{ $settlements->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

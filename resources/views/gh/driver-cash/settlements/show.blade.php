@extends('layouts.app')

@section('title', __('gh.driver_cash.show_title', ['reference' => $settlement->reference]))

@section('content')
@php
    $fmt = static function ($at) {
        $text = \App\Support\GeneralHouse\GhDateTimePresenter::format($at);

        return $text === null ? '—' : $text.' '.\App\Support\GeneralHouse\GhDateTimePresenter::uaeBadge();
    };
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><span class="num-ltr">{{ __('gh.driver_cash.show_title', ['reference' => $settlement->reference]) }}</span></h1>
            <div class="d-flex flex-wrap gap-2">
                @if ($settlement->reversal)
                    <span class="badge text-bg-secondary">{{ __('gh.driver_cash.reversed') }}</span>
                @else
                    <span class="badge text-bg-success">{{ __('gh.driver_cash.active') }}</span>
                @endif
                @if ($settlement->allocations->isEmpty())
                    <span class="badge text-bg-warning">{{ __('gh.driver_cash.legacy_badge') }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.driver-cash.settlements.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.settlements_title') }}</a>
            <a href="{{ route('gh.driver-cash.show', $settlement->driver_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.back_to_driver') }}</a>
        </div>
    </div>

    @foreach (['settlement', 'reason', 'allocations', 'idempotency_key'] as $key)
        @error($key)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @endforeach

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.driver') }}</dt>
                <dd class="col-sm-8 col-lg-4 hp-value">{{ $settlement->driver?->localizedName() }}</dd>
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.amount') }}</dt>
                <dd class="col-sm-8 col-lg-4"><x-money :amount="$settlement->amount" currency="AED" /></dd>
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.settled_at') }}</dt>
                <dd class="col-sm-8 col-lg-4 hp-value"><span class="num-ltr">{{ $fmt($settlement->settled_at) }}</span></dd>
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.settled_by') }}</dt>
                <dd class="col-sm-8 col-lg-4 hp-value">{{ $settlement->settler?->localizedName() ?? '—' }}</dd>
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.receipt_reference') }}</dt>
                <dd class="col-sm-8 col-lg-4 hp-value">{{ $settlement->receipt_reference ?: '—' }}</dd>
                <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.unallocated') }}</dt>
                <dd class="col-sm-8 col-lg-4"><x-money :amount="$unallocated" currency="AED" /></dd>
                @if ($settlement->notes)
                    <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.notes') }}</dt>
                    <dd class="col-sm-8 col-lg-10 hp-value">{{ $settlement->notes }}</dd>
                @endif
            </dl>
            @include('gh.driver-cash.partials.tz-note', ['tz' => $displayTz])
        </div>
    </div>

    @if ($settlement->reversal)
        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-secondary">
            <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.reverse_title') }}</h2></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.reversal_reference') }}</dt>
                    <dd class="col-sm-8 col-lg-4 hp-value"><span class="num-ltr">{{ $settlement->reversal->reference }}</span></dd>
                    <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.reversed_at') }}</dt>
                    <dd class="col-sm-8 col-lg-4 hp-value"><span class="num-ltr">{{ $fmt($settlement->reversal->reversed_at) }}</span></dd>
                    <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.reversed_by') }}</dt>
                    <dd class="col-sm-8 col-lg-4 hp-value">{{ $settlement->reversal->reverser?->localizedName() }}</dd>
                    <dt class="col-sm-4 col-lg-2 form-label">{{ __('gh.driver_cash.reason') }}</dt>
                    <dd class="col-sm-8 col-lg-4 hp-value">{{ $settlement->reversal->reason }}</dd>
                </dl>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.allocations') }}</h2></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr>
                    <th>{{ __('gh.driver_cash.collection_ref') }}</th>
                    <th>{{ __('gh.driver_cash.order') }}</th>
                    <th>{{ __('gh.driver_cash.invoice') }}</th>
                    <th>{{ __('gh.driver_cash.occurred_at') }}</th>
                    <th>{{ __('gh.driver_cash.allocated_by') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($settlement->allocations as $a)
                    <tr>
                        <td class="num-ltr text-nowrap">{{ $a->collection?->reference }}</td>
                        <td class="num-ltr text-nowrap">
                            @if ($a->collection?->order)
                                <a href="{{ route('gh.orders.show', $a->collection->order_id) }}">{{ $a->collection->order->reference }}</a>
                            @endif
                        </td>
                        <td class="num-ltr text-nowrap">
                            @if ($a->collection?->order?->invoice)
                                <a href="{{ route('gh.invoices.show', $a->collection->order->invoice) }}">{{ $a->collection->order->invoice->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num-ltr small text-nowrap">{{ $fmt($a->allocated_at) }}</td>
                        <td>{{ $a->allocator?->localizedName() }}</td>
                        <td class="text-end"><x-money :amount="$a->amount" currency="AED" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ __('gh.driver_cash.unallocated_hint') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($mayAllocate)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent">
                <h2 class="h6 card-title mb-1">{{ __('gh.driver_cash.allocate_title') }}</h2>
                <p class="small text-body-secondary mb-0">{{ __('gh.driver_cash.allocate_hint') }}</p>
            </div>
            @if ($outstanding->isEmpty())
                <div class="card-body text-body-secondary">{{ __('gh.driver_cash.no_outstanding_collections') }}</div>
            @else
                <form method="post" action="{{ route('gh.driver-cash.settlements.allocate', $settlement) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    @include('gh.driver-cash.partials.allocation-table', ['collections' => $outstanding, 'displayTz' => $displayTz])
                    <div class="card-body text-end">
                        <button type="submit" class="btn btn-primary">{{ __('gh.driver_cash.allocate_submit') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if ($mayReverse)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent">
                <h2 class="h6 card-title mb-1">{{ __('gh.driver_cash.reverse_title') }}</h2>
                <p class="small text-body-secondary mb-0">{{ __('gh.driver_cash.reverse_hint') }}</p>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('gh.driver-cash.settlements.reverse', $settlement) }}" class="row g-2 align-items-end"
                      onsubmit="return confirm(this.dataset.confirm);" data-confirm="{{ __('gh.driver_cash.reverse_confirm') }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="col-12 col-md-9">
                        <label class="form-label" for="reason">{{ __('gh.driver_cash.reason') }}</label>
                        <input id="reason" type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" maxlength="500" required value="{{ old('reason') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-outline-danger w-100">{{ __('gh.driver_cash.reverse_submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-gh-alloc-row]').forEach(function (row) {
    var box = row.querySelector('[data-gh-alloc-select]');
    var input = row.querySelector('[data-gh-alloc-amount]');
    box.addEventListener('change', function () { input.disabled = !box.checked; });
    row.querySelector('[data-gh-fill]').addEventListener('click', function () {
        box.checked = true; input.disabled = false; input.value = row.dataset.remaining;
    });
});
</script>
@endpush

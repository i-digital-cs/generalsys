@extends('layouts.app')

@section('title', __('gh.driver_cash.create_title', ['driver' => $driver->localizedName()]))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver_cash.create_title', ['driver' => $driver->localizedName()]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.create_hint') }}</p>
        </div>
        <a href="{{ route('gh.driver-cash.show', $driver) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.back_to_driver') }}</a>
    </div>

    @if ($errors->has('settlement') || $errors->has('allocations') || $errors->has('idempotency_key'))
        <div class="alert alert-danger" role="alert">
            {{ $errors->first('settlement') ?: ($errors->first('allocations') ?: $errors->first('idempotency_key')) }}
        </div>
    @endif

    @unless ($summary['reconciled'])
        <div class="alert alert-danger" role="alert">
            {{ __('gh.driver_cash.not_reconciled', ['balance' => $summary['balance_outstanding'], 'ledger' => $summary['outstanding']]) }}
        </div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-4">
            <x-stat-card :title="__('gh.driver_cash.outstanding')" icon="bi-wallet2" color="warning" :show-sparkline="false"><x-money :amount="$summary['outstanding']" currency="AED" /></x-stat-card>
        </div>
        @if (bccomp($summary['unallocated'], '0', 2) > 0)
            <div class="col-12 col-sm-6 col-xl-4">
                <x-stat-card :title="__('gh.driver_cash.unallocated')" icon="bi-exclamation-diamond" color="secondary" :show-sparkline="false" :subtitle="__('gh.driver_cash.unallocated_hint')"><x-money :amount="$summary['unallocated']" currency="AED" /></x-stat-card>
            </div>
        @endif
    </div>

    @if ($collections->isEmpty())
        <div class="card border-0 shadow-sm"><div class="card-body text-body-secondary">{{ __('gh.driver_cash.no_outstanding_collections') }}</div></div>
    @else
        <form method="post" action="{{ route('gh.driver-cash.settle', $driver) }}" id="gh-settle-form"
              data-outstanding="{{ $summary['outstanding'] }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="received_amount">{{ __('gh.driver_cash.received_amount') }}</label>
                        <input id="received_amount" type="number" step="0.01" min="0.01" name="received_amount" required inputmode="decimal"
                               class="form-control @error('received_amount') is-invalid @enderror" value="{{ old('received_amount') }}" data-gh-received>
                        @error('received_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="receipt_reference">{{ __('gh.driver_cash.receipt_reference') }}</label>
                        <input id="receipt_reference" type="text" name="receipt_reference" class="form-control" maxlength="191" value="{{ old('receipt_reference') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="settle_notes">{{ __('gh.driver_cash.notes') }}</label>
                        <input id="settle_notes" type="text" name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                @if ($collections->count() >= $limit)
                    <div class="card-body pb-0"><p class="small text-body-secondary mb-0">{{ __('gh.driver_cash.outstanding_limit_note', ['limit' => $limit]) }}</p></div>
                @endif
                @include('gh.driver-cash.partials.allocation-table', ['collections' => $collections, 'displayTz' => $displayTz])
            </div>

            <div class="card border-0 shadow-sm mb-3 sticky-md-bottom">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <dl class="d-flex flex-wrap gap-4 mb-0 small">
                        <div><dt class="form-label mb-0">{{ __('gh.driver_cash.selected_total') }}</dt><dd class="hp-value num-ltr mb-0" data-gh-selected-total>0.00</dd></div>
                        <div><dt class="form-label mb-0">{{ __('gh.driver_cash.difference') }}</dt><dd class="hp-value num-ltr mb-0" data-gh-difference>0.00</dd></div>
                        <div><dt class="form-label mb-0">{{ __('gh.driver_cash.outstanding_after') }}</dt><dd class="hp-value num-ltr mb-0" data-gh-outstanding-after>{{ $summary['outstanding'] }}</dd></div>
                    </dl>
                    <div class="text-end">
                        <p class="small text-danger mb-1 d-none" data-gh-mismatch>{{ __('gh.driver_cash.totals_must_match') }}</p>
                        <button type="button" class="btn btn-primary" data-gh-open-confirm disabled>{{ __('gh.driver_cash.settle') }}</button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="gh-settle-confirm" tabindex="-1" aria-labelledby="gh-settle-confirm-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="gh-settle-confirm-title">{{ __('gh.driver_cash.confirm_title') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('gh.driver_cash.cancel') }}"></button>
                        </div>
                        <div class="modal-body" data-gh-confirm-body data-template="{{ __('gh.driver_cash.confirm_body', ['amount' => '__AMOUNT__', 'count' => '__COUNT__']) }}"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('gh.driver_cash.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('gh.driver_cash.confirm') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('gh-settle-form');
    if (!form) { return; }
    var cents = function (v) { var n = parseFloat(v); return isFinite(n) ? Math.round(n * 100) : 0; };
    var fmt = function (c) { var s = c < 0 ? '-' : ''; c = Math.abs(c); return s + Math.floor(c / 100) + '.' + String(c % 100).padStart(2, '0'); };
    var received = form.querySelector('[data-gh-received]');
    var button = form.querySelector('[data-gh-open-confirm]');
    var outstanding = cents(form.dataset.outstanding);

    function recalc() {
        var total = 0, count = 0;
        form.querySelectorAll('[data-gh-alloc-row]').forEach(function (row) {
            var box = row.querySelector('[data-gh-alloc-select]');
            var input = row.querySelector('[data-gh-alloc-amount]');
            input.disabled = !box.checked;
            if (box.checked && cents(input.value) > 0) { total += cents(input.value); count++; }
        });
        var rec = cents(received.value);
        form.querySelector('[data-gh-selected-total]').textContent = fmt(total);
        form.querySelector('[data-gh-difference]').textContent = fmt(rec - total);
        form.querySelector('[data-gh-outstanding-after]').textContent = fmt(outstanding - rec);
        var ok = rec > 0 && count > 0 && total === rec;
        form.querySelector('[data-gh-mismatch]').classList.toggle('d-none', ok || count === 0);
        button.disabled = !ok;
        form.dataset.count = count;
        form.dataset.total = fmt(total);
    }

    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    form.querySelectorAll('[data-gh-fill]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('[data-gh-alloc-row]');
            row.querySelector('[data-gh-alloc-select]').checked = true;
            row.querySelector('[data-gh-alloc-amount]').value = row.dataset.remaining;
            recalc();
        });
    });
    button.addEventListener('click', function () {
        var body = form.querySelector('[data-gh-confirm-body]');
        body.textContent = body.dataset.template.replace('__AMOUNT__', form.dataset.total).replace('__COUNT__', form.dataset.count);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('gh-settle-confirm')).show();
    });
    recalc();
})();
</script>
@endpush

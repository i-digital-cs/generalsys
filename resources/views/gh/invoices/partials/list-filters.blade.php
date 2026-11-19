@php
    $f = fn (string $key) => $filters[$key] ?? '';
    $advanced = collect(['number', 'order', 'customer', 'driver_id', 'from', 'to', 'payment_status', 'settlement_status'])
        ->contains(fn ($k) => $f($k) !== '');
@endphp
<form method="get" class="row g-2 align-items-end">
    <div class="col-12 col-md-4">
        <label class="form-label" for="q">{{ __('gh.invoices.search') }}</label>
        <input id="q" name="q" class="form-control form-control-sm" value="{{ $f('q') }}">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="status">{{ __('gh.invoices.status') }}</label>
        <select id="status" name="status" class="form-select form-select-sm">
            <option value="">{{ __('general.all') }}</option>
            @foreach (\App\Support\GeneralHouse\GhInvoiceStatus::ALL as $s)
                <option value="{{ $s }}" @selected($f('status') === $s)>{{ __('gh.invoice_status.'.$s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" for="settlement_status">{{ __('gh.invoices.settlement_status') }}</label>
        <select id="settlement_status" name="settlement_status" class="form-select form-select-sm">
            <option value="">{{ __('general.all') }}</option>
            @foreach (\App\Support\GeneralHouse\GhCashSettlementStatus::ALL as $s)
                <option value="{{ $s }}" @selected($f('settlement_status') === $s)>{{ __('gh.cash_settlement_status.'.$s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <button class="btn btn-primary btn-sm w-100">{{ __('general.filter') }}</button>
    </div>
    <div class="col-6 col-md-2">
        <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#gh-invoice-more-filters"
                aria-expanded="{{ $advanced ? 'true' : 'false' }}" aria-controls="gh-invoice-more-filters">{{ __('gh.invoices.more_filters') }}</button>
    </div>
    <div class="collapse @if ($advanced) show @endif col-12" id="gh-invoice-more-filters">
        <div class="row g-2">
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="number">{{ __('gh.invoices.number') }}</label>
                <input id="number" name="number" class="form-control form-control-sm" value="{{ $f('number') }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="order">{{ __('gh.invoices.order_number') }}</label>
                <input id="order" name="order" class="form-control form-control-sm" value="{{ $f('order') }}">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label" for="customer">{{ __('gh.fields.customer') }}</label>
                <input id="customer" name="customer" class="form-control form-control-sm" value="{{ $f('customer') }}">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label" for="driver_id">{{ __('gh.invoices.driver') }}</label>
                <select id="driver_id" name="driver_id" class="form-select form-select-sm">
                    <option value="">{{ __('general.all') }}</option>
                    @foreach ($driverOptions as $d)
                        <option value="{{ $d->id }}" @selected((string) $f('driver_id') === (string) $d->id)>{{ $d->localizedName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label" for="from">{{ __('gh.invoices.date_from') }}</label>
                <input id="from" type="date" name="from" class="form-control form-control-sm" value="{{ $f('from') }}">
            </div>
            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label" for="to">{{ __('gh.invoices.date_to') }}</label>
                <input id="to" type="date" name="to" class="form-control form-control-sm" value="{{ $f('to') }}">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label" for="payment_status">{{ __('gh.invoices.payment_status') }}</label>
                <select id="payment_status" name="payment_status" class="form-select form-select-sm">
                    <option value="">{{ __('general.all') }}</option>
                    @foreach (\App\Support\GeneralHouse\GhInvoicePaymentStatus::ALL as $s)
                        <option value="{{ $s }}" @selected($f('payment_status') === $s)>{{ __('gh.payment_status.'.$s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</form>

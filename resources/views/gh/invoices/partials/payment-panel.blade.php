@php
    $payBadge = match ($balance['payment_status'] ?? 'UNPAID') {
        'PAID' => 'success',
        'PARTIAL' => 'warning',
        default => 'secondary',
    };
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 card-title mb-0">{{ __('gh.payments.summary') }}</h2>
        <span class="badge text-bg-{{ $payBadge }}">{{ __('gh.payment_status.'.$balance['payment_status']) }}</span>
    </div>
    <div class="card-body">
        <div class="border rounded p-2 mb-3 small">
            <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['invoice_total']" currency="AED" /></span></div>
            <div class="d-flex justify-content-between"><span>{{ __('gh.payments.confirmed_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['confirmed_total']" currency="AED" /></span></div>
            <div class="d-flex justify-content-between"><span>{{ __('gh.payments.pending_total') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['pending_total']" currency="AED" /></span></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between fw-bold"><span>{{ __('gh.payments.remaining') }}</span><span class="hp-value num-ltr"><x-money :amount="$balance['remaining']" currency="AED" /></span></div>
        </div>

        @if ($invoice->isIssued() && config('general_house.payments.cash_enabled') && \App\Support\GeneralHouse\GhMoney::cmp($balance['remaining'], '0') > 0)
            @can('recordCash', [\App\Models\GeneralHouse\GhPayment::class, $invoice])
                <form method="post" action="{{ route('gh.payments.cash', $invoice) }}" class="mb-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <h3 class="h6">{{ __('gh.payments.record_cash') }}</h3>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="cash_amount">{{ __('gh.payments.amount') }}</label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" id="cash_amount" value="{{ old('amount', $balance['remaining']) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cash_occurred_at">{{ __('gh.payments.occurred_at') }}</label>
                            <input type="datetime-local" class="form-control form-control-sm" name="occurred_at" id="cash_occurred_at" value="{{ old('occurred_at') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="cash_notes">{{ __('gh.fields.notes') }}</label>
                            <input type="text" class="form-control form-control-sm" name="notes" id="cash_notes" value="{{ old('notes') }}" maxlength="1000">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.payments.confirm_cash') }}</button>
                        </div>
                    </div>
                    <p class="small text-body-secondary mb-0 mt-1">{{ __('gh.payments.cash_hint') }}</p>
                </form>
            @endcan
        @endif

        @if ($invoice->isIssued() && config('general_house.payments.online_initiation_enabled'))
            @can('initiateOnline', [\App\Models\GeneralHouse\GhPayment::class, $invoice])
                <form method="post" action="{{ route('gh.payments.online', $invoice) }}" class="mb-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <h3 class="h6">{{ __('gh.payments.initiate_online') }}</h3>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="online_amount">{{ __('gh.payments.amount') }}</label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" id="online_amount" value="{{ old('amount', $balance['remaining']) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="online_ext">{{ __('gh.payments.external_ref') }}</label>
                            <input type="text" class="form-control form-control-sm" name="external_ref" id="online_ext" value="{{ old('external_ref') }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('gh.payments.initiate_online_btn') }}</button>
                        </div>
                    </div>
                    <p class="small text-body-secondary mb-0 mt-1">{{ __('gh.payments.online_hint') }}</p>
                </form>
            @endcan
        @endif

        <h3 class="h6">{{ __('gh.payments.history') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.payments.reference') }}</th>
                    <th>{{ __('gh.payments.method') }}</th>
                    <th>{{ __('gh.invoices.status') }}</th>
                    <th class="text-end">{{ __('gh.payments.amount') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($invoice->payments as $payment)
                    <tr>
                        <td class="num-ltr small">{{ $payment->reference }}</td>
                        <td>{{ __('gh.payment_method.'.$payment->method) }}</td>
                        <td><span class="badge text-bg-{{ $payment->status === 'CONFIRMED' ? 'success' : ($payment->status === 'PENDING' ? 'warning' : 'secondary') }}">{{ __('gh.pay_status.'.$payment->status) }}</span></td>
                        <td class="text-end num-ltr"><x-money :amount="$payment->amount" currency="AED" /></td>
                        <td class="text-end">
                            @if ($payment->isPending())
                                @can('cancelPending', $payment)
                                    <form method="post" action="{{ route('gh.payments.cancel', $payment) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0">{{ __('gh.payments.cancel') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-body-secondary small">{{ __('gh.payments.empty_history') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @unless (config('general_house.payments.bank_transfer_enabled'))
            <p class="small text-body-secondary mb-0 mt-2">{{ __('gh.payments.bank_disabled_note') }}</p>
        @endunless
    </div>
</div>

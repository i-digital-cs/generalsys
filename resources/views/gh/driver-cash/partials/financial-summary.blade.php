@php
    /** @var array<string, mixed> $financial */
    $fmt = fn ($at) => $at?->timezone($displayTz)->translatedFormat('Y-m-d H:i');
    $invoice = $financial['invoice'];
    $balance = $financial['balance'];
    $custody = $financial['custody'];
    $showInvoice = $showInvoice ?? true;
@endphp
<div class="card border-0 shadow-sm mt-3" id="gh-financial-summary">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 card-title mb-0">{{ $title ?? __('gh.order_financial.title') }}</h2>
        @include('gh.driver-cash.partials.status-badge', ['status' => $financial['settlement_status']])
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if ($showInvoice)
                <div class="col-12 col-lg-6">
                    @if ($invoice === null || $balance === null)
                        <p class="text-body-secondary small mb-0">{{ __('gh.order_financial.no_invoice') }}</p>
                    @else
                        <dl class="row small mb-0">
                            <dt class="col-6 form-label mb-1">{{ __('gh.invoices.number') }}</dt>
                            <dd class="col-6 mb-1"><a class="num-ltr" href="{{ route('gh.invoices.show', $invoice) }}">{{ $invoice->number }}</a></dd>
                            <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.invoice_total') }}</dt>
                            <dd class="col-6 mb-1"><x-money :amount="$balance['net_invoiced']" currency="AED" /></dd>
                            <dt class="col-6 form-label mb-1">{{ __('gh.invoices.payment_method_label') }}</dt>
                            <dd class="col-6 mb-1 hp-value">{{ collect($financial['payment_methods'])->map(fn ($m) => __('gh.payment_method.'.$m))->implode(', ') ?: '—' }}</dd>
                            <dt class="col-6 form-label mb-1">{{ __('gh.invoices.payment_status') }}</dt>
                            <dd class="col-6 mb-1 hp-value">{{ __('gh.payment_status.'.$balance['payment_status']) }}</dd>
                            <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.amount_paid') }}</dt>
                            <dd class="col-6 mb-1"><x-money :amount="$balance['cash_net']" currency="AED" /></dd>
                            <dt class="col-6 form-label mb-0">{{ __('gh.order_financial.remaining_due') }}</dt>
                            <dd class="col-6 mb-0 fw-semibold"><x-money :amount="$balance['remaining_due']" currency="AED" /></dd>
                        </dl>
                    @endif
                </div>
            @endif
            <div class="col-12 @if ($showInvoice) col-lg-6 @endif">
                @if ($financial['settlement_status'] === \App\Support\GeneralHouse\GhCashSettlementStatus::NOT_APPLICABLE)
                    <p class="text-body-secondary small mb-0">{{ __('gh.order_financial.direct_payment') }}</p>
                @else
                    <dl class="row small mb-0">
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.driver') }}</dt>
                        <dd class="col-6 mb-1 hp-value">
                            @foreach ($financial['drivers'] as $d)
                                <a href="{{ route('gh.driver-cash.show', $d->id) }}">{{ $d->localizedName() }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.collected_by_driver') }}</dt>
                        <dd class="col-6 mb-1"><x-money :amount="$custody['collected']" currency="AED" /></dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.collected_at') }}</dt>
                        <dd class="col-6 mb-1 hp-value"><span class="num-ltr">{{ $fmt($financial['last_collected_at']) ?? '—' }}</span></dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.settled') }}</dt>
                        <dd class="col-6 mb-1"><x-money :amount="$custody['settled']" currency="AED" /></dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.remaining') }}</dt>
                        <dd class="col-6 mb-1 fw-semibold"><x-money :amount="$custody['remaining']" currency="AED" /></dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.order_financial.settlement_refs') }}</dt>
                        <dd class="col-6 mb-1">
                            @forelse ($custody['settlements'] as $s)
                                <a href="{{ route('gh.driver-cash.settlements.show', $s) }}" @class(['num-ltr', 'text-decoration-line-through' => $s->reversal !== null])>{{ $s->reference }}</a>@if (! $loop->last), @endif
                            @empty
                                —
                            @endforelse
                        </dd>
                        <dt class="col-6 form-label mb-0">{{ __('gh.order_financial.last_settled_at') }}</dt>
                        <dd class="col-6 mb-0 hp-value"><span class="num-ltr">{{ $fmt($custody['last_settled_at']) ?? '—' }}</span></dd>
                    </dl>
                @endif
            </div>
        </div>
    </div>
    <div class="card-header bg-transparent border-top"><h3 class="h6 card-title mb-0">{{ __('gh.order_financial.history') }}</h3></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr>
                <th>{{ __('gh.driver_cash.occurred_at') }}</th>
                <th>{{ __('gh.driver_cash.entry') }}</th>
                <th>{{ __('gh.driver_cash.reference') }}</th>
                <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                <th>{{ __('gh.order_financial.driver') }} / {{ __('gh.driver_cash.manager') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($financial['timeline'] as $e)
                <tr>
                    <td class="num-ltr small text-nowrap">{{ $fmt($e['at']) }}</td>
                    <td>
                        {{ __('gh.order_financial.event.'.$e['type']) }}
                        @if ($e['type'] === 'settlement_reversed' && $e['meta'])
                            <div class="small text-body-secondary">{{ $e['meta'] }}</div>
                        @elseif ($e['meta'])
                            <span class="small text-body-secondary">· {{ __('gh.payment_method.'.$e['meta']) }}</span>
                        @endif
                    </td>
                    <td class="num-ltr text-nowrap">
                        @if ($e['settlement_id'])
                            <a href="{{ route('gh.driver-cash.settlements.show', $e['settlement_id']) }}">{{ $e['reference'] }}</a>
                        @else
                            {{ $e['reference'] ?? '—' }}
                        @endif
                    </td>
                    <td class="text-end">@if ($e['amount'] !== null)<x-money :amount="$e['amount']" currency="AED" />@endif</td>
                    <td class="small">{{ $e['actor']?->localizedName() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-body-secondary">{{ __('gh.order_financial.no_history') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">@include('gh.driver-cash.partials.tz-note', ['tz' => $displayTz])</div>
</div>

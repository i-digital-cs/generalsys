@php
    $fmt = static function ($at) {
        $text = \App\Support\GeneralHouse\GhDateTimePresenter::format($at);

        return $text === null ? '—' : $text.' '.\App\Support\GeneralHouse\GhDateTimePresenter::uaeBadge();
    };
    $empty = collect($data)->every(fn ($rows) => $rows->isEmpty());
@endphp
<div class="card-body">
    @if ($empty)
        <p class="text-body-secondary mb-0">{{ __('gh.driver_cash.no_adjustments') }}</p>
    @endif

    @if ($data['reversals']->isNotEmpty())
        <h3 class="h6 card-title">{{ __('gh.driver_cash.reversals_title') }}</h3>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ __('gh.driver_cash.reversal_reference') }}</th>
                    <th>{{ __('gh.driver_cash.settlement_ref') }}</th>
                    <th>{{ __('gh.driver_cash.reversed_at') }}</th>
                    <th>{{ __('gh.driver_cash.reversed_by') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                    <th>{{ __('gh.driver_cash.reason') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($data['reversals'] as $r)
                    <tr>
                        <td class="num-ltr text-nowrap">{{ $r->reference }}</td>
                        <td class="num-ltr text-nowrap"><a href="{{ route('gh.driver-cash.settlements.show', $r->settlement_id) }}">{{ $r->settlement?->reference }}</a></td>
                        <td class="num-ltr small text-nowrap">{{ $fmt($r->reversed_at) }}</td>
                        <td>{{ $r->reverser?->localizedName() }}</td>
                        <td class="text-end"><x-money :amount="$r->amount" currency="AED" /></td>
                        <td class="small">{{ $r->reason }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($data['late_allocations']->isNotEmpty())
        <h3 class="h6 card-title">{{ __('gh.driver_cash.late_allocations_title') }}</h3>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ __('gh.driver_cash.settlement_ref') }}</th>
                    <th>{{ __('gh.driver_cash.collection_ref') }}</th>
                    <th>{{ __('gh.driver_cash.occurred_at') }}</th>
                    <th>{{ __('gh.driver_cash.allocated_by') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($data['late_allocations'] as $a)
                    <tr>
                        <td class="num-ltr text-nowrap"><a href="{{ route('gh.driver-cash.settlements.show', $a->settlement_id) }}">{{ $a->settlement?->reference }}</a></td>
                        <td class="num-ltr text-nowrap">{{ $a->collection?->reference }}</td>
                        <td class="num-ltr small text-nowrap">{{ $fmt($a->allocated_at) }}</td>
                        <td>{{ $a->allocator?->localizedName() }}</td>
                        <td class="text-end"><x-money :amount="$a->amount" currency="AED" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $invoiceAdjustments = collect()
            ->concat($data['credit_notes']->map(fn ($n) => ['label' => __('gh.driver_cash.credit_notes'), 'ref' => $n->number, 'invoice' => $n->invoice, 'at' => $n->issued_at, 'amount' => $n->total_credit]))
            ->concat($data['debit_adjustments']->map(fn ($d) => ['label' => __('gh.driver_cash.debit_adjustments'), 'ref' => $d->number, 'invoice' => $d->invoice, 'at' => $d->issued_at, 'amount' => $d->total_debit]))
            ->concat($data['refunds']->map(fn ($r) => ['label' => __('gh.driver_cash.refunds'), 'ref' => $r->reference, 'invoice' => $r->invoice, 'at' => $r->paid_out_at, 'amount' => $r->amount]))
            ->sortByDesc(fn ($row) => $row['at']?->getTimestamp());
    @endphp
    @if ($invoiceAdjustments->isNotEmpty())
        <h3 class="h6 card-title">{{ __('gh.driver_cash.invoice_adjustments_title') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>{{ __('gh.driver_cash.entry') }}</th>
                    <th>{{ __('gh.driver_cash.reference') }}</th>
                    <th>{{ __('gh.driver_cash.invoice') }}</th>
                    <th>{{ __('gh.driver_cash.occurred_at') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($invoiceAdjustments as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num-ltr text-nowrap">{{ $row['ref'] }}</td>
                        <td class="num-ltr text-nowrap">
                            @if ($row['invoice'])
                                <a href="{{ route('gh.invoices.show', $row['invoice']) }}">{{ $row['invoice']->number }}</a>
                            @endif
                        </td>
                        <td class="num-ltr small text-nowrap">{{ $fmt($row['at']) }}</td>
                        <td class="text-end"><x-money :amount="$row['amount']" currency="AED" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

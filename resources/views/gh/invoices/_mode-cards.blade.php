{{-- Modern card presentation (working tree) --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.invoices.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.title') }}</a>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="invoices">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                @include('gh.invoices.partials.list-filters')
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="gh-card-ui__empty">
                <p class="text-center text-body-secondary mb-0 py-3">{{ __('gh.invoices.empty') }}</p>
            </div>
        @else
            <div class="gh-card-ui__grid">
                @foreach ($rows as $row)
                    @php
                        $customer = app()->getLocale()==='ar' ? $row->customer_name_ar : ($row->customer_name_en ?: $row->customer_name_ar);
                        $title = $row->number ?? '—';
                    @endphp
                    <x-gh.record-card :title="$title" :title-ltr="true">
                        <x-slot:badges>
                            <span class="gh-record-card__badge {{ $row->status==='ISSUED' ? 'gh-record-card__badge--status' : 'gh-record-card__badge--muted' }}">{{ __('gh.invoice_status.'.$row->status) }}</span>
                            @if ($row->isIssued() && $row->payment_status)
                                <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('gh.payment_status.'.$row->payment_status) }}</span>
                            @endif
                            @include('gh.driver-cash.partials.status-badge', ['status' => $row->cash_settlement_status])
                        </x-slot:badges>
                        <x-slot:meta>
                            <x-gh.meta-cell icon="bi-receipt" :value="$row->order_reference" :ltr="true" />
                            <x-gh.meta-cell icon="bi-person" :value="$customer" />
                            <x-gh.meta-cell icon="bi-calendar3" :value="($row->issued_at ?? $row->created_at)?->timezone($displayTz)->translatedFormat('Y-m-d')" :ltr="true" />
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->total_payable" currency="AED" /></span>
                            </div>
                            <div class="gh-record-card__cell" title="{{ __('gh.invoices.amount_paid') }}">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-credit-card"></i></span>
                                <span class="gh-record-card__value">
                                    <x-money :amount="$row->payments_sum_amount ?? 0" currency="AED" />
                                    <span class="small text-body-secondary">{{ $row->payments->pluck('method')->unique()->map(fn ($m) => __('gh.payment_method.'.$m))->implode(', ') }}</span>
                                </span>
                            </div>
                            @if (bccomp($row->driver_cash_collected, '0', 2) > 0)
                                <div class="gh-record-card__cell" title="{{ __('gh.invoices.driver_outstanding') }}">
                                    <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                    <span class="gh-record-card__value small">
                                        {{ __('gh.invoices.driver_collected') }}: <x-money :amount="$row->driver_cash_collected" currency="AED" /> ·
                                        {{ __('gh.invoices.driver_outstanding') }}: <x-money :amount="$row->driver_cash_outstanding" currency="AED" />
                                    </span>
                                </div>
                            @endif
                        </x-slot:meta>
                        <x-slot:actions>
                            @if ($row->isDraft())
                                <a href="{{ route('gh.invoices.builder', $row) }}" class="gh-board-btn">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                    <span>{{ __('gh.invoices.builder') }}</span>
                                </a>
                            @else
                                <a href="{{ route('gh.invoices.show', $row) }}" class="gh-board-btn">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    <span>{{ __('gh.invoices.view') }}</span>
                                </a>
                            @endif
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            @if ($rows->hasPages())
                <div class="gh-card-ui__pager">{{ $rows->links() }}</div>
            @endif
        @endif
    </div>
</div>

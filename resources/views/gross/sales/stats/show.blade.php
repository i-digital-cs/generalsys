@extends('layouts.app')

@section('title', __('gross.sales_stats.detail_title', ['name' => $name]))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales_stats.detail_title', ['name' => $name]) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales_stats.detail_subtitle') }}</p>
        </div>
        <a href="{{ route('gross.sales.stats', $filters) }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales_stats.back') }}</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.net_merchandise')"
                icon="bi-cash-stack"
                color="primary"
                :show-sparkline="false"
            >
                <span class="num-ltr"><x-money :amount="$kpis['net_merchandise']" :currency="$filters['currency']" /></span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.invoices')"
                icon="bi-receipt"
                color="info"
                :show-sparkline="false"
                :value="$kpis['invoices_count']"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.cod_collected')"
                icon="bi-cash-coin"
                color="success"
                :show-sparkline="false"
            >
                <span class="num-ltr"><x-money :amount="$kpis['cod_collected']" :currency="$filters['currency']" /></span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.returned_merchandise')"
                icon="bi-arrow-return-left"
                color="warning"
                :show-sparkline="false"
            >
                <span class="num-ltr"><x-money :amount="$kpis['returned_merchandise']" :currency="$filters['currency']" /></span>
            </x-stat-card>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($invoices->isEmpty())
                <x-empty-state :title="__('gross.sales_stats.empty')" icon="bi-receipt" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.date') }}</th>
                                <th>{{ __('gross.fields.customer') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.sales_stats.net_merchandise') }}</th>
                                <th class="col-num">{{ __('gross.sales_stats.cod_collected') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                @php
                                    $netMerch = max(0, round((float) $invoice->net_subtotal - (float) $invoice->net_discount, 2));
                                    $codAmt = $invoice->cod_collection_status === \App\Models\Gross\GrossSalesInvoice::COD_COLLECTED
                                        ? (float) ($invoice->cod_collected_amount ?? 0)
                                        : 0;
                                    $when = $invoice->confirmed_at ?? $invoice->created_at;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.sales.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="small"><span class="num-ltr">{{ $when?->translatedFormat('d M Y') }}</span></td>
                                    <td>{{ $invoice->customer_name }}</td>
                                    <td>{{ __('gross.statuses.'.$invoice->status) }}</td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$netMerch" :currency="$invoice->currency" /></span>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$codAmt" :currency="$invoice->currency" /></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('gross.sales.show', $invoice) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $invoices->links() }}</div>
            @endif
        </div>
    </div>
@endsection

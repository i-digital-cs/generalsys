@extends('layouts.app')

@section('title', __('gross.sales.fulfillment_queues.'.$queue))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales.fulfillment_queues.'.$queue) }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales.fulfillment_title') }}</p>
        </div>
        <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales.title') }}</a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['preparing', 'ready', 'with_courier', 'delivered', 'returned'] as $q)
            <a href="{{ route('gross.sales.fulfillment', $q) }}"
               @class(['btn btn-sm', $queue === $q ? 'btn-primary' : 'btn-outline-secondary'])>
                {{ __('gross.sales.fulfillment_queues.'.$q) }}
            </a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($invoices->isEmpty())
                <x-empty-state :title="__('gross.sales.fulfillment_empty')" icon="bi-truck" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.customer') }}</th>
                                <th>{{ __('gross.fields.shipping_company') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.fields.total') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.sales.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>{{ $invoice->customer_name }}</td>
                                    <td>{{ $invoice->shipping_company_name ?: '—' }}</td>
                                    <td>
                                        {{ __('gross.statuses.'.$invoice->status) }}
                                        @if ($invoice->fulfillment_status)
                                            <div class="small text-body-secondary">{{ __('gross.sales.fulfillment.'.$invoice->fulfillment_status) }}</div>
                                        @endif
                                    </td>
                                    <td class="col-num"><span class="num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></span></td>
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

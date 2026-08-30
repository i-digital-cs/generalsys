@extends('layouts.app')

@section('title', __('gross.sales.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossSalesInvoice::class)
            <a href="{{ route('gross.sales.create') }}" class="btn btn-primary">{{ __('gross.sales.create') }}</a>
        @endcan
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($invoices->isEmpty())
                <x-empty-state :title="__('gross.sales.empty')" icon="bi-receipt" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.customer') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.fields.total') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="fw-semibold"><span class="num-ltr">{{ $invoice->invoice_number }}</span></td>
                                    <td>{{ $invoice->customer_name }}</td>
                                    <td>{{ __('gross.statuses.'.$invoice->status) }}</td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></span>
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

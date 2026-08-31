@extends('layouts.app')

@section('title', __('gross.purchases.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.purchases.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.purchases.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossPurchaseInvoice::class)
            <a href="{{ route('gross.purchases.create') }}" class="btn btn-primary">{{ __('gross.purchases.create') }}</a>
        @endcan
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($invoices->isEmpty())
                <x-empty-state :title="__('gross.purchases.empty')" icon="bi-cart-plus" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.supplier') }}</th>
                                <th>{{ __('gross.fields.status') }}</th>
                                <th class="col-num">{{ __('gross.fields.total') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.purchases.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($invoice->supplier_id)
                                            <a href="{{ route('gross.suppliers.show', $invoice->supplier_id) }}" class="link-body-emphasis text-decoration-none">
                                                {{ $invoice->supplier_name }}
                                            </a>
                                        @else
                                            {{ $invoice->supplier_name }}
                                        @endif
                                    </td>
                                    <td>{{ __('gross.statuses.'.$invoice->status) }}</td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('gross.purchases.show', $invoice) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
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

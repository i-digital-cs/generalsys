@extends('layouts.app')

@section('title', __('gross.sales.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gross.sales.collections') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales.collections_title') }}</a>
            @can('create', App\Models\Gross\GrossSalesInvoice::class)
                <a href="{{ route('gross.sales.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.sales.template') }}
                </a>
                <a href="{{ route('gross.sales.create') }}" class="btn btn-outline-primary">{{ __('gross.sales.create') }}</a>
                <a href="{{ route('gross.sales.cashier') }}" class="btn btn-primary">
                    <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>{{ __('gross.sales.cashier') }}
                </a>
            @endcan
        </div>
    </div>

    @if ($errors->has('import'))
        <div class="alert alert-warning py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ((array) $errors->get('import') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                                    <td class="fw-semibold">
                                        <a href="{{ route('gross.sales.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($invoice->customer_id)
                                            <a href="{{ route('gross.customers.show', $invoice->customer_id) }}" class="link-body-emphasis text-decoration-none">
                                                {{ $invoice->customer_name }}
                                            </a>
                                        @else
                                            {{ $invoice->customer_name }}
                                        @endif
                                    </td>
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

    @can('create', App\Models\Gross\GrossSalesInvoice::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.sales.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.sales.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.sales.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.sales.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.sales.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="sales_file">{{ __('gross.sales.import_file') }}</label>
                        <input id="sales_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.sales.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

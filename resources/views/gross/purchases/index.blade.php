@extends('layouts.app')

@section('title', __('gross.purchases.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.purchases.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.purchases.subtitle') }}</p>
        </div>
        @can('create', App\Models\Gross\GrossPurchaseInvoice::class)
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('gross.purchases.template') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gross.purchases.template') }}
                </a>
                <a href="{{ route('gross.purchases.create') }}" class="btn btn-primary">{{ __('gross.purchases.create') }}</a>
            </div>
        @endcan
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
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                            <a href="{{ route('gross.purchases.show', $invoice) }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                            @can('update', $invoice)
                                                <a href="{{ route('gross.purchases.edit', $invoice) }}" class="btn btn-sm btn-outline-secondary">{{ __('general.edit') }}</a>
                                            @endcan
                                            @can('delete', $invoice)
                                                <form method="post" action="{{ route('gross.purchases.destroy', $invoice) }}" class="d-inline"
                                                      onsubmit="return confirm(@js(__('gross.purchases.delete_confirm')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('general.delete') }}</button>
                                                </form>
                                            @endcan
                                        </div>
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

    @can('create', App\Models\Gross\GrossPurchaseInvoice::class)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h6 mb-1">{{ __('gross.purchases.import_title') }}</h2>
                        <p class="small text-body-secondary mb-0">{{ __('gross.purchases.import_help') }}</p>
                    </div>
                    <a href="{{ route('gross.purchases.template') }}" class="btn btn-link btn-sm px-0">
                        {{ __('gross.purchases.template') }}
                    </a>
                </div>
                <form method="post" action="{{ route('gross.purchases.import') }}" enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label mb-1" for="purchase_file">{{ __('gross.purchases.import_file') }}</label>
                        <input id="purchase_file" name="file" type="file" class="form-control form-control-sm"
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-4 col-lg-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('gross.purchases.import') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

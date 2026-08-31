@extends('layouts.app')

@section('title', __('gross.sales.collections_title'))

@section('content')
    @php($hasFilters = ! empty($filters['shipping_company_id']))

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales.collections_title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales.collections_subtitle') }}</p>
        </div>
        <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales.title') }}</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="shipping_company_id">{{ __('gross.fields.shipping_company') }}</label>
                    <select id="shipping_company_id" name="shipping_company_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($shippingCompanies as $sc)
                            <option value="{{ $sc->id }}" @selected((string) ($filters['shipping_company_id'] ?? '') === (string) $sc->id)>
                                {{ $sc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.sales.collections') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>

            @if ($invoices->isEmpty())
                <x-empty-state :title="__('gross.sales.collections_empty')" icon="bi-cash-coin" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.invoice_number') }}</th>
                                <th>{{ __('gross.fields.customer') }}</th>
                                <th>{{ __('gross.fields.shipping_company') }}</th>
                                <th class="col-num">{{ __('gross.fields.shipping_fee') }}</th>
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
                                    <td>
                                        @if ($invoice->shipping_company_id)
                                            <a href="{{ route('gross.shipping.show', $invoice->shipping_company_id) }}" class="link-body-emphasis text-decoration-none">
                                                {{ $invoice->shipping_company_name ?: '—' }}
                                            </a>
                                        @else
                                            {{ $invoice->shipping_company_name ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$invoice->shipping_fee" :currency="$invoice->currency" /></span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @can('collectShippingFee', $invoice)
                                            <form method="post" action="{{ route('gross.sales.collect-fee', $invoice) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-primary">{{ __('gross.sales.collect_fee') }}</button>
                                            </form>
                                        @endcan
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

@extends('layouts.app')

@section('title', $company->name)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $company->name }}</h1>
            <p class="text-body-secondary small mb-0">
                {{ __('gross.statuses.'.$company->status) }}
                @if ($company->phone)
                    · <span class="num-ltr">{{ $company->phone }}</span>
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gross.shipping.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
            @can('update', $company)
                <a href="{{ route('gross.shipping.edit', $company) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
            @endcan
            @can('delete', $company)
                <form method="post" action="{{ route('gross.shipping.destroy', $company) }}"
                      onsubmit="return confirm(@json(__('gross.shipping.confirm_delete')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('general.delete') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3">{{ __('gross.fields.address') }}</dt>
                <dd class="col-sm-9">{{ $company->address ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('gross.fields.notes') }}</dt>
                <dd class="col-sm-9">{{ $company->notes ?: '—' }}</dd>
            </dl>
        </div>
    </div>

    <h2 class="h6 mb-2">{{ __('gross.shipping.stats_invoices') }}</h2>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-body-secondary small">{{ __('gross.shipping.stats_invoice_total') }}</div>
                    <div class="fs-5 fw-semibold num-ltr">{{ $stats['invoice_total'] }}</div>
                </div>
            </div>
        </div>
        @foreach (['DRAFT', 'CONFIRMED', 'DELIVERED', 'REFUSED', 'CANCELLED'] as $st)
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="text-body-secondary small">{{ __('gross.statuses.'.$st) }}</div>
                        <div class="fs-5 fw-semibold num-ltr">{{ $stats['invoice_counts'][$st] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="h6 mb-2">{{ __('gross.shipping.stats_pieces') }}</h2>
    <div class="row g-2 mb-3">
        @foreach ([
            'shipped' => 'stats_shipped',
            'delivered' => 'stats_delivered',
            'returned' => 'stats_returned',
            'with_courier' => 'stats_with_courier',
            'lost' => 'stats_lost',
        ] as $key => $label)
            <div class="col-6 col-md">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="text-body-secondary small">{{ __('gross.shipping.'.$label) }}</div>
                        <div class="fs-5 fw-semibold num-ltr">{{ $stats['pieces'][$key] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="h6 mb-2">{{ __('gross.shipping.stats_fees') }}</h2>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @if ($stats['fees_by_currency'] === [])
                <p class="text-body-secondary small mb-0">{{ __('gross.shipping.stats_fees_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('gross.fields.currency') }}</th>
                                <th>{{ __('gross.fields.shipping_fee') }}</th>
                                <th class="col-num">{{ __('gross.shipping.stats_fee_invoices') }}</th>
                                <th class="col-num">{{ __('gross.shipping.stats_fee_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['fees_by_currency'] as $fee)
                                <tr>
                                    <td>{{ $fee['currency'] }}</td>
                                    <td>{{ __('gross.shipping_fee_status.'.$fee['fee_status']) }}</td>
                                    <td class="col-num"><span class="num-ltr">{{ $fee['count'] }}</span></td>
                                    <td class="col-num">
                                        <span class="num-ltr">
                                            <x-money :amount="$fee['total']" :currency="$fee['currency']" />
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <h2 class="h6 mb-2">{{ __('gross.shipping.stats_recent_invoices') }}</h2>
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
                                <th class="col-num">{{ __('gross.fields.shipping_fee') }}</th>
                                <th class="col-num">{{ __('gross.fields.total') }}</th>
                                <th class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td><span class="num-ltr">{{ $invoice->invoice_number }}</span></td>
                                    <td>
                                        {{ $invoice->customer_name }}
                                        <div class="small text-body-secondary num-ltr">{{ $invoice->customer_phone }}</div>
                                    </td>
                                    <td>{{ __('gross.statuses.'.$invoice->status) }}</td>
                                    <td class="col-num">
                                        <span class="num-ltr">
                                            <x-money :amount="$invoice->shipping_fee" :currency="$invoice->currency" />
                                        </span>
                                        <div class="small text-body-secondary">{{ __('gross.shipping_fee_status.'.$invoice->shipping_fee_status) }}</div>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr">
                                            <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                                        </span>
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

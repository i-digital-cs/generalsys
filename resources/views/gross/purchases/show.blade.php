@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 d-print-none">
        <div>
            <a href="{{ route('gross.purchases.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($invoice->isDraft())
                @can('update', $invoice)
                    <a href="{{ route('gross.purchases.edit', $invoice) }}" class="btn btn-outline-primary btn-sm">{{ __('general.edit') }}</a>
                @endcan
                @can('confirm', $invoice)
                    <form method="post" action="{{ route('gross.purchases.confirm', $invoice) }}">@csrf
                        <button class="btn btn-primary btn-sm">{{ __('gross.purchases.confirm') }}</button>
                    </form>
                @endcan
                @can('cancel', $invoice)
                    <form method="post" action="{{ route('gross.purchases.cancel', $invoice) }}">@csrf
                        <button class="btn btn-outline-danger btn-sm">{{ __('gross.purchases.cancel') }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-print-none">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <div class="text-body-secondary small">{{ config('app.name') }} · {{ __('gross.purchases.title') }}</div>
                    <h1 class="h3 mb-1"><span class="num-ltr">{{ $invoice->invoice_number }}</span></h1>
                    <div class="small text-body-secondary">
                        {{ $invoice->created_at?->translatedFormat('d M Y H:i') }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-secondary fs-6">{{ __('gross.statuses.'.$invoice->status) }}</span>
                    <div class="mt-2 small">{{ __('gross.fields.currency') }}: <strong>{{ $invoice->currency }}</strong></div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.fields.supplier') }}</h2>
                    <div class="fw-semibold">{{ $invoice->supplier_name }}</div>
                    <div class="num-ltr">{{ $invoice->supplier_phone }}</div>
                    @if ($invoice->supplier_address)
                        <div class="small text-body-secondary">{{ $invoice->supplier_address }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.purchases.invoice_meta') }}</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('gross.fields.created_by') }}</dt>
                        <dd class="col-7">{{ $invoice->creator?->localizedName() ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gross.fields.notes') }}</dt>
                        <dd class="col-7">{{ $invoice->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('gross.fields.code') }}</th>
                            <th>{{ __('gross.fields.name') }}</th>
                            <th>{{ __('gross.fields.size') }}</th>
                            <th class="col-num">{{ __('gross.fields.qty') }}</th>
                            <th class="col-num">{{ __('gross.fields.buy_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.sale_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_discount') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td><span class="num-ltr">{{ $line->item_code_snapshot }}</span></td>
                                <td>{{ $line->item_name_snapshot }}</td>
                                <td>{{ $line->size_snapshot ?: '—' }}</td>
                                <td class="col-num"><span class="num-ltr">{{ $line->qty }}</span></td>
                                <td class="col-num"><span class="num-ltr"><x-money :amount="$line->buy_price" :currency="$invoice->currency" /></span></td>
                                <td class="col-num"><span class="num-ltr"><x-money :amount="$line->sale_price" :currency="$invoice->currency" /></span></td>
                                <td class="col-num">
                                    @if ((float) $line->discount_percent > 0)
                                        <span class="num-ltr">{{ $line->discount_percent }}%</span>
                                    @else
                                        <span class="num-ltr"><x-money :amount="$line->discount_amount" :currency="$invoice->currency" /></span>
                                    @endif
                                </td>
                                <td class="col-num fw-semibold"><span class="num-ltr"><x-money :amount="$line->line_total" :currency="$invoice->currency" /></span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5">
                    <dl class="row mb-0 small">
                        <dt class="col-6">{{ __('gross.fields.subtotal') }}</dt>
                        <dd class="col-6 text-end num-ltr"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></dd>
                        <dt class="col-6">{{ __('gross.fields.invoice_discount') }}</dt>
                        <dd class="col-6 text-end num-ltr"><x-money :amount="$invoice->discount_amount" :currency="$invoice->currency" /></dd>
                        <dt class="col-6 fw-semibold">{{ __('gross.fields.grand_total') }}</dt>
                        <dd class="col-6 text-end fw-semibold num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection

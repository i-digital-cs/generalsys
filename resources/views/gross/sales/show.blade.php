@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
        <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.back') }}</a>

        <div class="ms-auto overflow-x-auto">
            <div class="d-flex flex-nowrap align-items-center gap-2 pb-1">
            @if ($invoice->isDraft())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
                @can('update', $invoice)
                    <a href="{{ route('gross.sales.edit', $invoice) }}" class="btn btn-outline-primary btn-sm flex-shrink-0">{{ __('general.edit') }}</a>
                @endcan
                @can('cancel', $invoice)
                    <form method="post" action="{{ route('gross.sales.cancel', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('gross.sales.cancel') }}</button>
                    </form>
                @endcan
                @can('confirm', $invoice)
                    <form method="post" action="{{ route('gross.sales.confirm', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('gross.sales.confirm') }}</button>
                    </form>
                @endcan
            @endif

            @if ($invoice->isConfirmed())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
                @can('markDelivered', $invoice)
                    <form method="post" action="{{ route('gross.sales.deliver', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('gross.sales.mark_delivered') }}</button>
                    </form>
                @endcan
                @can('collectShippingFee', $invoice)
                    <form method="post" action="{{ route('gross.sales.collect-fee', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-success btn-sm">{{ __('gross.sales.collect_fee') }}</button>
                    </form>
                @endcan
                @can('refuse', $invoice)
                    <form method="post" action="{{ route('gross.sales.refuse', $invoice) }}"
                          class="d-inline-flex flex-nowrap align-items-center mb-0 flex-shrink-0">
                        @csrf
                        <input type="text" name="refuse_reason"
                               class="form-control form-control-sm"
                               style="width: 9.5rem"
                               placeholder="{{ __('gross.fields.refuse_reason') }}">
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('gross.sales.refuse') }}</button>
                    </form>
                @endcan
                @can('cancel', $invoice)
                    <form method="post" action="{{ route('gross.sales.cancel', $invoice) }}" class="d-inline-flex mb-0 flex-shrink-0">@csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('gross.sales.cancel') }}</button>
                    </form>
                @endcan
            @endif

            @if (! $invoice->isDraft() && ! $invoice->isConfirmed())
                <a href="{{ route('gross.sales.excel', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_excel') }}">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_excel') }}</span>
                </a>
                <a href="{{ route('gross.sales.pdf', $invoice) }}"
                   class="btn btn-outline-secondary btn-sm flex-shrink-0"
                   title="{{ __('gross.sales.export_pdf') }}">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('gross.sales.export_pdf') }}</span>
                </a>
            @endif
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-print-none">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border shadow-sm mb-3" data-gross-invoice-doc>
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <div class="text-body-secondary small">{{ config('app.name') }} · {{ __('gross.sales.title') }}</div>
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
                    <h2 class="h6 text-body-secondary">{{ __('gross.fields.customer') }}</h2>
                    <div class="fw-semibold">
                        @if ($invoice->customer_id)
                            <a href="{{ route('gross.customers.show', $invoice->customer_id) }}" class="link-body-emphasis text-decoration-none">
                                {{ $invoice->customer_name }}
                            </a>
                        @else
                            {{ $invoice->customer_name }}
                        @endif
                    </div>
                    <div class="num-ltr">{{ $invoice->customer_phone }}</div>
                    @if ($invoice->customer_address)
                        <div class="small text-body-secondary">{{ $invoice->customer_address }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-body-secondary">{{ __('gross.sales.invoice_meta') }}</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">{{ __('gross.fields.sales_person') }}</dt>
                        <dd class="col-7">{{ $invoice->salesUser?->localizedName() ?: '—' }}</dd>
                        <dt class="col-5">{{ __('gross.fields.shipping_company') }}</dt>
                        <dd class="col-7">
                            @if ($invoice->shipping_company_id)
                                <a href="{{ route('gross.shipping.show', $invoice->shipping_company_id) }}" class="link-body-emphasis text-decoration-none">
                                    {{ $invoice->shipping_company_name ?: '—' }}
                                </a>
                            @else
                                {{ $invoice->shipping_company_name ?: '—' }}
                            @endif
                        </dd>
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
                            <th class="col-num">{{ __('gross.fields.unit_price') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_discount') }}</th>
                            <th class="col-num">{{ __('gross.fields.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            @php
                                $gross = round((float) $line->qty * (float) $line->unit_price, 2);
                            @endphp
                            <tr>
                                <td>
                                    @if ($line->item_id)
                                        <a href="{{ route('gross.items.show', $line->item_id) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                            {{ $line->item_code_snapshot }}
                                        </a>
                                    @else
                                        <span class="num-ltr">{{ $line->item_code_snapshot }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($line->item_id)
                                        <a href="{{ route('gross.items.show', $line->item_id) }}" class="link-body-emphasis text-decoration-none">
                                            {{ $line->item_name_snapshot }}
                                        </a>
                                    @else
                                        {{ $line->item_name_snapshot }}
                                    @endif
                                </td>
                                <td>{{ $line->size_snapshot ?: '—' }}</td>
                                <td class="col-num"><span class="num-ltr">{{ $line->qty }}</span></td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$line->unit_price" :currency="$invoice->currency" /></span>
                                </td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$line->discount_amount" :currency="$invoice->currency" /></span>
                                    @if ((float) $line->discount_percent > 0)
                                        <div class="small text-body-secondary num-ltr">{{ number_format((float) $line->discount_percent, 2) }}%</div>
                                    @endif
                                </td>
                                <td class="col-num fw-semibold">
                                    <span class="num-ltr"><x-money :amount="$line->line_total" :currency="$invoice->currency" /></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('gross.fields.subtotal') }}</dt>
                        <dd class="col-6 text-end"><span class="num-ltr"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></span></dd>
                        <dt class="col-6">{{ __('gross.fields.invoice_discount') }}</dt>
                        <dd class="col-6 text-end">
                            <span class="num-ltr"><x-money :amount="$invoice->discount_amount" :currency="$invoice->currency" /></span>
                            @if ((float) $invoice->discount_percent > 0)
                                <span class="small text-body-secondary">({{ number_format((float) $invoice->discount_percent, 2) }}%)</span>
                            @endif
                        </dd>
                        <dt class="col-6">{{ __('gross.fields.shipping_fee') }}</dt>
                        <dd class="col-6 text-end">
                            <span class="num-ltr"><x-money :amount="$invoice->shipping_fee" :currency="$invoice->currency" /></span>
                            <div class="small text-body-secondary">{{ __('gross.shipping_fee_status.'.$invoice->shipping_fee_status) }}</div>
                        </dd>
                        <dt class="col-6 border-top pt-2">{{ __('gross.fields.grand_total') }}</dt>
                        <dd class="col-6 text-end border-top pt-2 fw-bold fs-5">
                            <span class="num-ltr"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @if ($invoice->isConfirmed())
        <div class="card border-0 shadow-sm d-print-none">
            <div class="card-header bg-transparent fw-semibold">{{ __('gross.sales.allocations') }}</div>
            <div class="card-body">
                @foreach ($invoice->lines as $line)
                    @php($alloc = $line->allocation)
                    @if ($alloc)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold mb-2">
                                @if ($line->item_id)
                                    <a href="{{ route('gross.items.show', $line->item_id) }}" class="link-body-emphasis text-decoration-none">
                                        {{ $line->item_code_snapshot }} — {{ $line->item_name_snapshot }}
                                    </a>
                                @else
                                    {{ $line->item_code_snapshot }} — {{ $line->item_name_snapshot }}
                                @endif
                            </div>
                            <div class="small text-body-secondary mb-2">
                                {{ __('gross.fields.delivered') }}:
                                <span class="num-ltr">{{ $alloc->qty_delivered_pieces }}</span>
                                · {{ __('gross.fields.with_courier') }}:
                                <span class="num-ltr">{{ $alloc->qty_with_courier_pieces }}</span>
                                · {{ __('gross.fields.lost') }}:
                                <span class="num-ltr">{{ $alloc->qty_lost_pieces }}</span>
                                · {{ __('gross.fields.returned') }}:
                                <span class="num-ltr">{{ $alloc->qty_returned_pieces }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @can('returnLine', $invoice)
                                    <form method="post" action="{{ route('gross.sales.return', $invoice) }}" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="allocation_id" value="{{ $alloc->id }}">
                                        <input type="number" min="1" name="qty_pieces" class="form-control form-control-sm" style="width:6rem" value="1" required>
                                        <button class="btn btn-sm btn-outline-primary">{{ __('gross.sales.return') }}</button>
                                    </form>
                                @endcan
                                @can('lossLine', $invoice)
                                    <form method="post" action="{{ route('gross.sales.loss', $invoice) }}" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="allocation_id" value="{{ $alloc->id }}">
                                        <input type="number" min="1" name="qty_pieces" class="form-control form-control-sm" style="width:6rem" value="1" required>
                                        <button class="btn btn-sm btn-outline-danger">{{ __('gross.sales.loss') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endsection

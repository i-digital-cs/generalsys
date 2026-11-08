@extends('layouts.app')

@section('title', $invoice->number ?? __('gh.invoices.title'))

@section('content')
@php
    $customerName = app()->getLocale() === 'ar'
        ? ($invoice->customer_name_ar ?: $invoice->customer_name_en)
        : ($invoice->customer_name_en ?: $invoice->customer_name_ar);
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $invoice->number ?? ('#'.$invoice->id) }}</h1>
            <p class="mb-0">
                <span class="badge text-bg-{{ $invoice->isIssued() ? 'success' : 'secondary' }}">{{ __('gh.invoice_status.'.$invoice->status) }}</span>
                <span class="text-body-secondary small">· {{ $invoice->order_reference }}</span>
            </p>
            @if ($customerName)
                <p class="mb-0 mt-1 fw-semibold">{{ $customerName }}</p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($invoice->isDraft())
                <a href="{{ route('gh.invoices.builder', $invoice) }}" class="btn btn-primary btn-sm">{{ __('gh.invoices.builder') }}</a>
            @endif
            <a href="{{ route('gh.invoices.print', $invoice) }}" class="btn btn-outline-secondary btn-sm" target="_blank">{{ __('gh.invoices.print') }}</a>
            <a href="{{ route('gh.orders.show', $invoice->order_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.invoices.view_order') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            @if ($invoice->isIssued())
                @include('gh.invoices.partials.payment-panel', ['invoice' => $invoice, 'balance' => $balance])
            @endif

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.invoices.lines') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('gh.fields.product') }}</th>
                            <th class="text-center">{{ __('gh.orders.quantity') }}</th>
                            <th class="text-end">{{ __('gh.invoices.unit_price') }}</th>
                            <th class="text-end">{{ __('gh.invoices.line_total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $line->localizedProductName() }}</div>
                                    <div class="small text-body-secondary">{{ $line->localizedTreatmentName() }}</div>
                                </td>
                                <td class="text-center num-ltr">{{ $line->quantity }}</td>
                                <td class="text-end num-ltr"><x-money :amount="$line->unit_price" currency="AED" /></td>
                                <td class="text-end num-ltr hp-value"><x-money :amount="$line->line_total" currency="AED" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.invoices.summary') }}</h2></div>
                <div class="card-body">
                    @include('gh.invoices.partials.totals', ['invoice' => $invoice, 'hidePaymentStatus' => true])
                    @if ($invoice->issued_at)
                        <p class="small text-body-secondary mb-0">
                            {{ __('gh.invoices.issued_at') }}:
                            <span class="num-ltr">{{ $invoice->issued_at->timezone(config('general_house.timezone'))->translatedFormat('Y-m-d H:i') }}</span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

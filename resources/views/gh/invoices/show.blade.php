@extends('layouts.app')

@section('title', $invoice->number ?? __('gh.invoices.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 num-ltr">{{ $invoice->number ?? ('#'.$invoice->id) }}</h1>
            <p class="mb-0">
                <span class="badge text-bg-{{ $invoice->isIssued() ? 'success' : 'secondary' }}">{{ __('gh.invoice_status.'.$invoice->status) }}</span>
                <span class="text-body-secondary small">· {{ $invoice->order_reference }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($invoice->isDraft())
                <a href="{{ route('gh.invoices.builder', $invoice) }}" class="btn btn-primary btn-sm">{{ __('gh.invoices.builder') }}</a>
            @endif
            <a href="{{ route('gh.invoices.print', $invoice) }}" class="btn btn-outline-secondary btn-sm" target="_blank">{{ __('gh.invoices.print') }}</a>
            <a href="{{ route('gh.orders.show', $invoice->order_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.show') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.invoices.lines') }}</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>{{ __('gh.fields.product') }}</th>
                            <th>{{ __('gh.orders.quantity') }}</th>
                            <th class="text-end">{{ __('gh.invoices.unit_price') }}</th>
                            <th class="text-end">{{ __('gh.invoices.vat') }}</th>
                            <th class="text-end">{{ __('gh.invoices.line_total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $line->localizedProductName() }}</div>
                                    <div class="small text-body-secondary">{{ $line->localizedTreatmentName() }} · {{ __('gh.tax_class.'.$line->tax_class) }}</div>
                                </td>
                                <td class="num-ltr">{{ $line->quantity }}</td>
                                <td class="text-end num-ltr"><x-money :amount="$line->unit_price" currency="AED" /></td>
                                <td class="text-end num-ltr"><x-money :amount="$line->tax_amount" currency="AED" /></td>
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
                    @include('gh.invoices.partials.totals', ['invoice' => $invoice])
                    @if ($invoice->issued_at)
                        <p class="small text-body-secondary mb-0">
                            {{ __('gh.invoices.issued_at') }}:
                            <span class="num-ltr">{{ $invoice->issued_at->timezone(config('general_house.timezone'))->translatedFormat('Y-m-d H:i') }}</span>
                            @if ($invoice->issuer) · {{ $invoice->issuer->localizedName() }} @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

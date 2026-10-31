<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number ?? __('gh.invoices.title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ app()->getLocale()==='ar' ? '.rtl' : '' }}.min.css" rel="stylesheet">
    <style>@media print { .no-print { display:none !important } }</style>
</head>
<body class="p-4">
<button type="button" class="btn btn-sm btn-outline-secondary no-print mb-3" onclick="window.print()">{{ __('gh.invoices.print') }}</button>
<h1 class="h4 num-ltr">{{ $invoice->number }}</h1>
<p class="small">{{ __('gh.orders.reference') }}: <span class="num-ltr">{{ $invoice->order_reference }}</span></p>
<p>{{ app()->getLocale()==='ar' ? $invoice->customer_name_ar : ($invoice->customer_name_en ?: $invoice->customer_name_ar) }}</p>
@if ($invoice->billing_address)<p class="small">{{ $invoice->billing_address }}</p>@endif
<table class="table table-sm">
    <thead><tr>
        <th>{{ __('gh.fields.product') }}</th><th>{{ __('gh.orders.quantity') }}</th>
        <th class="text-end">{{ __('gh.invoices.unit_price') }}</th><th class="text-end">{{ __('gh.invoices.line_total') }}</th>
    </tr></thead>
    <tbody>
    @foreach ($invoice->lines as $line)
        <tr>
            <td>{{ $line->localizedProductName() }} / {{ $line->localizedTreatmentName() }}</td>
            <td class="num-ltr">{{ $line->quantity }}</td>
            <td class="text-end num-ltr">{{ number_format((float)$line->unit_price, 2) }}</td>
            <td class="text-end num-ltr">{{ number_format((float)$line->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="text-end">{{ __('gh.invoices.vat') }}: {{ number_format((float)$invoice->vat_amount, 2) }} AED</p>
<p class="text-end fw-bold">{{ __('gh.invoices.total') }}: {{ number_format((float)$invoice->total_payable, 2) }} AED</p>
<p class="small text-body-secondary">{{ __('gh.invoices.payment_status') }}: {{ __('gh.payment_status.'.$invoice->payment_status) }}</p>
</body>
</html>

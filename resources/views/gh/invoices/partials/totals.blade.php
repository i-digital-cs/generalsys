<div class="border rounded p-2 mb-3 small">
    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.lines_gross') }}</span><span class="hp-value num-ltr"><x-money :amount="$invoice->lines_gross" currency="AED" /></span></div>
    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.item_discounts') }}</span><span class="hp-value num-ltr">-<x-money :amount="$invoice->item_discount_total" currency="AED" /></span></div>
    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.invoice_discount') }}</span><span class="hp-value num-ltr">-<x-money :amount="$invoice->invoice_discount_amount" currency="AED" /></span></div>
    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.after_discounts') }}</span><span class="hp-value num-ltr"><x-money :amount="$invoice->lines_after_discounts" currency="AED" /></span></div>
    @if ($invoice->min_order_adjustment_amount > 0)
        <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.min_adjustment') }}</span><span class="hp-value num-ltr"><x-money :amount="$invoice->min_order_adjustment_amount" currency="AED" /></span></div>
    @endif
    <div class="d-flex justify-content-between"><span>{{ __('gh.invoices.vat') }} ({{ $invoice->standard_vat_rate }}%)</span><span class="hp-value num-ltr"><x-money :amount="$invoice->vat_amount" currency="AED" /></span></div>
    <hr class="my-2">
    <div class="d-flex justify-content-between fw-bold"><span>{{ __('gh.invoices.total') }}</span><span class="hp-value num-ltr"><x-money :amount="$invoice->total_payable" currency="AED" /></span></div>
    <div class="text-body-secondary mt-1">{{ __('gh.invoices.payment_status') }}: {{ __('gh.payment_status.'.$invoice->payment_status) }}</div>
</div>

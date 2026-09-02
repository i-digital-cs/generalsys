<ul class="list-unstyled small text-body-secondary mb-0 mt-1">
    @foreach ($breakdown as $row)
        <li class="mb-1">
            <div>
                @if ($row['company_id'])
                    <a href="{{ route('gross.shipping.show', $row['company_id']) }}" class="text-decoration-none fw-semibold">
                        {{ $row['company_name'] }}
                    </a>
                @else
                    <span class="fw-semibold">{{ $row['company_name'] }}</span>
                @endif
                · <span class="num-ltr">{{ $row['qty'] }}</span>
                @if (($row['awaiting_delivery'] ?? 0) > 0)
                    <span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1">
                        <span class="num-ltr">{{ $row['awaiting_delivery'] }}</span> {{ __('gross.stock.waiting_client') }}
                    </span>
                @endif
                @if (($row['refused_with_courier'] ?? 0) > 0)
                    <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">
                        <span class="num-ltr">{{ $row['refused_with_courier'] }}</span> {{ __('gross.stock.refused_with_courier') }}
                    </span>
                @endif
            </div>
            @if (! empty($row['invoices']))
                <ul class="list-unstyled ps-2 mb-0 mt-1 border-start border-2 border-light-subtle">
                    @foreach ($row['invoices'] as $invoiceRow)
                        <li class="py-0">
                            <a href="{{ route('gross.sales.show', $invoiceRow['invoice_id']) }}"
                               class="num-ltr link-body-emphasis text-decoration-none">
                                {{ $invoiceRow['invoice_number'] }}
                            </a>
                            · {{ $invoiceRow['customer_name'] }}
                            @if (($invoiceRow['awaiting_delivery'] ?? 0) > 0)
                                · <span class="num-ltr">{{ $invoiceRow['awaiting_delivery'] }}</span> {{ __('gross.stock.waiting_client') }}
                            @endif
                            @if (($invoiceRow['refused_with_courier'] ?? 0) > 0)
                                · <span class="num-ltr">{{ $invoiceRow['refused_with_courier'] }}</span> {{ __('gross.stock.refused_with_courier') }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ul>

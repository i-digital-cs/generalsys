@if (($rows ?? []) === [])
    <p class="text-body-secondary small mb-0">{{ $emptyText ?? __('gross.shipping.with_courier_modal_empty') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-hr table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('gross.fields.item') }}</th>
                    <th>{{ __('gross.fields.invoice_number') }}</th>
                    <th>{{ __('gross.fields.customer') }}</th>
                    <th class="col-num">{{ __('gross.fields.qty_pieces') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            <span>{{ $row['item_name'] }}</span>
                            <div class="small text-body-secondary num-ltr">{{ $row['item_code'] }}</div>
                        </td>
                        <td>
                            @if (($row['invoice_id'] ?? 0) > 0)
                                <a href="{{ route('gross.sales.show', $row['invoice_id']) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                    {{ $row['invoice_number'] }}
                                </a>
                            @else
                                <span class="num-ltr">{{ $row['invoice_number'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td class="col-num"><span class="num-ltr">{{ $row['qty'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

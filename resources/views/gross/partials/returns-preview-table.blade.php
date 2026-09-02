@php
    $reportService = app(\App\Services\Gross\ShippingReturnsReportService::class);
@endphp

@if ($entries->isEmpty())
    <p class="text-body-secondary small mb-0">{{ __('gross.shipping.returns_empty') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-hr table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('gross.fields.date') }}</th>
                    <th>{{ __('gross.fields.type') }}</th>
                    <th>{{ __('gross.fields.invoice_number') }}</th>
                    <th>{{ __('gross.fields.item') }}</th>
                    <th class="col-num">{{ __('gross.fields.qty_pieces') }}</th>
                    <th>{{ __('gross.shipping.returns_col_location') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    @php
                        $invoice = $entry->shipmentAllocation?->line?->invoice;
                        $kind = $reportService->returnKind($entry);
                        $location = $reportService->locationAfterReturn($entry);
                    @endphp
                    <tr>
                        <td class="small"><span class="num-ltr">{{ $entry->created_at->translatedFormat('d M Y H:i') }}</span></td>
                        <td>{{ __('gross.shipping.return_kind.'.$kind) }}</td>
                        <td>
                            @if ($invoice)
                                <a href="{{ route('gross.sales.show', $invoice) }}" class="num-ltr link-body-emphasis text-decoration-none">
                                    {{ $invoice->invoice_number }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $entry->item?->name ?: '—' }}</td>
                        <td class="col-num"><span class="num-ltr">{{ $entry->qty_pieces }}</span></td>
                        <td>
                            <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">
                                {{ __('gross.shipping.return_location.'.$location) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

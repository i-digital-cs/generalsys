{{-- Legacy baseline: git HEAD for resources/views/gh/invoices/index.blade.php --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.invoices.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.invoices.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.orders.title') }}</a>
    </div>

    <div class="mb-3">@include('gh.invoices.partials.list-filters')</div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('gh.invoices.number') }}</th>
                    <th>{{ __('gh.invoices.order_number') }}</th>
                    <th>{{ __('gh.fields.customer') }}</th>
                    <th>{{ __('gh.invoices.invoice_date') }}</th>
                    <th>{{ __('gh.invoices.status') }}</th>
                    <th class="text-end">{{ __('gh.invoices.total') }}</th>
                    <th class="text-end">{{ __('gh.invoices.amount_paid') }}</th>
                    <th>{{ __('gh.invoices.payment_method_label') }}</th>
                    <th>{{ __('gh.invoices.payment_status') }}</th>
                    <th class="text-end">{{ __('gh.invoices.driver_collected') }}</th>
                    <th class="text-end">{{ __('gh.invoices.driver_settled') }}</th>
                    <th class="text-end">{{ __('gh.invoices.driver_outstanding') }}</th>
                    <th>{{ __('gh.invoices.settlement_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="hp-value num-ltr text-nowrap">{{ $row->number ?? '—' }}</td>
                        <td class="num-ltr text-nowrap">
                            @if ($row->order_id)
                                <a href="{{ route('gh.orders.show', $row->order_id) }}">{{ $row->order_reference }}</a>
                            @else
                                {{ $row->order_reference }}
                            @endif
                        </td>
                        <td>{{ app()->getLocale()==='ar' ? $row->customer_name_ar : ($row->customer_name_en ?: $row->customer_name_ar) }}</td>
                        <td class="num-ltr small text-nowrap">{{ ($row->issued_at ?? $row->created_at)?->timezone($displayTz)->translatedFormat('Y-m-d') }}</td>
                        <td><span class="badge text-bg-{{ $row->status==='ISSUED' ? 'success' : 'secondary' }}">{{ __('gh.invoice_status.'.$row->status) }}</span></td>
                        <td class="text-end"><x-money :amount="$row->total_payable" currency="AED" /></td>
                        <td class="text-end"><x-money :amount="$row->payments_sum_amount ?? 0" currency="AED" /></td>
                        <td class="small">{{ $row->payments->pluck('method')->unique()->map(fn ($m) => __('gh.payment_method.'.$m))->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($row->isIssued() && $row->payment_status)
                                <span class="badge text-bg-{{ ['PAID' => 'success', 'PARTIAL' => 'warning'][$row->payment_status] ?? 'danger' }}">{{ __('gh.payment_status.'.$row->payment_status) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end"><x-money :amount="$row->driver_cash_collected" currency="AED" /></td>
                        <td class="text-end"><x-money :amount="$row->driver_cash_settled" currency="AED" /></td>
                        <td class="text-end fw-semibold"><x-money :amount="$row->driver_cash_outstanding" currency="AED" /></td>
                        <td>@include('gh.driver-cash.partials.status-badge', ['status' => $row->cash_settlement_status])</td>
                        <td class="text-end text-nowrap">
                            @if ($row->isDraft())
                                <a href="{{ route('gh.invoices.builder', $row) }}" class="btn btn-sm btn-primary">{{ __('gh.invoices.builder') }}</a>
                            @else
                                <a href="{{ route('gh.invoices.show', $row) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.invoices.view') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-body-secondary py-4">{{ __('gh.invoices.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-transparent">{{ $rows->links() }}</div>
        @endif
    </div>
</div>

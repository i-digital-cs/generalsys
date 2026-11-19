<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead><tr>
            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
            <th>{{ __('gh.driver_cash.order') }}</th>
            <th>{{ __('gh.driver_cash.invoice') }}</th>
            <th>{{ __('gh.driver_cash.collected_at') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.settled') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.remaining') }}</th>
            <th>{{ __('gh.driver_cash.status') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($data as $c)
            <tr>
                <td class="num-ltr text-nowrap">{{ $c->reference }}</td>
                <td class="num-ltr text-nowrap"><a href="{{ route('gh.orders.show', $c->order_id) }}">{{ $c->order?->reference }}</a></td>
                <td class="num-ltr text-nowrap">
                    @if ($c->order?->invoice)
                        <a href="{{ route('gh.invoices.show', $c->order->invoice) }}">{{ $c->order->invoice->number }}</a>
                    @else
                        <span class="text-body-secondary">{{ __('gh.driver_cash.unlinked_payment') }}</span>
                    @endif
                </td>
                <td class="num-ltr small text-nowrap">{{ $c->collected_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                <td class="text-end"><x-money :amount="$c->amount" currency="AED" /></td>
                <td class="text-end"><x-money :amount="$c->settled_amount" currency="AED" /></td>
                <td class="text-end fw-semibold"><x-money :amount="$c->remaining_amount" currency="AED" /></td>
                <td>@include('gh.driver-cash.partials.status-badge', ['status' => $c->settlement_status])</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-body-secondary">{{ __('gh.driver_cash.no_rows') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($data->hasPages())
    <div class="card-footer bg-transparent">{{ $data->links() }}</div>
@endif

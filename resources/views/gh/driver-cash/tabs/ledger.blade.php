<div class="card-body pb-0">
    <div class="d-flex flex-wrap gap-3 small">
        <span><span class="form-label">{{ __('gh.driver_cash.opening_balance') }}:</span> <x-money :amount="$data['opening']" currency="AED" /></span>
        <span><span class="form-label">{{ __('gh.driver_cash.closing_balance') }}:</span> <x-money :amount="$data['closing']" currency="AED" class="fw-semibold" /></span>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead><tr>
            <th>{{ __('gh.driver_cash.occurred_at') }}</th>
            <th>{{ __('gh.driver_cash.entry') }}</th>
            <th>{{ __('gh.driver_cash.reference') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.delta') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.balance_after') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($data['entries'] as $e)
            <tr>
                <td class="num-ltr small text-nowrap">{{ $e->occurred_at->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                <td>{{ __('gh.driver_cash.ledger_kind.'.$e->kind) }}</td>
                <td class="num-ltr text-nowrap">
                    @if ($e->kind === 'COLLECTION' && $e->order_id)
                        <a href="{{ route('gh.orders.show', $e->order_id) }}">{{ $e->reference }}</a>
                    @else
                        <a href="{{ route('gh.driver-cash.settlements.show', $e->source_id) }}">{{ $e->reference }}</a>
                    @endif
                </td>
                <td class="text-end {{ bccomp($e->delta, '0', 2) < 0 ? 'text-success' : 'text-danger' }}"><x-money :amount="$e->delta" currency="AED" /></td>
                <td class="text-end fw-semibold"><x-money :amount="$e->balance_after" currency="AED" /></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-body-secondary">{{ __('gh.driver_cash.no_rows') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($data['entries']->hasPages())
    <div class="card-footer bg-transparent">{{ $data['entries']->links() }}</div>
@endif

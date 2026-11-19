<div class="card-body pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
    @if ($data->count() >= \App\Services\GeneralHouse\Payments\GhDriverCashActivityService::OUTSTANDING_LIMIT)
        <p class="small text-body-secondary mb-0">{{ __('gh.driver_cash.outstanding_limit_note', ['limit' => \App\Services\GeneralHouse\Payments\GhDriverCashActivityService::OUTSTANDING_LIMIT]) }}</p>
    @else
        <span></span>
    @endif
    @if ($data->isNotEmpty())
        <a href="{{ route('gh.driver-cash.settlements.create', $driver) }}" class="btn btn-primary btn-sm">{{ __('gh.driver_cash.new_settlement') }}</a>
    @endif
</div>
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead><tr>
            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
            <th>{{ __('gh.driver_cash.order') }}</th>
            <th>{{ __('gh.driver_cash.invoice') }}</th>
            <th>{{ __('gh.driver_cash.collected_at') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.remaining') }}</th>
            <th>{{ __('gh.driver_cash.status') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($data as $c)
            <tr>
                <td class="num-ltr text-nowrap">{{ $c->reference }}</td>
                <td class="num-ltr text-nowrap"><a href="{{ route('gh.orders.show', $c->order_id) }}">{{ $c->order?->reference }}</a></td>
                <td class="num-ltr text-nowrap">{{ $c->order?->invoice?->number ?? '—' }}</td>
                <td class="num-ltr small text-nowrap"><x-gh.dubai-time :at="$c->collected_at" :badge="true" /></td>
                <td class="text-end"><x-money :amount="$c->amount" currency="AED" /></td>
                <td class="text-end fw-semibold"><x-money :amount="$c->remaining_amount" currency="AED" /></td>
                <td>@include('gh.driver-cash.partials.status-badge', ['status' => $c->settlement_status])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-body-secondary">{{ __('gh.driver_cash.no_outstanding_collections') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

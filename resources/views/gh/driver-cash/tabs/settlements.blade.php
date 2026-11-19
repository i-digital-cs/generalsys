@php
    $showDriver = $showDriver ?? false;
@endphp
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead><tr>
            <th>{{ __('gh.driver_cash.settlement_ref') }}</th>
            @if ($showDriver)<th>{{ __('gh.driver_cash.driver') }}</th>@endif
            <th>{{ __('gh.driver_cash.settled_at') }}</th>
            <th>{{ __('gh.driver_cash.settled_by') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.allocated') }}</th>
            <th class="text-center">{{ __('gh.driver_cash.invoices_count') }}</th>
            <th>{{ __('gh.driver_cash.state') }}</th>
            <th></th>
        </tr></thead>
        <tbody>
        @forelse ($data as $s)
            <tr>
                <td class="num-ltr text-nowrap">{{ $s->reference }}</td>
                @if ($showDriver)<td>{{ $s->driver?->localizedName() }}</td>@endif
                <td class="num-ltr small text-nowrap"><x-gh.dubai-time :at="$s->settled_at" :badge="true" /></td>
                <td>{{ $s->settler?->localizedName() ?? '—' }}</td>
                <td class="text-end"><x-money :amount="$s->amount" currency="AED" /></td>
                <td class="text-end"><x-money :amount="$s->allocations_sum_amount ?? 0" currency="AED" /></td>
                <td class="text-center num-ltr">{{ $s->allocations_count }}</td>
                <td>
                    @if ($s->reversal)
                        <span class="badge text-bg-secondary">{{ __('gh.driver_cash.reversed') }}</span>
                    @else
                        <span class="badge text-bg-success">{{ __('gh.driver_cash.active') }}</span>
                    @endif
                    @if ((int) $s->allocations_count === 0)
                        <span class="badge text-bg-warning">{{ __('gh.driver_cash.legacy_badge') }}</span>
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('gh.driver-cash.settlements.show', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.driver_cash.view') }}</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $showDriver ? 9 : 8 }}" class="text-body-secondary">{{ __('gh.driver_cash.no_rows') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($data->hasPages())
    <div class="card-footer bg-transparent">{{ $data->links() }}</div>
@endif

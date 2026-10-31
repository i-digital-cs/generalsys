@php
    /** @var \App\Models\GeneralHouse\GhOrder $order */
    $taskType = $taskType ?? 'pickup';
    $href = $taskType === 'delivery'
        ? route('gh.driver.delivery', $order)
        : route('gh.driver.pickup', $order);
    $addr = $order->address;
    $addrLine = collect([$addr?->building, $addr?->street, $addr?->area])->filter()->implode(', ');
    $name = $order->customer?->localizedName();
    $window = $order->pickup_starts_at && $order->pickup_ends_at
        ? $order->pickup_starts_at->timezone($displayTz)->translatedFormat('Y-m-d H:i')
            .' – '.$order->pickup_ends_at->timezone($displayTz)->translatedFormat('H:i')
        : '—';
@endphp
<a href="{{ $href }}" class="card border-0 shadow-sm text-decoration-none text-body mb-2 hp-driver-task">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <span class="fw-bold num-ltr">{{ $order->reference }}</span>
            <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
        </div>
        <div class="small text-body-secondary mb-1">{{ __('gh.driver.task_'.$taskType) }}</div>
        <div class="hp-value mb-1">{{ $name }}</div>
        <div class="small mb-1">{{ $addrLine !== '' ? $addrLine : ($addr?->label ?? '—') }}</div>
        <div class="small text-body-secondary num-ltr">{{ $window }}</div>
    </div>
</a>

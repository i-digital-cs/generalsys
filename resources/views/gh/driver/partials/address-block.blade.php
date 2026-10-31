@php
    $addr = $order->address;
    $mapsUrl = null;
    if ($addr && $addr->latitude !== null && $addr->longitude !== null) {
        $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($addr->latitude.','.$addr->longitude);
    }
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver.location') }}</h2></div>
    <div class="card-body">
        <div class="mb-2">
            <span class="form-label d-block mb-0">{{ __('gh.fields.customer') }}</span>
            <span class="hp-value">{{ $order->customer?->localizedName() }}</span>
            @if ($order->customer?->phone)
                <div class="mt-1">
                    <a class="btn btn-outline-primary btn-sm" href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a>
                </div>
            @endif
        </div>
        <div class="mb-2">
            <span class="form-label d-block mb-0">{{ __('gh.orders.select_address') }}</span>
            <span class="hp-value">{{ $addr?->label }}</span>
            <div class="small">{{ collect([$addr?->building, $addr?->street, $addr?->area])->filter()->implode(', ') }}</div>
            @if ($addr?->directions)
                <div class="small text-body-secondary mt-1">{{ $addr->directions }}</div>
            @endif
            @if ($addr?->zone)
                <div class="small mt-1">{{ $addr->zone->localizedName() }}</div>
            @endif
        </div>
        @if ($mapsUrl)
            <a class="btn btn-primary w-100" href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">{{ __('gh.driver.open_maps') }}</a>
        @endif
    </div>
</div>

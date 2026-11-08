@php
    /** @var \App\Models\GeneralHouse\GhOrder $order */
    $taskType = $taskType ?? 'pickup';
    $sortable = $sortable ?? false;
    $position = $position ?? null;
    $informational = $informational ?? false;
    $href = $taskType === 'delivery'
        ? route('gh.driver.delivery', $order)
        : route('gh.driver.pickup', $order);
    $addr = $order->address;
    $addrLine = collect([$addr?->building, $addr?->street, $addr?->area])->filter()->implode(', ');
    $name = $order->customer?->localizedName();
    $customerType = $order->customer?->type;
    $window = $order->pickup_starts_at
        ? $order->pickup_starts_at->timezone($displayTz)->translatedFormat('Y-m-d H:i')
        : '—';
    $overdue = $taskType === 'pickup'
        && $order->status === \App\Support\GeneralHouse\GhOrderStatus::PICKUP
        && ($order->pickup_ends_at?->isPast() || $order->pickup_starts_at?->isPast());
@endphp
<div class="d-flex align-items-stretch gap-2 mb-2"
     @if ($sortable) data-sort-item data-order-id="{{ $order->id }}" @endif>
    @if ($sortable)
        <div class="d-flex flex-column align-items-center justify-content-center gap-1 flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-sort-up
                    aria-label="{{ __('gh.driver.move_up') }}" title="{{ __('gh.driver.move_up') }}">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <span class="hp-driver-sort-handle fs-4" data-sort-handle role="button" tabindex="0"
                  title="{{ __('gh.driver.sort_handle') }}"
                  aria-label="{{ __('gh.driver.sort_handle') }}">
                <i class="bi bi-grip-vertical" aria-hidden="true"></i>
            </span>
            <span class="badge text-bg-secondary num-ltr" data-sort-position title="{{ __('gh.driver.position') }}">{{ $position }}</span>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-sort-down
                    aria-label="{{ __('gh.driver.move_down') }}" title="{{ __('gh.driver.move_down') }}">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
        </div>
    @endif
    <a href="{{ $href }}" class="card border-0 shadow-sm text-decoration-none text-body flex-grow-1 hp-driver-task {{ $informational ? 'opacity-75' : '' }}">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <span class="fw-bold num-ltr">{{ $order->reference }}</span>
                <span class="d-flex flex-wrap justify-content-end gap-1">
                    @if ($customerType)
                        <span class="badge {{ $customerType === \App\Support\GeneralHouse\GhCustomerType::B2B ? 'text-bg-info' : 'text-bg-success' }}">
                            {{ __('gh.customer_type.'.$customerType) }}
                        </span>
                    @endif
                    @if ($overdue)
                        <span class="badge text-bg-danger">{{ __('gh.driver.overdue') }}</span>
                    @endif
                    @if ($informational)
                        <span class="badge text-bg-secondary">{{ __('gh.driver.not_yet_visible') }}</span>
                    @endif
                    <span class="badge text-bg-primary">{{ __('gh.order_status.'.$order->status) }}</span>
                </span>
            </div>
            <div class="small text-body-secondary mb-1">{{ __('gh.driver.task_'.$taskType) }}</div>
            <div class="hp-value mb-1">{{ $name }}</div>
            <div class="small mb-1">{{ $addrLine !== '' ? $addrLine : ($addr?->label ?? '—') }}</div>
            <div class="small text-body-secondary">
                {{ $taskType === 'delivery' ? __('gh.driver.delivery_time') : __('gh.driver.pickup_time') }}:
                <span class="num-ltr">{{ $window }}</span>
            </div>
        </div>
    </a>
</div>

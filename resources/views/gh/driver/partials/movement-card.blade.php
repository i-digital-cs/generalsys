@php
    /** @var \App\Services\GeneralHouse\Orders\GhDriverMovement $movement */
    $order = $movement->order;
    $taskType = $movement->taskType();
    $href = $taskType === 'delivery'
        ? route('gh.driver.delivery', $order)
        : route('gh.driver.pickup', $order);
    $addr = $order->address;
    $addrLine = collect([$addr?->building, $addr?->street, $addr?->area])->filter()->implode(', ');
    $name = $order->customer?->localizedName();
    $scheduled = $movement->scheduledAt
        ? $movement->scheduledAt->timezone($displayTz)->translatedFormat('Y-m-d H:i')
        : '—';
    $completed = $movement->completedAt
        ? $movement->completedAt->timezone($displayTz)->translatedFormat('Y-m-d H:i')
        : null;
    $statusBadge = match ($movement->status) {
        'COMPLETED' => 'success',
        'FAILED' => 'danger',
        'CANCELLED' => 'secondary',
        'READY' => 'primary',
        default => 'warning',
    };
@endphp
<div class="card border-0 shadow-sm mb-2 hp-driver-movement" data-movement-key="{{ $movement->key() }}" data-movement-type="{{ $movement->type }}">
    <a href="{{ $href }}" class="text-decoration-none text-body">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <span class="fw-bold num-ltr">{{ $order->reference }}</span>
                <span class="d-flex flex-wrap justify-content-end gap-1">
                    <span class="badge text-bg-{{ $taskType === 'delivery' ? 'info' : 'primary' }}">
                        {{ __('gh.driver.movement_type.'.$movement->type) }}
                    </span>
                    <span class="badge text-bg-{{ $statusBadge }}">
                        {{ __('gh.driver.movement_status.'.$movement->status) }}
                    </span>
                </span>
            </div>
            <div class="hp-value mb-1">{{ $name }}</div>
            <div class="small mb-1">{{ $addrLine !== '' ? $addrLine : ($addr?->label ?? '—') }}</div>
            <div class="small text-body-secondary">
                {{ $taskType === 'delivery' ? __('gh.driver.delivery_time') : __('gh.driver.pickup_time') }}:
                <span class="num-ltr">{{ $scheduled }}</span>
            </div>
            @if ($completed)
                <div class="small text-body-secondary">
                    {{ __('gh.driver.completed_at') }}:
                    <span class="num-ltr">{{ $completed }}</span>
                </div>
            @endif
            @if ($movement->attempts->isNotEmpty())
                <div class="small text-danger mt-1">
                    {{ __('gh.driver.failed_attempts_count', ['count' => $movement->attempts->count()]) }}
                </div>
            @endif
            @if (count($movement->assignmentHistory) > 1)
                <div class="small text-body-secondary mt-1">
                    {{ __('gh.driver.reassignment_count', ['count' => count($movement->assignmentHistory) - 1]) }}
                </div>
            @endif
        </div>
    </a>
</div>

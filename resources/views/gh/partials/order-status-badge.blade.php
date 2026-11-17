@php
    /** @var string $status */
    $status = strtoupper((string) ($status ?? ''));
    $label = $label ?? __('gh.order_status.'.$status);
    $modifier = match ($status) {
        'LATER' => 'later',
        'PICKUP' => 'pickup',
        'WORKING' => 'working',
        'DELIVERY' => 'delivery',
        'COMPLETED' => 'completed',
        'CANCELLED' => 'cancelled',
        default => 'unknown',
    };
    $extraClass = trim((string) ($class ?? ''));
@endphp
<span class="gh-status-badge gh-status-badge--{{ $modifier }}{{ $extraClass !== '' ? ' '.$extraClass : '' }}"
      title="{{ $label }}">{{ $label }}</span>

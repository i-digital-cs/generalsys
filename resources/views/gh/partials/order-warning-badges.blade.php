@php
    /** @var list<array{code?: string, tone?: string, label?: string}> $warnings */
    $warnings = is_array($warnings ?? null) ? $warnings : [];
@endphp

@foreach ($warnings as $warning)
    @php
        $tone = in_array(($warning['tone'] ?? ''), ['orange', 'red'], true)
            ? $warning['tone']
            : 'orange';

        $label = (string) ($warning['label'] ?? '');
        $code = (string) ($warning['code'] ?? '');

        $isDeliveryWarning = $code === 'needs_delivery_driver';
    @endphp

    @if ($label !== '')
        <span
            class="{{ $isDeliveryWarning
                ? 'gh-order-card__badge text-bg-warning'
                : 'gh-warning-badge gh-warning-badge--'.$tone.(! empty($compact) ? ' gh-warning-badge--compact' : '') }}"

            @if ($code !== '')
                data-warning-code="{{ $code }}"
            @endif

            title="{{ $isDeliveryWarning
                ? __('gh.orders.delivery_unassigned_hint')
                : $label }}"
        >{{ $label }}</span>
    @endif
@endforeach

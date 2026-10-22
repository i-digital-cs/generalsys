@props([
    'label',
    'value' => null,
    'tooltipKey' => null,
    'tooltip' => null,
    'badgeClass' => 'text-bg-light border text-dark',
])

@php
    $tip = $tooltip;
    if ($tip === null && $tooltipKey !== null) {
        $tip = __('attendance.tooltips.'.$tooltipKey);
    }
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$badgeClass.' d-inline-flex align-items-center gap-1']) }}>
    <span>{{ $label }}@if ($value !== null):@endif</span>
    @if ($value !== null)
        <span class="num-ltr">{{ $value }}</span>
    @endif
    @if ($tip)
        <x-hp-tooltip :text="$tip" :label="$label" />
    @endif
</span>

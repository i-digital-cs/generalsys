@props([
    'label',
    'value' => 0,
    'hint' => null,
])

@php
    $percent = max(0, min(100, (float) $value));
@endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
        <span class="fw-semibold">{{ $label }}</span>
        <span class="text-body-secondary" dir="ltr">{{ number_format($percent, 2) }}%</span>
    </div>
    <div class="progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="{{ $label }}">
        <div class="progress-bar" style="width: {{ $percent }}%"></div>
    </div>
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>

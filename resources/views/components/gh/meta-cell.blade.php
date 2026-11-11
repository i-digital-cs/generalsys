@props([
    'icon' => 'bi-dot',
    'value' => '',
    'title' => null,
    'ltr' => false,
])
@php
    $titleAttr = $title ?? (is_scalar($value) ? (string) $value : '');
@endphp
<div {{ $attributes->class('gh-record-card__cell') }}>
    <span class="gh-record-card__icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <span
        @class(['gh-record-card__value', 'num-ltr' => $ltr])
        @if ($titleAttr !== '') title="{{ $titleAttr }}" @endif
    >{{ $value }}</span>
</div>

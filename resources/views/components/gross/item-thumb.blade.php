@props([
    'item' => null,
    'url' => null,
    'size' => '2.5rem',
    'alt' => '',
])

@php
    $resolvedUrl = $url;
    $label = $alt;
    if ($resolvedUrl === null && $item instanceof \App\Models\Gross\GrossItem) {
        $resolvedUrl = $item->thumbnailUrl();
        if ($label === '') {
            $label = (string) $item->name;
        }
    }
@endphp

@if ($resolvedUrl)
    <img src="{{ $resolvedUrl }}"
         alt="{{ $label }}"
         {{ $attributes->class(['rounded', 'border', 'flex-shrink-0']) }}
         style="width: {{ $size }}; height: {{ $size }}; object-fit: cover">
@else
    <span {{ $attributes->class(['d-inline-flex', 'align-items-center', 'justify-content-center', 'rounded', 'border', 'text-body-secondary', 'flex-shrink-0']) }}
          style="width: {{ $size }}; height: {{ $size }}"
          aria-hidden="true">
        <i class="bi bi-image"></i>
    </span>
@endif

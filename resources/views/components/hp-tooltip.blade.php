@props([
    'text',
    'label' => null,
])

@php
    $text = trim((string) $text);
    $aria = $label ?? __('attendance.tooltips.more_info');
@endphp

@if ($text !== '')
    <button
        type="button"
        class="btn btn-link p-0 border-0 align-baseline hp-tooltip-trigger text-body-secondary"
        data-bs-toggle="tooltip"
        data-bs-custom-class="hp-tooltip"
        data-bs-placement="auto"
        data-bs-html="false"
        title="{{ $text }}"
        aria-label="{{ $aria }}"
        {{ $attributes }}
    >
        <i class="bi bi-info-circle" aria-hidden="true"></i>
    </button>
@endif

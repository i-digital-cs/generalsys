@props([
    'title',
    'text' => null,
    'icon' => 'bi-inbox',
])

<div class="text-center py-5 text-body-secondary">
    <i class="bi {{ $icon }} fs-1 d-block mb-3" aria-hidden="true"></i>
    <p class="fw-semibold mb-1">{{ $title }}</p>
    @if ($text)
        <p class="small mb-0">{{ $text }}</p>
    @endif
</div>

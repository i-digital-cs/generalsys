@props([
    'title',
    'value' => null,
    'icon' => 'bi-bar-chart',
    'color' => 'primary',
    'subtitle' => null,
])

<div class="card stat-card border-0 h-100">
    <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon d-inline-flex align-items-center justify-content-center bg-{{ $color }} bg-opacity-10 text-{{ $color }} fs-4">
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
        </span>
        <div>
            <div class="text-body-secondary small">{{ $title }}</div>
            <div class="stat-value fw-bold">
                @if ($value !== null)
                    {{ $value }}
                @else
                    {{ $slot }}
                @endif
            </div>
            @if ($subtitle)
                <div class="text-body-secondary small">{{ $subtitle }}</div>
            @endif
        </div>
    </div>
</div>

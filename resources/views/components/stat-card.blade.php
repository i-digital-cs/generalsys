@props([
    'title',
    'value' => null,
    'icon' => 'bi-bar-chart',
    'color' => 'primary',
    'subtitle' => null,
    'sparkline' => null,
    'showSparkline' => true,
])

@php
    /** @var list<float|int>|null $sparkline */
    $points = is_array($sparkline) ? array_values($sparkline) : null;

    if ($showSparkline && ($points === null || count($points) < 2)) {
        $seed = crc32((string) $title);
        $points = [];
        for ($i = 0; $i < 8; $i++) {
            $points[] = 18 + (($seed >> ($i * 3)) & 63);
        }
    }

    $sparkPath = null;
    $sparkArea = null;

    if ($showSparkline && is_array($points) && count($points) >= 2) {
        $w = 64;
        $h = 36;
        $min = min($points);
        $max = max($points);
        $range = max(1, $max - $min);
        $n = count($points);
        $coords = [];

        foreach ($points as $i => $raw) {
            $x = $n === 1 ? 0 : ($i / ($n - 1)) * $w;
            $y = $h - (((float) $raw - $min) / $range) * ($h - 4) - 2;
            $coords[] = [round($x, 2), round($y, 2)];
        }

        $sparkPath = 'M '.implode(' L ', array_map(fn ($c) => $c[0].' '.$c[1], $coords));
        $first = $coords[0];
        $last = $coords[array_key_last($coords)];
        $sparkArea = $sparkPath.' L '.$last[0].' '.$h.' L '.$first[0].' '.$h.' Z';
    }
@endphp

<div {{ $attributes->class(['card', 'stat-card', 'border-0', 'h-100']) }}>
    <div class="card-body py-3">
        <div class="stat-card-inner">
            <span class="stat-icon d-inline-flex align-items-center justify-content-center bg-{{ $color }} bg-opacity-10 text-{{ $color }}">
                <i class="bi {{ $icon }}" aria-hidden="true"></i>
            </span>

            <div class="stat-body">
                <div class="stat-label text-truncate" title="{{ $title }}">{{ $title }}</div>
                <div class="stat-value">
                    @if ($value !== null)
                        {{ $value }}
                    @else
                        {{ $slot }}
                    @endif
                </div>
                @if ($subtitle)
                    <div class="stat-subtitle text-truncate" title="{{ $subtitle }}">{{ $subtitle }}</div>
                @endif
            </div>

            @if ($sparkPath)
                <div class="stat-sparkline" aria-hidden="true">
                    <svg viewBox="0 0 64 36" preserveAspectRatio="none">
                        <path d="{{ $sparkArea }}" fill="var(--hp-spark-fill)" stroke="none"></path>
                        <path d="{{ $sparkPath }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            @endif
        </div>
    </div>
</div>

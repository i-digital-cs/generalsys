@props([
    'attendance' => null,
    'kind' => null, // CLOCK_IN | CLOCK_OUT | null (both)
    'compact' => false,
])

@php
    use App\Services\Attendance\GeofenceService;

    $row = $attendance;
    $showIn = $kind === null || $kind === 'CLOCK_IN';
    $showOut = $kind === null || $kind === 'CLOCK_OUT';

    $items = [];
    if ($row !== null && $showIn && $row->clock_in_lat !== null && $row->clock_in_lng !== null) {
        $items[] = [
            'label' => __('clock.location.in_label'),
            'lat' => (float) $row->clock_in_lat,
            'lng' => (float) $row->clock_in_lng,
            'accuracy' => $row->clock_in_accuracy_m,
            'distance' => $row->clock_in_distance_m,
            'outside' => (bool) $row->clock_in_outside_geofence,
        ];
    }
    if ($row !== null && $showOut && $row->clock_out_lat !== null && $row->clock_out_lng !== null) {
        $items[] = [
            'label' => __('clock.location.out_label'),
            'lat' => (float) $row->clock_out_lat,
            'lng' => (float) $row->clock_out_lng,
            'accuracy' => $row->clock_out_accuracy_m,
            'distance' => $row->clock_out_distance_m,
            'outside' => (bool) $row->clock_out_outside_geofence,
        ];
    }
@endphp

@if ($items !== [])
    <div {{ $attributes->class(['attendance-location', $compact ? 'attendance-location--compact' : '']) }}>
        @unless ($compact)
            <div class="small fw-semibold mb-1">{{ __('clock.location.title') }}</div>
        @endunless
        <ul class="list-unstyled mb-0 small">
            @foreach ($items as $item)
                <li class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    @unless ($compact && count($items) === 1)
                        <span class="text-body-secondary">{{ $item['label'] }}</span>
                    @endunless
                    <a href="{{ GeofenceService::googleMapsUrl($item['lat'], $item['lng']) }}"
                       target="_blank"
                       rel="noopener"
                       class="link-primary"
                       dir="ltr">
                        <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                        {{ number_format($item['lat'], 5) }}, {{ number_format($item['lng'], 5) }}
                    </a>
                    @if ($item['outside'])
                        <span class="badge text-bg-warning">{{ __('clock.location.outside') }}</span>
                    @elseif ($item['distance'] !== null)
                        <span class="badge text-bg-success">{{ __('clock.location.inside') }}</span>
                    @endif
                    @if ($item['distance'] !== null)
                        <span class="text-body-secondary" dir="ltr">
                            {{ __('clock.location.distance', ['m' => $item['distance']]) }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

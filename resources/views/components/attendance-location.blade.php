@props([
    'attendance' => null,
    'user' => null,
    'kind' => null, // CLOCK_IN | CLOCK_OUT | null (both)
    'compact' => false,
])

@php
    use App\Http\Requests\Employee\ClockLocationRules;
    use App\Services\Attendance\GeofenceService;

    $row = $attendance;
    $employee = $user ?? $row?->user;
    $officeExpected = $row !== null
        ? ClockLocationRules::expectsOfficeProximity($row->shift_type, $employee)
        : false;
    $showIn = $kind === null || $kind === 'CLOCK_IN';
    $showOut = $kind === null || $kind === 'CLOCK_OUT';

    $items = [];
    if ($row !== null && $showIn && $row->clock_in_lat !== null && $row->clock_in_lng !== null) {
        $items[] = [
            'label' => __('clock.location.in_label'),
            'punch' => __('clock.location.punch_in'),
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
            'punch' => __('clock.location.punch_out'),
            'lat' => (float) $row->clock_out_lat,
            'lng' => (float) $row->clock_out_lng,
            'accuracy' => $row->clock_out_accuracy_m,
            'distance' => $row->clock_out_distance_m,
            'outside' => (bool) $row->clock_out_outside_geofence,
        ];
    }

    $statusLabel = static function (bool $outside) use ($officeExpected): string {
        if ($outside) {
            return $officeExpected
                ? __('clock.location.outside')
                : __('clock.location.outside_ok');
        }

        return __('clock.location.inside');
    };

    $badgeClass = static function (bool $outside) use ($officeExpected): string {
        if ($outside && $officeExpected) {
            return 'text-bg-warning text-dark';
        }

        if ($outside && ! $officeExpected) {
            return 'text-bg-secondary';
        }

        return 'text-bg-success';
    };
@endphp

@if ($items !== [])
    <div {{ $attributes->class(['attendance-location', $compact ? 'attendance-location--compact' : '']) }}>
        @unless ($compact)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="small fw-semibold mb-0">{{ __('clock.location.title') }}</div>
                @if ($row !== null)
                    <span class="badge text-bg-light border text-dark small">
                        {{ __('clock.shifts.'.$row->shift_type) }}
                    </span>
                    <span @class([
                        'badge small',
                        'text-bg-primary' => $officeExpected,
                        'text-bg-secondary' => ! $officeExpected,
                    ])>
                        {{ $officeExpected ? __('clock.location.office_expected') : __('clock.location.office_optional') }}
                    </span>
                @endif
            </div>
        @endunless
        <ul class="list-unstyled mb-0 small">
            @foreach ($items as $item)
                <li class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="text-body-secondary">{{ $compact ? $item['punch'] : $item['label'] }}</span>
                    <a href="{{ GeofenceService::googleMapsUrl($item['lat'], $item['lng']) }}"
                       target="_blank"
                       rel="noopener"
                       class="link-primary"
                       dir="ltr">
                        <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                        {{ number_format($item['lat'], 5) }}, {{ number_format($item['lng'], 5) }}
                    </a>
                    <span @class(['badge', $badgeClass($item['outside'])])>
                        {{ $statusLabel($item['outside']) }}
                    </span>
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

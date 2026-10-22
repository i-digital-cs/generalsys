@props([
    'attendance',
    'user' => null,
])

@php
    use App\Http\Requests\Employee\ClockLocationRules;

    $employee = $user ?? $attendance->user;
    $officeExpected = ClockLocationRules::expectsOfficeProximity($attendance->shift_type, $employee);
    $shiftLabel = __('clock.shifts.'.$attendance->shift_type);

    $punches = [];
    if ($attendance->clock_in_lat !== null) {
        $punches[] = [
            'punch' => __('clock.location.punch_in'),
            'outside' => (bool) $attendance->clock_in_outside_geofence,
        ];
    }
    if ($attendance->clock_out_lat !== null) {
        $punches[] = [
            'punch' => __('clock.location.punch_out'),
            'outside' => (bool) $attendance->clock_out_outside_geofence,
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

<div {{ $attributes->class(['d-flex flex-wrap gap-1 align-items-center']) }}>
    <span
        class="badge text-bg-light border text-dark"
        title="{{ $officeExpected ? __('clock.location.office_expected') : __('clock.location.office_optional') }}"
    >
        {{ $shiftLabel }}
        <span class="visually-hidden">
            — {{ $officeExpected ? __('clock.location.office_expected') : __('clock.location.office_optional') }}
        </span>
        <i @class([
            'ms-1',
            'bi bi-building' => $officeExpected,
            'bi bi-house' => ! $officeExpected,
        ]) aria-hidden="true"></i>
    </span>
    @foreach ($punches as $punch)
        <span @class(['badge', $badgeClass($punch['outside']), 'd-inline-flex', 'align-items-center', 'gap-1'])>
            {{ __('clock.location.badge_status', [
                'punch' => $punch['punch'],
                'status' => $statusLabel($punch['outside']),
            ]) }}
            @if ($punch['outside'])
                <x-hp-tooltip :text="__('attendance.tooltips.outside_office')" />
            @endif
        </span>
    @endforeach
</div>

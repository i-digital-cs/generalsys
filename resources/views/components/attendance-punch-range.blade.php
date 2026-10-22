@props([
    'attendance',
    'showShift' => false,
])

@php
    use Carbon\CarbonInterface;

    $tz = config('app.timezone');
    $in = $attendance->clock_in;
    $out = $attendance->clock_out;
    $open = $in !== null && $out === null;
    $auto = (bool) $attendance->is_auto_clock_out;
    $forced = $attendance->force_closed_by !== null;

    $formatPunch = static function (?CarbonInterface $dt, ?CarbonInterface $other) use ($tz): string {
        if ($dt === null) {
            return '—';
        }
        $local = $dt->timezone($tz);
        if ($other !== null && $local->toDateString() !== $other->timezone($tz)->toDateString()) {
            return $local->format('Y-m-d H:i');
        }

        return $local->format('H:i');
    };

    $inLabel = $formatPunch($in, $out);
    $outLabel = $open
        ? __('attendance.punch.open')
        : $formatPunch($out, $in);
@endphp

<span {{ $attributes->merge(['class' => 'd-inline-flex flex-wrap align-items-center gap-1']) }}>
    <span class="badge text-bg-light border text-dark num-ltr" dir="ltr">
        {{ $inLabel }} → {{ $outLabel }}
    </span>
    @if ($showShift)
        <span class="badge text-bg-light border text-dark">
            {{ __('employees.shifts.'.$attendance->shift_type) }}
        </span>
    @endif
    @if ($open)
        <span class="badge text-bg-warning text-dark">{{ __('attendance.punch.missing_checkout') }}</span>
    @elseif ($auto)
        <span class="badge text-bg-dark">{{ __('clock.flags.auto_clock_out') }}</span>
    @elseif ($forced)
        <span class="badge text-bg-warning text-dark">{{ __('clock.flags.force_clock_out') }}</span>
    @endif
</span>

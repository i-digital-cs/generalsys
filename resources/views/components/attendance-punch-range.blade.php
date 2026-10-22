@props([
    'attendance',
    'showShift' => false,
    'result' => null,
    'showProvenance' => true,
])

@php
    use App\Services\Attendance\AttendanceCalculationEngine;
    use App\Services\Attendance\CheckoutProvenance;
    use Carbon\CarbonInterface;

    $result = $result ?? app(AttendanceCalculationEngine::class)->forAttendance($attendance);
    $tz = config('app.timezone');
    $in = $attendance->clock_in;
    $out = $attendance->clock_out;
    $open = $result->isOpen();
    $provenance = $result->checkoutProvenance;

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
            @if ($result->summary->windowStart && $result->summary->windowEnd)
                <span class="num-ltr">({{ $result->summary->windowStart }}–{{ $result->summary->windowEnd }})</span>
            @endif
        </span>
    @endif
    @if ($showProvenance)
        <span @class([
            'badge',
            'text-bg-success' => $provenance === CheckoutProvenance::EMPLOYEE,
            'text-bg-warning text-dark' => in_array($provenance, [CheckoutProvenance::OPEN, CheckoutProvenance::SUPERVISOR], true),
            'text-bg-dark' => $provenance === CheckoutProvenance::AUTO_STALE,
            'd-inline-flex',
            'align-items-center',
            'gap-1',
        ])>
            {{ __(CheckoutProvenance::labelKey($provenance)) }}
            @if ($provenance === CheckoutProvenance::AUTO_STALE)
                <x-hp-tooltip :text="__('attendance.tooltips.auto_checkout')" :label="__(CheckoutProvenance::labelKey($provenance))" class="text-white" />
            @endif
        </span>
    @endif
</span>

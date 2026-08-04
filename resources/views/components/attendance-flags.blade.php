@props([
    'attendance' => null,
    'friday' => null,
    'restDay' => null,
    'holiday' => null,
])

@php
    $friday = $friday ?? (bool) ($attendance?->is_friday_work);
    $restDay = $restDay ?? (bool) ($attendance?->is_day_off_work);
    $holiday = $holiday ?? (bool) ($attendance?->is_official_holiday_work);
@endphp

@if ($friday || $restDay || $holiday)
    <div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1']) }}>
        @if ($friday)
            <span class="badge text-bg-primary">{{ __('clock.flags.friday') }}</span>
        @endif
        @if ($restDay)
            <span class="badge text-bg-warning text-dark">{{ __('clock.flags.rest_day') }}</span>
        @endif
        @if ($holiday)
            <span class="badge text-bg-info">{{ __('clock.flags.holiday') }}</span>
        @endif
    </div>
@endif

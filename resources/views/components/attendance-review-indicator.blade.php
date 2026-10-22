@props([
    'reasonCodes' => [],
    'tooltip' => '',
])

@php
    use App\Services\Attendance\AttendanceReviewPresentation;

    $codes = array_values(array_unique(array_filter((array) $reasonCodes)));
    $tooltip = trim((string) $tooltip);
    if ($tooltip === '' && $codes !== []) {
        $tooltip = implode("\n", app(AttendanceReviewPresentation::class)->reasonLabels($codes));
    }
@endphp

@if ($codes !== [])
    <span {{ $attributes->merge(['class' => 'badge text-bg-danger d-inline-flex align-items-center gap-1']) }}>
        <span>{{ __('attendance.review.indicator') }}</span>
        <x-hp-tooltip :text="$tooltip" :label="__('attendance.review.indicator_aria')" class="text-white" />
    </span>
@endif

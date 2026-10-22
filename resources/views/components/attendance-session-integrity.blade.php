@props([
    'result',
])

@php
    /** @var \App\Services\Attendance\AttendanceCalculationResult $result */
    use App\Services\Attendance\CheckoutProvenance;
@endphp

<span {{ $attributes->merge(['class' => 'd-inline-flex flex-wrap align-items-center gap-1']) }}>
    <span @class([
        'badge',
        'text-bg-success' => $result->checkoutProvenance === CheckoutProvenance::EMPLOYEE,
        'text-bg-warning text-dark' => $result->checkoutProvenance === CheckoutProvenance::SUPERVISOR
            || $result->checkoutProvenance === CheckoutProvenance::OPEN,
        'text-bg-dark' => $result->checkoutProvenance === CheckoutProvenance::AUTO_STALE,
    ])>
        {{ __(CheckoutProvenance::labelKey($result->checkoutProvenance)) }}
    </span>

    @if ($result->shiftReviewRequired())
        <span class="badge text-bg-danger">
            {{ __('attendance.integrity.shift_review_required') }}
        </span>
    @endif
</span>

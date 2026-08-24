@props(['gap'])

@php
    $tl = $gap->tl_recommendation;
    $admin = $gap->admin_decision;
@endphp

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-1 align-items-center']) }}>
    @if ($admin !== \App\Models\AttendanceGap::ADMIN_PENDING)
        <span @class([
            'badge',
            'text-bg-success' => $admin === \App\Models\AttendanceGap::ADMIN_APPROVED,
            'text-bg-danger' => $admin === \App\Models\AttendanceGap::ADMIN_REJECTED,
        ])>
            {{ __('attendance_gaps.status.'.$admin) }}
        </span>
    @elseif ($tl !== \App\Models\AttendanceGap::TL_PENDING)
        <span @class([
            'badge',
            'text-bg-info text-dark' => $tl === \App\Models\AttendanceGap::TL_APPROVE,
            'text-bg-warning text-dark' => $tl === \App\Models\AttendanceGap::TL_REJECT,
        ])>
            {{ __('attendance_gaps.status.'.$tl) }}
        </span>
        <span class="badge text-bg-secondary">{{ __('attendance_gaps.pending_admin') }}</span>
    @else
        <span class="badge text-bg-warning text-dark">{{ __('attendance_gaps.pending_tl') }}</span>
    @endif
</div>

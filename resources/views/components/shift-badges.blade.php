@props([
    'user',
    'stacked' => true,
])

@php
    /** @var \App\Models\User $user */
    $profiles = $user->defaultShiftProfiles();
@endphp

<div {{ $attributes->class([
    'd-flex gap-1',
    'flex-column align-items-start' => $stacked,
    'flex-wrap' => ! $stacked,
]) }}>
    @forelse ($profiles as $profile)
        <span class="badge text-bg-light border text-start">
            <span class="d-block">{{ __('employees.shifts.'.$profile['type']) }}</span>
            <span class="num-ltr fw-normal">{{ $profile['start'] }}–{{ $profile['end'] }}</span>
        </span>
    @empty
        <span>{{ __('general.not_available') }}</span>
    @endforelse
</div>

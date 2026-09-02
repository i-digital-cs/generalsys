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
    'flex-wrap align-items-center' => ! $stacked,
]) }}>
    @forelse ($profiles as $profile)
        <span class="badge text-bg-light border d-inline-flex flex-column justify-content-center lh-sm text-start gap-0 py-2">
            <span>{{ __('employees.shifts.'.$profile['type']) }}</span>
            <span class="num-ltr fw-normal">{{ $profile['start'] }}–{{ $profile['end'] }}</span>
        </span>
    @empty
        <span>{{ __('general.not_available') }}</span>
    @endforelse
</div>

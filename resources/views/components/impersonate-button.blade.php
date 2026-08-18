@props([
    'user',
    'size' => 'sm',
])

@php
    $displayName = app()->getLocale() === 'ar' ? $user->name_ar : $user->name_en;
@endphp

<form method="POST" action="{{ route('admin.employees.impersonate', $user) }}" class="d-inline">
    @csrf
    <button type="submit"
            class="btn btn-{{ $size }} btn-outline-secondary"
            title="{{ __('impersonation.login_as') }}"
            onclick="return confirm(@js(__('impersonation.confirm', ['name' => $displayName])))">
        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
        <span class="d-none d-md-inline ms-1">{{ __('impersonation.login_as') }}</span>
    </button>
</form>

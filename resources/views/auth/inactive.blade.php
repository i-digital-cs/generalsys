@extends('layouts.auth')

@section('title', __('auth.inactive_title'))

@section('content')
    <div class="card shadow-sm border-0 border-warning">
        <div class="card-body p-4 text-center">
            <div class="text-warning mb-3" aria-hidden="true">
                <i class="bi bi-person-x-fill display-4"></i>
            </div>
            <h1 class="h4 mb-2">{{ __('auth.inactive_title') }}</h1>
            <p class="text-body-secondary mb-4">{{ __('auth.inactive_message') }}</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">{{ __('auth.logout') }}</button>
            </form>
        </div>
    </div>

    <div class="text-center mt-3">
        <a href="{{ route('locale', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
           class="link-secondary"
           title="{{ __('general.language') }}"
           aria-label="{{ __('general.switch_language') }}">
            <i class="bi bi-globe me-1" aria-hidden="true"></i>{{ __('general.language') }}
        </a>
    </div>
@endsection

@extends('layouts.auth')

@section('title', __('auth.login'))

@section('content')
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-1">{{ config('app.name') }}</h1>
            <p class="text-center text-body-secondary mb-4">{{ __('auth.sign_in') }}</p>

            <form method="POST" action="{{ route('login.store') }}" data-login-form autocomplete="on">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('auth.email') }}</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email') }}"
                           autocomplete="username" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('auth.password') }}</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                           id="password" name="password" autocomplete="current-password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">{{ __('auth.remember_me') }}</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('auth.login') }}</button>
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

@push('scripts')
    @vite(['resources/js/pages/login.js'])
@endpush

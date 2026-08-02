@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite([$dir === 'rtl' ? 'resources/scss/app.rtl.scss' : 'resources/scss/app.scss'])
</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100">
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4">
                @include('layouts.partials.flash')
                @yield('content')
            </div>
        </div>
    </main>

    @vite(['resources/js/app.js'])
    @stack('scripts')
</body>
</html>

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
    @include('layouts.partials.fonts')
    @vite([$dir === 'rtl' ? 'resources/scss/app.rtl.scss' : 'resources/scss/app.scss'])
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
    @include('layouts.partials.navbar')
    @include('layouts.partials.impersonation-banner')

    <div class="d-flex flex-grow-1">
        @include('layouts.partials.sidebar')

        <main class="flex-grow-1 p-3" style="min-width: 0;">
            @include('layouts.partials.breadcrumbs')
            @include('layouts.partials.flash')
            @yield('content')
        </main>
    </div>

    @vite(['resources/js/app.js'])
    @stack('scripts')
</body>
</html>

{{-- Critical Cairo faces first (locale-aware) to reduce FOUT on hard refresh. --}}
@php
    $fontLocale = $locale ?? app()->getLocale();
    $cairoPreload = $fontLocale === 'ar'
        ? [
            'cairo-400-arabic.woff2',
            'cairo-600-arabic.woff2',
            'cairo-700-arabic.woff2',
            'cairo-400-latin.woff2',
        ]
        : [
            'cairo-400-latin.woff2',
            'cairo-600-latin.woff2',
            'cairo-700-latin.woff2',
        ];
@endphp
@foreach ($cairoPreload as $fontFile)
    <link rel="preload" href="{{ asset('fonts/'.$fontFile) }}" as="font" type="font/woff2" crossorigin>
@endforeach
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">

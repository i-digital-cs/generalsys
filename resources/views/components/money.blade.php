@props([
    'amount',
    'currency' => 'EGP',
])

@php
    $value = number_format((float) $amount, 2, '.', ',');
    $suffix = $currency === 'AED' ? 'AED' : 'ج.م';
@endphp
<span {{ $attributes->merge(['dir' => 'ltr', 'class' => 'text-nowrap']) }}>{{ $value }} {{ $suffix }}</span>

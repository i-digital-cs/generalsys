@props([
    'amount',
    'currency' => 'EGP',
])

@php
    $value = number_format((float) $amount, 2, '.', ',');
    $code = in_array($currency, ['EGP', 'AED'], true) ? $currency : 'EGP';
    $suffix = __('general.currencies.'.$code);
@endphp
<span {{ $attributes->merge(['dir' => 'ltr', 'class' => 'text-nowrap']) }}>{{ $value }} {{ $suffix }}</span>

@props(['score' => 0])
@php
    $n = \App\Services\Valuation\ValuationCalculator::starsFromScore($score);
@endphp
<span {{ $attributes->merge(['class' => 'valuation-stars', 'dir' => 'ltr']) }} aria-hidden="true">
    @for ($i = 1; $i <= 5; $i++)
        <i class="bi {{ $i <= $n ? 'bi-star-fill' : 'bi-star' }}"></i>
    @endfor
</span>

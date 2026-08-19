@props(['value' => 0])
<span {{ $attributes->merge(['class' => 'num-ltr']) }}>{{ \App\Services\Valuation\ValuationCalculator::formatPercent((float) $value) }}</span>

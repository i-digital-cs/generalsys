@php
    $currency = $stats['currency'] ?? 'EGP';
    $compactTitle = $compactTitle ?? false;
@endphp
<div class="mb-4">
    @unless ($compactTitle)
        <h2 class="h6 mb-3">{{ __('valuation.stats.title') }}
            <span class="text-body-secondary fw-normal">· {{ $stats['month_label'] }}</span>
        </h2>
    @endunless
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.stats.gross')" icon="bi-cash-stack" color="primary">
                <x-money :amount="$stats['gross']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.stats.target')" icon="bi-bullseye" color="info">
                <x-money :amount="$stats['target']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.stats.gap')" icon="bi-arrows-collapse" :color="$stats['gap_tone'] ?? ($stats['gap'] >= 0 ? 'warning' : 'success')">
                <x-money :amount="$stats['gap']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('valuation.stats.kpi_avg')"
                :value="$stats['kpi_avg_display']"
                icon="bi-flag"
                color="secondary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.stats.b2b_sales')" :subtitle="__('valuation.stats.b2b_count', ['n' => $stats['b2b_count']])" icon="bi-building" color="success">
                <x-money :amount="$stats['b2b']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.stats.b2c_sales')" :subtitle="__('valuation.stats.b2c_count', ['n' => $stats['b2c_count']])" icon="bi-people" color="success">
                <x-money :amount="$stats['b2c']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('valuation.stats.b2b_rate')"
                :value="$stats['b2b_rate_display']"
                icon="bi-percent"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('valuation.stats.b2c_rate')"
                :value="$stats['b2c_rate_display']"
                icon="bi-percent"
                color="primary" />
        </div>
    </div>
    <p class="small text-body-secondary mt-2 mb-0">{{ __('valuation.stats.hint', ['count' => $stats['sheets']]) }}</p>
</div>

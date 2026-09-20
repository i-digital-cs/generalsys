@if (! empty($salesStats))
    <div class="mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h6 mb-0">{{ __('gross.sales_stats.dashboard_title') }}</h2>
            <a href="{{ $salesStats['stats_url'] }}" class="btn btn-sm btn-outline-primary">{{ __('gross.sales_stats.dashboard_link') }}</a>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <x-stat-card
                    :title="$salesStats['own_only'] ? __('gross.sales_stats.dashboard_own') : __('gross.sales_stats.dashboard_net')"
                    icon="bi-cash-stack"
                    color="primary"
                    :show-sparkline="false"
                    :subtitle="$salesStats['month']"
                >
                    <span class="num-ltr"><x-money :amount="$salesStats['net_merchandise']" :currency="$salesStats['currency']" /></span>
                </x-stat-card>
            </div>
            <div class="col-md-4">
                <x-stat-card
                    :title="__('gross.sales_stats.dashboard_invoices')"
                    icon="bi-receipt"
                    color="info"
                    :show-sparkline="false"
                    :value="$salesStats['invoices_count']"
                    :subtitle="$salesStats['month']"
                />
            </div>
            @if (! $salesStats['own_only'] && ! empty($salesStats['top_seller_name']))
                <div class="col-md-4">
                    <x-stat-card
                        :title="__('gross.sales_stats.dashboard_top')"
                        icon="bi-trophy"
                        color="success"
                        :show-sparkline="false"
                        :subtitle="$salesStats['top_seller_name']"
                    >
                        <span class="num-ltr"><x-money :amount="$salesStats['top_seller_amount']" :currency="$salesStats['currency']" /></span>
                    </x-stat-card>
                </div>
            @endif
        </div>
    </div>
@endif

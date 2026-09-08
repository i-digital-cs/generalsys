@php
    $currency = $expenseStats['currency'] ?? 'EGP';
    $summary = $expenseStats['summary'] ?? ['expected' => 0, 'spent' => 0, 'remaining' => 0, 'over_budget_count' => 0];
    $categories = $expenseStats['categories'] ?? [];
    $year = $expenseStats['year'] ?? now()->year;
    $month = $expenseStats['month'] ?? now()->month;
@endphp
<div class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h6 mb-0">{{ __('product_expenses.summary.title') }}
                <span class="text-body-secondary fw-normal">· {{ $expenseStats['month_label'] ?? '' }}</span>
            </h2>
            <p class="small text-body-secondary mb-0">{{ __('product_expenses.subtitle') }}</p>
        </div>
        @can('viewAny', \App\Models\Gross\GrossExpenseType::class)
            <a class="btn btn-sm btn-outline-primary" href="{{ route('gross.expense-types.index') }}">
                {{ __('product_expenses.manage_categories') }}
            </a>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('product_expenses.summary.expected')" icon="bi-bullseye" color="info">
                <x-money :amount="$summary['expected']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('product_expenses.summary.spent')" icon="bi-cash" color="warning">
                <x-money :amount="$summary['spent']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('product_expenses.summary.remaining')"
                icon="bi-wallet2"
                :color="$summary['remaining'] < 0 ? 'danger' : 'success'">
                <x-money :amount="$summary['remaining']" :currency="$currency" />
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('product_expenses.summary.over_budget')"
                :value="(string) $summary['over_budget_count']"
                :subtitle="__('product_expenses.summary.over_budget_count', ['n' => $summary['over_budget_count']])"
                icon="bi-exclamation-triangle"
                :color="$summary['over_budget_count'] > 0 ? 'danger' : 'secondary'" />
        </div>
    </div>

    <h3 class="h6 mb-2">{{ __('product_expenses.categories_title') }}</h3>
    <div class="row g-3">
        @forelse ($categories as $cat)
            <div class="col-6 col-lg-3">
                <a href="{{ route('product.expenses.show', ['expenseType' => $cat['id'], 'year' => $year, 'month' => $month]) }}"
                   class="text-decoration-none text-body d-block h-100">
                    <x-stat-card
                        :title="$cat['name']"
                        :subtitle="__('product_expenses.expected').': '.number_format($cat['expected'], 2).' · '.__('product_expenses.spent').': '.number_format($cat['spent'], 2)"
                        icon="bi-receipt"
                        :color="$cat['over'] ? 'danger' : ($cat['remaining'] < 0 ? 'warning' : 'primary')">
                        <span class="small text-body-secondary d-block">{{ __('product_expenses.remaining') }}</span>
                        <x-money :amount="$cat['remaining']" :currency="$currency" />
                    </x-stat-card>
                </a>
            </div>
        @empty
            <div class="col-12">
                <p class="text-body-secondary mb-0">{{ __('product_expenses.no_entries') }}</p>
            </div>
        @endforelse
    </div>
</div>

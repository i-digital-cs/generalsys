@extends('layouts.app')

@section('title', __('gross.sales_stats.title'))

@section('content')
    @php
        $defaultMonth = now()->format('Y-m');
        $defaultFrom = now()->startOfMonth()->toDateString();
        $defaultTo = now()->endOfMonth()->toDateString();
        $hasFilters = ($filters['from'] ?? '') !== $defaultFrom
            || ($filters['to'] ?? '') !== $defaultTo
            || (($filters['currency'] ?? 'EGP') !== 'EGP')
            || (($filters['month'] ?? '') !== $defaultMonth)
            || (($filters['sales_user_id'] ?? '') !== '');
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gross.sales_stats.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gross.sales_stats.subtitle') }}</p>
        </div>
        <a href="{{ route('gross.sales.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gross.sales.title') }}</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end" data-sales-stats-filters>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="month">{{ __('gross.sales_stats.month') }}</label>
                    <input id="month" name="month" type="month" class="form-control form-control-sm"
                           value="{{ $filters['month'] ?? '' }}" data-month-input>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="from">{{ __('gross.fields.date_from') }}</label>
                    <input id="from" name="from" type="date" class="form-control form-control-sm"
                           value="{{ $filters['from'] ?? '' }}" data-from-input>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="to">{{ __('gross.fields.date_to') }}</label>
                    <input id="to" name="to" type="date" class="form-control form-control-sm"
                           value="{{ $filters['to'] ?? '' }}" data-to-input>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" for="currency">{{ __('gross.fields.currency') }}</label>
                    <select id="currency" name="currency" class="form-select form-select-sm">
                        @foreach (['EGP', 'AED'] as $cur)
                            <option value="{{ $cur }}" @selected(($filters['currency'] ?? 'EGP') === $cur)>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canFilterSalesPerson ?? false)
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1" for="sales_user_id">{{ __('gross.sales_stats.sales_person') }}</label>
                        <select id="sales_user_id" name="sales_user_id" class="form-select form-select-sm">
                            <option value="">{{ __('general.all') }}</option>
                            <option value="unassigned" @selected(($filters['sales_user_id'] ?? '') === 'unassigned')>
                                {{ __('gross.sales_stats.unassigned') }}
                            </option>
                            @foreach ($salesPeople ?? [] as $person)
                                <option value="{{ $person->id }}" @selected((string) ($filters['sales_user_id'] ?? '') === (string) $person->id)>
                                    {{ $person->localizedName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('general.filter') }}</button>
                    @if ($hasFilters)
                        <a href="{{ route('gross.sales.stats') }}" class="btn btn-outline-secondary btn-sm">{{ __('general.all') }}</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.net_merchandise')"
                icon="bi-cash-stack"
                color="primary"
                :show-sparkline="false"
            >
                <span class="num-ltr"><x-money :amount="$totals['net_merchandise']" :currency="$filters['currency']" /></span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.invoices')"
                icon="bi-receipt"
                color="info"
                :show-sparkline="false"
                :value="$totals['invoices_count']"
            />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.cod_collected')"
                icon="bi-cash-coin"
                color="success"
                :show-sparkline="false"
            >
                <span class="num-ltr"><x-money :amount="$totals['cod_collected']" :currency="$filters['currency']" /></span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card
                :title="__('gross.sales_stats.refuse_rate')"
                icon="bi-exclamation-triangle"
                color="warning"
                :show-sparkline="false"
                :value="number_format((float) $totals['refuse_rate'], 1).'%'"
            />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if (empty($rows))
                <x-empty-state :title="__('gross.sales_stats.empty')" icon="bi-bar-chart-line" />
            @else
                <div class="table-responsive">
                    <table class="table table-hr table-sm table-hover align-middle mb-0" data-sales-stats-table>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('gross.sales_stats.sales_person') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.invoices') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.delivered') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.refused') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.net_merchandise') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.returned_merchandise') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.cod_collected') }}</th>
                                <th scope="col" class="col-num text-nowrap">{{ __('gross.sales_stats.cod_pending') }}</th>
                                <th scope="col" class="text-nowrap">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $row['name'] }}</td>
                                    <td class="col-num"><span class="num-ltr">{{ (int) $row['invoices_count'] }}</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ (int) $row['delivered_count'] }}</span></td>
                                    <td class="col-num"><span class="num-ltr">{{ (int) $row['refused_count'] }}</span></td>
                                    <td class="col-num fw-semibold">
                                        <span class="num-ltr"><x-money :amount="$row['net_merchandise']" :currency="$filters['currency']" /></span>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$row['returned_merchandise']" :currency="$filters['currency']" /></span>
                                    </td>
                                    <td class="col-num">
                                        <span class="num-ltr"><x-money :amount="$row['cod_collected']" :currency="$filters['currency']" /></span>
                                    </td>
                                    <td class="col-num"><span class="num-ltr">{{ (int) $row['cod_pending_count'] }}</span></td>
                                    <td class="text-nowrap">
                                        <a href="{{ $row['url'] }}" class="btn btn-sm btn-outline-primary">{{ __('general.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>{{ __('gross.sales_stats.total_row') }}</td>
                                <td class="col-num"><span class="num-ltr">{{ (int) $totals['invoices_count'] }}</span></td>
                                <td class="col-num"><span class="num-ltr">{{ (int) $totals['delivered_count'] }}</span></td>
                                <td class="col-num"><span class="num-ltr">{{ (int) $totals['refused_count'] }}</span></td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$totals['net_merchandise']" :currency="$filters['currency']" /></span>
                                </td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$totals['returned_merchandise']" :currency="$filters['currency']" /></span>
                                </td>
                                <td class="col-num">
                                    <span class="num-ltr"><x-money :amount="$totals['cod_collected']" :currency="$filters['currency']" /></span>
                                </td>
                                <td class="col-num"><span class="num-ltr">{{ (int) $totals['cod_pending_count'] }}</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.querySelector('[data-sales-stats-filters]');
            if (! form) return;
            var month = form.querySelector('[data-month-input]');
            var from = form.querySelector('[data-from-input]');
            var to = form.querySelector('[data-to-input]');
            if (!month || !from || !to) return;

            month.addEventListener('change', function () {
                if (!month.value) return;
                var parts = month.value.split('-');
                if (parts.length !== 2) return;
                var y = parseInt(parts[0], 10);
                var m = parseInt(parts[1], 10);
                if (!y || !m) return;
                var start = new Date(y, m - 1, 1);
                var end = new Date(y, m, 0);
                var pad = function (n) { return String(n).padStart(2, '0'); };
                from.value = y + '-' + pad(m) + '-01';
                to.value = y + '-' + pad(m) + '-' + pad(end.getDate());
            });
        })();
    </script>
@endpush

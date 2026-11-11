{{-- Modern card presentation (working tree) --}}
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.dashboard.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.dashboard.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-primary btn-sm">{{ __('gh.dashboard.reports') }}</a>
    </div>

    @include('gh.reports.partials.filters', ['action' => route('gh.dashboard')])

    <h2 class="h6 text-body-secondary mb-2">{{ __('gh.dashboard.operational') }}</h2>
    <div class="row g-2 mb-3">
        @foreach ([
            'total_orders','new_orders','awaiting_pickup','working','delivery','completed',
            'cancelled','overdue_pickups','failed_pickup_attempts','failed_delivery_attempts',
        ] as $key)
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('gh.dashboard.kpi.'.$key)"
                    :value="(string) ($operational[$key] ?? 0)"
                    icon="bi-clipboard-data"
                    color="primary"
                    :show-sparkline="false"
                />
            </div>
        @endforeach
        <div class="col-6 col-md-4 col-xl-2">
            <x-stat-card
                :title="__('gh.dashboard.kpi.overdue_deliveries')"
                :value="__('gh.dashboard.unavailable')"
                :subtitle="__('gh.dashboard.overdue_deliveries_note')"
                icon="bi-question-circle"
                color="secondary"
                :show-sparkline="false"
            />
        </div>
    </div>

    <h2 class="h6 text-body-secondary mb-2">{{ __('gh.dashboard.financial') }}</h2>
    <div class="row g-2 mb-3">
        @foreach ([
            'issued_invoice_value','issued_credit_notes_value','net_invoiced_value',
            'confirmed_cash_collections','cash_refunds_paid_out','net_cash_collected',
            'total_confirmed_collections','outstanding_receivables','customer_credit_liabilities','average_invoice_value',
        ] as $key)
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card :title="__('gh.dashboard.kpi.'.$key)" icon="bi-cash-coin" color="success" :show-sparkline="false">
                    <span class="num-ltr"><x-money :amount="$financial[$key]" currency="AED" /></span>
                </x-stat-card>
            </div>
        @endforeach
        @foreach (['partially_paid_invoices','unpaid_invoices','paid_invoices'] as $key)
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('gh.dashboard.kpi.'.$key)"
                    :value="(string) $financial[$key]"
                    icon="bi-receipt"
                    color="info"
                    :show-sparkline="false"
                />
            </div>
        @endforeach
    </div>

    <h2 class="h6 text-body-secondary mb-2">{{ __('gh.dashboard.drivers') }}</h2>
    <div class="row g-2 mb-3">
        @foreach (['assigned_pickups','assigned_deliveries','completed_pickups','completed_deliveries','failed_attempts'] as $key)
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card
                    :title="__('gh.dashboard.kpi.'.$key)"
                    :value="(string) $driverKpis[$key]"
                    icon="bi-truck"
                    color="warning"
                    :show-sparkline="false"
                />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.status_distribution') }}</h2></div>
                <div class="card-body">
                    @php $max = max(1, collect($status_distribution)->max('count') ?: 1); @endphp
                    @forelse ($status_distribution as $row)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ __('gh.order_status.'.$row['status']) }}</span>
                                <span class="hp-value num-ltr">{{ $row['count'] }}</span>
                            </div>
                            <div class="progress" style="height:0.5rem" role="progressbar"
                                 aria-valuenow="{{ $row['count'] }}" aria-valuemin="0" aria-valuemax="{{ $max }}"
                                 aria-label="{{ __('gh.order_status.'.$row['status']) }}">
                                <div class="progress-bar" style="width: {{ round(100 * $row['count'] / $max) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-body-secondary small mb-0">{{ __('gh.reports.empty') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.order_trend') }}</h2></div>
                <div class="card-body">
                    <x-stat-card
                        :title="__('gh.dashboard.order_trend')"
                        :value="(string) array_sum($order_trend)"
                        :sparkline="$order_trend"
                        icon="bi-graph-up"
                        color="primary"
                    />
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.pending_by_driver') }}</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($driverKpis['pending_by_driver'] as $row)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ app()->getLocale()==='ar' ? $row['name_ar'] : ($row['name_en'] ?: $row['name_ar']) }}</span>
                            <span class="num-ltr hp-value">
                                {{ __('gh.reports.pickups') }} {{ $row['pickups'] }} · {{ __('gh.reports.deliveries') }} {{ $row['deliveries'] }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('gh.reports.empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="gh-card-ui" data-gh-card-ui="dashboard">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.pending_pickups') }}</h2></div>
                    <div class="card-body">
                        @if ($pending_pickups->isEmpty())
                            <p class="text-body-secondary small mb-0">{{ __('gh.reports.empty') }}</p>
                        @else
                            <div class="gh-card-ui__grid">
                                @foreach ($pending_pickups as $o)
                                    <x-gh.record-card
                                        :title="$o->reference"
                                        :title-href="route('gh.orders.show', $o)"
                                        :title-ltr="true"
                                    >
                                        <x-slot:meta>
                                            <x-gh.meta-cell icon="bi-person" :value="$o->customer?->localizedName() ?? '—'" />
                                            <x-gh.meta-cell
                                                icon="bi-calendar3"
                                                :value="$o->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') ?? '—'"
                                                :ltr="true"
                                            />
                                        </x-slot:meta>
                                    </x-gh.record-card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.pending_deliveries') }}</h2></div>
                    <div class="card-body">
                        @if ($pending_deliveries->isEmpty())
                            <p class="text-body-secondary small mb-0">{{ __('gh.reports.empty') }}</p>
                        @else
                            <div class="gh-card-ui__grid">
                                @foreach ($pending_deliveries as $o)
                                    <x-gh.record-card
                                        :title="$o->reference"
                                        :title-href="route('gh.orders.show', $o)"
                                        :title-ltr="true"
                                    >
                                        <x-slot:meta>
                                            <x-gh.meta-cell icon="bi-person" :value="$o->customer?->localizedName() ?? '—'" />
                                            <x-gh.meta-cell
                                                icon="bi-truck"
                                                :value="$o->deliveryDriver?->name_en ?: $o->deliveryDriver?->name_ar ?: '—'"
                                            />
                                        </x-slot:meta>
                                    </x-gh.record-card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.recent_orders') }}</h2></div>
                    <div class="card-body">
                        @if ($recent_orders->isEmpty())
                            <p class="text-body-secondary small mb-0">{{ __('gh.reports.empty') }}</p>
                        @else
                            <div class="gh-card-ui__grid">
                                @foreach ($recent_orders as $o)
                                    <x-gh.record-card
                                        :title="$o->reference"
                                        :title-href="route('gh.orders.show', $o)"
                                        :title-ltr="true"
                                    >
                                        <x-slot:badges>
                                            <span class="gh-record-card__badge gh-record-card__badge--status">{{ __('gh.order_status.'.$o->status) }}</span>
                                        </x-slot:badges>
                                        <x-slot:meta>
                                            <x-gh.meta-cell icon="bi-person" :value="$o->customer?->localizedName() ?? '—'" />
                                        </x-slot:meta>
                                    </x-gh.record-card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.dashboard.outstanding_invoices') }}</h2></div>
                    <div class="card-body">
                        @if ($outstanding_invoices->isEmpty())
                            <p class="text-body-secondary small mb-0">{{ __('gh.reports.empty') }}</p>
                        @else
                            <div class="gh-card-ui__grid">
                                @foreach ($outstanding_invoices as $inv)
                                    @php
                                        $invCustomer = app()->getLocale() === 'ar'
                                            ? $inv->customer_name_ar
                                            : ($inv->customer_name_en ?: $inv->customer_name_ar);
                                    @endphp
                                    <x-gh.record-card
                                        :title="$inv->number"
                                        :title-href="route('gh.invoices.show', $inv)"
                                        :title-ltr="true"
                                    >
                                        <x-slot:badges>
                                            <span class="gh-record-card__badge gh-record-card__badge--muted">{{ __('gh.payment_status.'.$inv->payment_status) }}</span>
                                        </x-slot:badges>
                                        <x-slot:meta>
                                            <x-gh.meta-cell icon="bi-person" :value="$invCustomer" />
                                            <div class="gh-record-card__cell">
                                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                                <span class="gh-record-card__value"><x-money :amount="$inv->total_payable" currency="AED" /></span>
                                            </div>
                                        </x-slot:meta>
                                    </x-gh.record-card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

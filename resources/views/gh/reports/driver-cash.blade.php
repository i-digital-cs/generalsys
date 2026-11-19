@extends('layouts.app')
@section('title', __('gh.reports.driver_cash'))
@section('content')
@php
    $exportUrl = fn (string $dataset) => route('gh.reports.driver-cash', $query + ['export' => $dataset]);
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('gh.reports.driver_cash') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.driver-cash.settlements.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.settlements_title') }}</a>
            <a href="{{ route('gh.reports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.back') }}</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="preset">{{ __('gh.reports.preset') }}</label>
                    <select id="preset" name="preset" class="form-select form-select-sm">
                        @foreach (\App\Support\GeneralHouse\GhReportPeriod::PRESETS as $val)
                            <option value="{{ $val }}" @selected($period->preset === $val)>{{ __('gh.reports.preset_'.$val) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="form-label" for="from">{{ __('gh.reports.from') }}</label>
                    <input id="from" type="date" name="from" class="form-control form-control-sm" value="{{ $period->fromDate }}">
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="form-label" for="to">{{ __('gh.reports.to') }}</label>
                    <input id="to" type="date" name="to" class="form-control form-control-sm" value="{{ $period->toDate }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="driver_id">{{ __('gh.reports.driver') }}</label>
                    <select id="driver_id" name="driver_id" class="form-select form-select-sm">
                        <option value="">{{ __('gh.reports.all') }}</option>
                        @foreach ($driverOptions as $d)
                            <option value="{{ $d->id }}" @selected($driverId === (int) $d->id)>{{ $d->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.reports.apply') }}</button>
                </div>
            </form>
            <div class="mt-2">@include('gh.driver-cash.partials.tz-note', ['tz' => $displayTz])</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.period_collected')" icon="bi-cash-stack" color="primary" :show-sparkline="false"><x-money :amount="$totals['collected']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.period_settled')" icon="bi-check2-circle" color="success" :show-sparkline="false"><x-money :amount="$totals['settled']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.period_reversed')" icon="bi-arrow-counterclockwise" color="secondary" :show-sparkline="false"><x-money :amount="$totals['reversed']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.reports.dc_outstanding_now')" icon="bi-wallet2" color="warning" :show-sparkline="false"><x-money :amount="$totals['outstanding_now']" currency="AED" /></x-stat-card>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge text-bg-danger">{{ __('gh.driver_cash.count_not_settled') }}: <span class="num-ltr">{{ $totals['counts']['not_settled'] }}</span></span>
        <span class="badge text-bg-warning">{{ __('gh.driver_cash.count_partial') }}: <span class="num-ltr">{{ $totals['counts']['partial'] }}</span></span>
        <span class="badge text-bg-success">{{ __('gh.driver_cash.count_settled') }}: <span class="num-ltr">{{ $totals['counts']['settled'] }}</span></span>
        <span class="small text-body-secondary">{{ __('gh.reports.dc_counts_hint') }}</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 card-title mb-0">{{ __('gh.reports.dc_by_driver') }}</h2>
                    <a href="{{ $exportUrl('by_driver') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.export_csv') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.reports.driver') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.collected_total') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.settled_total') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.outstanding') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($byDriver as $d)
                            <tr>
                                <td><a href="{{ route('gh.driver-cash.show', $d->id) }}">{{ $d->localizedName() }}</a></td>
                                <td class="text-end"><x-money :amount="$d->collected_total" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$d->settled_total" currency="AED" /></td>
                                <td class="text-end fw-semibold"><x-money :amount="$d->outstanding" currency="AED" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 card-title mb-0">{{ __('gh.reports.dc_by_manager') }}</h2>
                    <a href="{{ $exportUrl('by_manager') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.export_csv') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.driver_cash.manager') }}</th>
                            <th class="text-center">{{ __('gh.reports.dc_settlements_count') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.settled') }}</th>
                            <th class="text-center">{{ __('gh.reports.dc_reversals_count') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.reversed') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($byManager as $m)
                            <tr>
                                <td>{{ $m['manager']?->localizedName() ?? '—' }}</td>
                                <td class="text-center num-ltr">{{ $m['settled_count'] }}</td>
                                <td class="text-end"><x-money :amount="$m['settled']" currency="AED" /></td>
                                <td class="text-center num-ltr">{{ $m['reversed_count'] }}</td>
                                <td class="text-end"><x-money :amount="$m['reversed']" currency="AED" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 card-title mb-0">{{ __('gh.reports.dc_outstanding_invoices') }}</h2>
                    <a href="{{ $exportUrl('outstanding') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.export_csv') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
                            <th>{{ __('gh.reports.driver') }}</th>
                            <th>{{ __('gh.driver_cash.order') }}</th>
                            <th>{{ __('gh.driver_cash.invoice') }}</th>
                            <th>{{ __('gh.driver_cash.collected_at') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.settled') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.remaining') }}</th>
                            <th>{{ __('gh.driver_cash.status') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($outstanding as $c)
                            <tr>
                                <td class="num-ltr text-nowrap">{{ $c->reference }}</td>
                                <td>{{ $c->driver?->localizedName() }}</td>
                                <td class="num-ltr text-nowrap"><a href="{{ route('gh.orders.show', $c->order_id) }}">{{ $c->order?->reference }}</a></td>
                                <td class="num-ltr text-nowrap">
                                    @if ($c->order?->invoice)
                                        <a href="{{ route('gh.invoices.show', $c->order->invoice) }}">{{ $c->order->invoice->number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="num-ltr small text-nowrap"><x-gh.dubai-time :at="$c->collected_at" :badge="true" /></td>
                                <td class="text-end"><x-money :amount="$c->amount" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$c->settled_amount" currency="AED" /></td>
                                <td class="text-end fw-semibold"><x-money :amount="$c->remaining_amount" currency="AED" /></td>
                                <td>@include('gh.driver-cash.partials.status-badge', ['status' => $c->settlement_status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($outstanding->hasPages())
                    <div class="card-footer bg-transparent">{{ $outstanding->links() }}</div>
                @endif
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 card-title mb-0">{{ __('gh.reports.dc_by_date') }}</h2>
                    <a href="{{ $exportUrl('by_date') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.reports.export_csv') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('gh.driver_cash.occurred_at') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.period_collected') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.period_settled') }}</th>
                            <th class="text-end">{{ __('gh.driver_cash.period_reversed') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($byDate as $r)
                            <tr>
                                <td class="num-ltr text-nowrap">{{ $r['date'] }}</td>
                                <td class="text-end"><x-money :amount="$r['collected']" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$r['settled']" currency="AED" /></td>
                                <td class="text-end"><x-money :amount="$r['reversed']" currency="AED" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">{{ __('gh.reports.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

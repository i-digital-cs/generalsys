@extends('layouts.app')

@section('title', __('gh.driver_cash.title').' — '.$driver->localizedName())

@section('content')
@php
    $baseQuery = array_filter([
        'preset' => $period->preset,
        'from' => $period->preset === 'custom' ? $period->fromDate : null,
        'to' => $period->preset === 'custom' ? $period->toDate : null,
        'as_of' => $asOf['date'] ?? null,
    ]);
    $tabs = ['collections', 'outstanding', 'settlements', 'adjustments', 'ledger'];
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $driver->localizedName() }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gh.driver-cash.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.title') }}</a>
            <a href="{{ route('gh.driver-cash.settlements.create', $driver) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>{{ __('gh.driver_cash.new_settlement') }}
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @include('gh.driver-cash.partials.driver-picker', ['driverOptions' => $driverOptions, 'selectedId' => $driver->id])
        </div>
    </div>

    @unless ($summary['reconciled'])
        <div class="alert alert-danger" role="alert">
            {{ __('gh.driver_cash.not_reconciled', ['balance' => $summary['balance_outstanding'], 'ledger' => $summary['outstanding']]) }}
        </div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.collected_total')" icon="bi-cash-stack" color="primary" :show-sparkline="false"><x-money :amount="$summary['collected']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.settled_total')" icon="bi-check2-circle" color="success" :show-sparkline="false"><x-money :amount="$summary['settled']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.outstanding')" icon="bi-wallet2" color="warning" :show-sparkline="false"><x-money :amount="$summary['outstanding']" currency="AED" /></x-stat-card>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-stat-card :title="__('gh.driver_cash.reversed_total')" icon="bi-arrow-counterclockwise" color="secondary" :show-sparkline="false"><x-money :amount="$summary['reversed']" currency="AED" /></x-stat-card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge text-bg-danger">{{ __('gh.driver_cash.count_not_settled') }}: <span class="num-ltr">{{ $summary['counts']['not_settled'] }}</span></span>
                        <span class="badge text-bg-warning">{{ __('gh.driver_cash.count_partial') }}: <span class="num-ltr">{{ $summary['counts']['partial'] }}</span></span>
                        <span class="badge text-bg-success">{{ __('gh.driver_cash.count_settled') }}: <span class="num-ltr">{{ $summary['counts']['settled'] }}</span></span>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-6 form-label mb-1">{{ __('gh.driver_cash.last_collected_at') }}</dt>
                        <dd class="col-6 hp-value mb-1"><span class="num-ltr"><x-gh.dubai-time :at="$summary['last_collected_at']" :badge="true" /></span></dd>
                        <dt class="col-6 form-label mb-1">{{ __('gh.driver_cash.last_settled_at') }}</dt>
                        <dd class="col-6 hp-value mb-1"><span class="num-ltr"><x-gh.dubai-time :at="$summary['last_settled_at']" :badge="true" /></span></dd>
                        @if (bccomp($summary['unallocated'], '0', 2) > 0)
                            <dt class="col-6 form-label mb-1">{{ __('gh.driver_cash.unallocated') }}</dt>
                            <dd class="col-6 mb-1"><x-money :amount="$summary['unallocated']" currency="AED" /></dd>
                            <dd class="col-12 text-body-secondary mb-0">{{ __('gh.driver_cash.unallocated_hint') }}</dd>
                        @endif
                    </dl>
                    @if ($summary['reconciled'])
                        <p class="small text-success mb-0 mt-2"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('gh.driver_cash.reconciled') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.period_activity') }}</h2></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-7 form-label mb-1">{{ __('gh.driver_cash.period_collected') }}</dt>
                        <dd class="col-5 text-end mb-1"><x-money :amount="$activity['collected']" currency="AED" /></dd>
                        <dt class="col-7 form-label mb-1">{{ __('gh.driver_cash.period_settled') }}</dt>
                        <dd class="col-5 text-end mb-1"><x-money :amount="$activity['settled']" currency="AED" /></dd>
                        <dt class="col-7 form-label mb-1">{{ __('gh.driver_cash.period_reversed') }}</dt>
                        <dd class="col-5 text-end mb-1"><x-money :amount="$activity['reversed']" currency="AED" /></dd>
                        @if ($asOf)
                            <dt class="col-7 form-label mb-0">{{ __('gh.driver_cash.as_of_outstanding', ['date' => $asOf['date']]) }}</dt>
                            <dd class="col-5 text-end mb-0 fw-semibold"><x-money :amount="$asOf['outstanding']" currency="AED" /></dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="{{ route('gh.driver-cash.show', $driver) }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="preset">{{ __('gh.driver_cash.period') }}</label>
                    <select id="preset" name="preset" class="form-select form-select-sm">
                        @foreach (\App\Support\GeneralHouse\GhReportPeriod::PRESETS as $val)
                            <option value="{{ $val }}" @selected($period->preset === $val)>{{ __('gh.reports.preset_'.$val) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="form-label" for="from">{{ __('gh.driver_cash.from') }}</label>
                    <input id="from" type="date" name="from" class="form-control form-control-sm" value="{{ $period->fromDate }}">
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="form-label" for="to">{{ __('gh.driver_cash.to') }}</label>
                    <input id="to" type="date" name="to" class="form-control form-control-sm" value="{{ $period->toDate }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="as_of">{{ __('gh.driver_cash.as_of') }}</label>
                    <input id="as_of" type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf['date'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.driver_cash.apply') }}</button>
                </div>
                <div class="col-12 col-lg-2">@include('gh.driver-cash.partials.tz-note', ['tz' => $displayTz])</div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-0 flex-nowrap overflow-auto" role="tablist">
        @foreach ($tabs as $key)
            <li class="nav-item" role="presentation">
                <a class="nav-link text-nowrap @if ($tab === $key) active @endif" @if ($tab === $key) aria-current="page" @endif
                   href="{{ route('gh.driver-cash.show', [$driver] + $baseQuery + ['tab' => $key]) }}">{{ __('gh.driver_cash.tab_'.$key) }}</a>
            </li>
        @endforeach
    </ul>
    <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
        @include('gh.driver-cash.tabs.'.$tab, ['data' => $tabData, 'driver' => $driver, 'displayTz' => $displayTz])
    </div>
</div>
@endsection

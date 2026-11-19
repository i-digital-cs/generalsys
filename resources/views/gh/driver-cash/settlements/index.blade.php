@extends('layouts.app')

@section('title', __('gh.driver_cash.settlements_title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver_cash.settlements_title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.settlements_subtitle') }}</p>
        </div>
        <a href="{{ route('gh.driver-cash.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.title') }}</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="driver_id">{{ __('gh.driver_cash.driver') }}</label>
                    <select id="driver_id" name="driver_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($driverOptions as $d)
                            <option value="{{ $d->id }}" @selected($filters['driver_id'] === (int) $d->id)>{{ $d->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="manager_id">{{ __('gh.driver_cash.manager') }}</label>
                    <select id="manager_id" name="manager_id" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach ($settlerOptions as $m)
                            <option value="{{ $m->id }}" @selected($filters['manager_id'] === (int) $m->id)>{{ $m->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="state">{{ __('gh.driver_cash.state') }}</label>
                    <select id="state" name="state" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        <option value="active" @selected($filters['state'] === 'active')>{{ __('gh.driver_cash.active') }}</option>
                        <option value="reversed" @selected($filters['state'] === 'reversed')>{{ __('gh.driver_cash.reversed') }}</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="preset">{{ __('gh.driver_cash.period') }}</label>
                    <select id="preset" name="preset" class="form-select form-select-sm">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (\App\Support\GeneralHouse\GhReportPeriod::PRESETS as $val)
                            <option value="{{ $val }}" @selected($period?->preset === $val)>{{ __('gh.reports.preset_'.$val) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-lg-1">
                    <label class="form-label" for="from">{{ __('gh.driver_cash.from') }}</label>
                    <input id="from" type="date" name="from" class="form-control form-control-sm" value="{{ $period?->fromDate }}">
                </div>
                <div class="col-6 col-sm-3 col-lg-1">
                    <label class="form-label" for="to">{{ __('gh.driver_cash.to') }}</label>
                    <input id="to" type="date" name="to" class="form-control form-control-sm" value="{{ $period?->toDate }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.driver_cash.apply') }}</button>
                </div>
            </form>
            <div class="mt-2">@include('gh.driver-cash.partials.tz-note', ['tz' => $displayTz])</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        @include('gh.driver-cash.tabs.settlements', ['data' => $settlements, 'displayTz' => $displayTz, 'showDriver' => true])
    </div>
</div>
@endsection

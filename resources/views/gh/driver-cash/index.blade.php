@extends('layouts.app')

@section('title', __('gh.driver_cash.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.driver_cash.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.driver_cash.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.driver-cash.settlements.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.driver_cash.settlements_title') }}</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @include('gh.driver-cash.partials.driver-picker', ['driverOptions' => $driverOptions])
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h6 card-title mb-0">{{ __('gh.driver_cash.outstanding_drivers') }}</h2>
            <form method="get" class="d-flex gap-2" role="search">
                <label class="visually-hidden" for="q">{{ __('gh.driver_cash.search_driver') }}</label>
                <input id="q" type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="{{ __('gh.driver_cash.search_driver') }}">
                <button class="btn btn-outline-primary btn-sm" type="submit" aria-label="{{ __('gh.driver_cash.search_driver') }}"><i class="bi bi-search" aria-hidden="true"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>{{ __('gh.driver_cash.driver') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.collected_total') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.settled_total') }}</th>
                    <th class="text-end">{{ __('gh.driver_cash.outstanding') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($drivers as $row)
                    <tr>
                        <td>{{ $row->localizedName() }}</td>
                        <td class="text-end"><x-money :amount="$row->collected_total" currency="AED" /></td>
                        <td class="text-end"><x-money :amount="$row->settled_total" currency="AED" /></td>
                        <td class="text-end fw-semibold"><x-money :amount="$row->outstanding" currency="AED" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('gh.driver-cash.show', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('gh.driver_cash.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-body-secondary">{{ __('gh.driver_cash.no_drivers') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($drivers->hasPages())
            <div class="card-footer bg-transparent">{{ $drivers->links() }}</div>
        @endif
    </div>
</div>
@endsection

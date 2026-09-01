@extends('layouts.app')

@section('title', __('portal.'.$module))

@section('content')
    <h1 class="h4 mb-2">{{ __('portal.'.$module) }}</h1>
    <p class="text-body-secondary small mb-3">{{ __('portal.module_home_text') }}</p>

    @include('valuation.sheets._month-stats', ['stats' => $stats])

    @if (($module ?? '') === 'product' && ! empty($lowStockAlert))
        @include('gross.partials.low-stock-alert-card', $lowStockAlert)
    @endif

    @if (auth()->user()->isManager())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('valuation.month_sheets') }}</div>
            <div class="card-body">
                @if ($monthSheets->isEmpty())
                    <x-empty-state :title="__('valuation.empty')" :text="__('valuation.empty_text')" icon="bi-clipboard-data" />
                @else
                    @include('valuation.sheets._list-table', [
                        'sheets' => $monthSheets,
                        'module' => $module,
                        'showPerson' => true,
                        'toolbarId' => 'module-home-month-sheets',
                        'currency' => $stats['currency'] ?? 'EGP',
                    ])
                @endif
            </div>
        </div>
    @endif

    <a class="btn btn-primary" href="{{ route('valuation.sheets.index', $module) }}">
        <i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>{{ __('nav.valuation') }}
    </a>
@endsection

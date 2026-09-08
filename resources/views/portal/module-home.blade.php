@extends('layouts.app')

@section('title', __('portal.'.$module))

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('portal.'.$module) }}</h1>
        <p class="text-body-secondary mb-1">{{ __('portal.module_home_text') }}</p>
        <p class="small text-body-secondary mb-0">
            {{ __('valuation.stats.title') }}
            <span class="num-ltr">· {{ $stats['month_label'] ?? '' }}</span>
        </p>
    </div>

    @include('valuation.sheets._month-stats', ['stats' => $stats, 'compactTitle' => true])

    @if (($module ?? '') === 'product' && ! empty($expenseStats))
        @include('product-expenses._dashboard', ['expenseStats' => $expenseStats])
    @endif

    @if (($module ?? '') === 'product' && ! empty($lowStockAlert))
        @include('gross.partials.low-stock-alert-card', $lowStockAlert)
    @endif

    @if (auth()->user()->isManager())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent">{{ __('valuation.month_sheets') }}</div>
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

    {{-- Spacer so fixed FAB does not cover content --}}
    <div class="pb-5 mb-4" aria-hidden="true"></div>

    <a class="btn btn-primary hp-fab" href="{{ route('valuation.sheets.index', $module) }}">
        <i class="bi bi-clipboard-data" aria-hidden="true"></i>
        <span>{{ __('nav.valuation') }}</span>
    </a>
@endsection

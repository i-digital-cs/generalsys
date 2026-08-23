@extends('layouts.app')

@section('title', __('valuation.title'))

@section('content')
    @php
        $isManager = auth()->user()->isManager();
    @endphp
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('portal.'.$module) }} — {{ __('valuation.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('valuation.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($isManager)
                <a class="btn btn-outline-primary" href="{{ route('valuation.assignments.index') }}">
                    <i class="bi bi-people me-1" aria-hidden="true"></i>{{ __('valuation.assign') }}
                </a>
            @endif
            @if ($canFill)
                <a class="btn btn-primary" href="{{ route('valuation.sheets.form', ['module' => $module, 'date' => now()->toDateString()]) }}">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('valuation.fill_today') }}
                </a>
            @endif
        </div>
    </div>

    @include('valuation.sheets._month-stats', ['stats' => $stats])

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('valuation.list') }}</div>
        <div class="card-body">
            @if ($sheets->isEmpty())
                <x-empty-state :title="__('valuation.empty')" :text="__('valuation.empty_text')" icon="bi-clipboard-data" />
            @else
                @include('valuation.sheets._list-table', [
                    'sheets' => $sheets,
                    'module' => $module,
                    'showPerson' => $isManager,
                    'toolbarId' => 'valuation-sheets-list',
                    'currency' => $stats['currency'] ?? 'EGP',
                ])
                <div class="mt-3">
                    {{ $sheets->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

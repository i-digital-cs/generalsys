@extends('layouts.app')

@section('title', __('valuation.title'))

@section('content')
    @php
        $in = $sheet->input;
        $out = $sheet->result;
        $name = app()->getLocale() === 'ar' ? $sheet->user->name_ar : $sheet->user->name_en;
    @endphp
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('portal.'.$module) }} — {{ $name }}</h1>
        <div>
            @if (auth()->id() === $sheet->user_id)
                <a class="btn btn-primary" href="{{ route('valuation.sheets.form', ['module' => $module, 'date' => $sheet->date->toDateString()]) }}">{{ __('valuation.edit') }}</a>
            @endif
            <a class="btn btn-outline-primary" href="{{ route('valuation.sheets.export.pdf', ['module' => $module, 'sheet' => $sheet]) }}">{{ __('valuation.export_pdf') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('valuation.sheets.index', $module) }}">{{ __('valuation.back') }}</a>
        </div>
    </div>

    <p class="text-body-secondary">
        <span class="num-ltr">{{ $sheet->date->toDateString() }}</span>
        · {{ __('valuation.statuses.'.$sheet->status) }}
    </p>

    <div class="row g-3 mb-3">
        @php
            $kpiKey = $out['kpi_label'] ?? 'loss';
            $cashKey = $out['cashflow_label'] ?? 'negative';
            $earnKey = $out['earnings_label'] ?? 'low';
            $tone = fn (string $key) => \App\Services\Valuation\ValuationCalculator::statusTone($key);
        @endphp
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.fields.actual_amount_total')" :value="number_format((float) ($out['actual_amount_total'] ?? 0), 2)" icon="bi-cash" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.kpi')" icon="bi-flag" :color="$tone($kpiKey)">
                <span @class(['badge', 'text-bg-'.$tone($kpiKey), 'text-dark' => $tone($kpiKey) === 'warning'])>
                    {{ __('valuation.labels.'.$kpiKey) }}
                </span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.cashflow')" icon="bi-activity" :color="$tone($cashKey)">
                <span @class(['badge', 'text-bg-'.$tone($cashKey), 'text-dark' => $tone($cashKey) === 'warning'])>
                    {{ __('valuation.labels.'.$cashKey) }}
                </span>
            </x-stat-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card :title="__('valuation.earnings')" icon="bi-graph-up" :color="$tone($earnKey)">
                <span @class(['badge', 'text-bg-'.$tone($earnKey), 'text-dark' => $tone($earnKey) === 'warning'])>
                    {{ __('valuation.labels.'.$earnKey) }}
                </span>
            </x-stat-card>
        </div>
    </div>

    <div class="row g-3">
        @include('valuation.sheets._body', ['editable' => false, 'module' => $module, 'input' => $in, 'result' => $out])
    </div>
@endsection

@extends('layouts.app')

@section('title', __('valuation.title'))

@section('content')
    @php
        $isManager = auth()->user()->isManager();
        $colspan = $isManager ? 6 : 5;
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
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="valuation-sheets-list"
                        :search-placeholder="__('valuation.search_placeholder')"
                        :filters="[
                            [
                                'name' => 'status',
                                'label' => __('valuation.status'),
                                'options' => [
                                    'DRAFT' => __('valuation.statuses.DRAFT'),
                                    'SUBMITTED' => __('valuation.statuses.SUBMITTED'),
                                ],
                            ],
                            [
                                'name' => 'kpi',
                                'label' => __('valuation.kpi'),
                                'options' => [
                                    'loss' => __('valuation.labels.loss'),
                                    'acceptable' => __('valuation.labels.acceptable'),
                                    'gain' => __('valuation.labels.gain'),
                                    'above' => __('valuation.labels.above'),
                                ],
                            ],
                        ]"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-num">{{ __('valuation.date') }}</th>
                                    @if ($isManager)
                                        <th scope="col">{{ __('valuation.person') }}</th>
                                    @endif
                                    <th scope="col">{{ __('valuation.status') }}</th>
                                    <th scope="col">{{ __('valuation.kpi') }}</th>
                                    <th scope="col" class="col-num">{{ __('valuation.fields.actual_amount_total') }}</th>
                                    <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sheets as $row)
                                    @php
                                        $kpi = $row->result['kpi_label'] ?? null;
                                        $amount = (float) ($row->result['actual_amount_total'] ?? 0);
                                        $person = $row->user
                                            ? (app()->getLocale() === 'ar' ? $row->user->name_ar : $row->user->name_en)
                                            : '';
                                        $filterText = trim($row->date->toDateString().' '.$person.' '.($row->user?->name_ar ?? '').' '.($row->user?->name_en ?? '').' '.__('valuation.statuses.'.$row->status).' '.($kpi ? __('valuation.labels.'.$kpi) : ''));
                                    @endphp
                                    <tr
                                        data-filter-text="{{ $filterText }}"
                                        data-filter-status="{{ $row->status }}"
                                        data-filter-kpi="{{ $kpi ?? '' }}"
                                    >
                                        <td class="col-num text-nowrap">
                                            <span class="num-ltr">{{ $row->date->toDateString() }}</span>
                                        </td>
                                        @if ($isManager)
                                            <td class="fw-semibold">{{ $person }}</td>
                                        @endif
                                        <td>
                                            <span @class([
                                                'badge',
                                                'text-bg-secondary' => $row->status === 'DRAFT',
                                                'text-bg-success' => $row->status === 'SUBMITTED',
                                            ])>
                                                {{ __('valuation.statuses.'.$row->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($kpi)
                                                <span @class([
                                                    'badge',
                                                    'text-bg-danger' => $kpi === 'loss',
                                                    'text-bg-warning' => $kpi === 'acceptable',
                                                    'text-bg-success' => $kpi === 'gain',
                                                    'text-bg-primary' => $kpi === 'above',
                                                ])>
                                                    {{ __('valuation.labels.'.$kpi) }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="col-num">
                                            <span class="num-ltr"><x-money :amount="$amount" :currency="$stats['currency'] ?? 'EGP'" /></span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('valuation.sheets.show', ['module' => $module, 'sheet' => $row]) }}">{{ __('valuation.view') }}</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('valuation.sheets.export.pdf', ['module' => $module, 'sheet' => $row]) }}">{{ __('valuation.export_pdf') }}</a>
                                            @if (auth()->id() === $row->user_id)
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('valuation.sheets.form', ['module' => $module, 'date' => $row->date->toDateString()]) }}">{{ __('valuation.edit') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="{{ $colspan }}" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3">
                    {{ $sheets->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

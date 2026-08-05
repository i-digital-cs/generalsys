@extends('layouts.app')

@section('title', __('reports.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('reports.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('reports.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.annual') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="user_id" class="form-label">{{ __('reports.employee') }}</label>
                    <select id="user_id" name="user_id" class="form-select" required>
                        <option value="">{{ __('general.none') }}</option>
                        @foreach ($employees as $emp)
                            @php
                                $label = app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en;
                            @endphp
                            <option value="{{ $emp->id }}" @selected((int) $userId === $emp->id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="year" class="form-label">{{ __('reports.year') }}</label>
                    <select id="year" name="year" class="form-select">
                        @for ($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('reports.open') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($report === null)
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <x-empty-state
                    :title="__('reports.title')"
                    :text="__('reports.select_employee')"
                    icon="bi-bar-chart"
                />
            </div>
        </div>
    @else
        @php
            $employee = $report['employee'];
            $name = app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en;
            $currency = $employee->currency ?: 'EGP';
            $contract = $report['contract'];
            $vacation = $report['vacation'];
        @endphp

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('reports.contract') }}</div>
                    <div class="card-body">
                        <p class="fw-semibold mb-2">{{ $name }}</p>
                        @if ($contract === null)
                            <p class="text-body-secondary small mb-0">{{ __('reports.no_contract') }}</p>
                        @else
                            <dl class="row small mb-0">
                                <dt class="col-5">{{ __('reports.hire_date') }}</dt>
                                <dd class="col-7">{{ $contract->hire_date?->translatedFormat('d M Y') ?? __('reports.empty_cell') }}</dd>
                                <dt class="col-5">{{ __('reports.contract_start') }}</dt>
                                <dd class="col-7">{{ $contract->contract_start?->translatedFormat('d M Y') ?? __('reports.empty_cell') }}</dd>
                                <dt class="col-5">{{ __('reports.contract_end') }}</dt>
                                <dd class="col-7">{{ $contract->contract_end?->translatedFormat('d M Y') ?? __('reports.empty_cell') }}</dd>
                                <dt class="col-5">{{ __('reports.contract_type') }}</dt>
                                <dd class="col-7">{{ $contract->contract_type ?: __('reports.empty_cell') }}</dd>
                            </dl>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('reports.vacation') }}</div>
                    <div class="card-body">
                        @if ($vacation === null)
                            <p class="text-body-secondary small mb-0">{{ __('reports.no_vacation') }}</p>
                        @else
                            <div class="text-body-secondary small">{{ __('reports.vacation_remaining') }}</div>
                            <div class="fs-4" dir="ltr">{{ number_format((float) $vacation->remaining_days, 1) }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('reports.year_average') }}</div>
                    <div class="card-body">
                        <p class="small text-body-secondary mb-2">{{ __('reports.year_average_hint') }}</p>
                        @if ($report['evaluation_year_average'] === null)
                            <p class="text-body-secondary small mb-0">{{ __('reports.no_evaluations') }}</p>
                        @else
                            <div class="fs-4 fw-semibold" dir="ltr" data-testid="year-average">
                                {{ number_format($report['evaluation_year_average'], 2) }}%
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">{{ __('reports.grid') }} — {{ $year }}</div>
            <div class="card-body">
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="annual-report"
                        :search-placeholder="__('reports.month')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('reports.status'),
                            'options' => [
                                'DRAFT' => __('payroll.status.DRAFT'),
                                'APPROVED' => __('payroll.status.APPROVED'),
                                'PAID' => __('payroll.status.PAID'),
                            ],
                        ]]"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('reports.month') }}</th>
                                    <th scope="col" class="col-num">{{ __('reports.evaluation') }}</th>
                                    <th scope="col" class="col-num">{{ __('reports.penalties') }}</th>
                                    <th scope="col" class="col-num">{{ __('reports.bonuses') }}</th>
                                    <th scope="col" class="col-num">{{ __('reports.deductions') }}</th>
                                    <th scope="col" class="col-num">{{ __('reports.net') }}</th>
                                    <th scope="col">{{ __('reports.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['months'] as $row)
                                    @php
                                        $monthLabel = \Carbon\Carbon::create($year, $row['month'], 1)->translatedFormat('F');
                                        $monthLabelEn = \Carbon\Carbon::create($year, $row['month'], 1)->locale('en')->translatedFormat('F');
                                        $monthLabelAr = \Carbon\Carbon::create($year, $row['month'], 1)->locale('ar')->translatedFormat('F');
                                    @endphp
                                    <tr
                                        data-filter-text="{{ $monthLabel }} {{ $monthLabelEn }} {{ $monthLabelAr }}"
                                        data-filter-status="{{ $row['payroll_status'] ?? '' }}"
                                    >
                                        <td class="fw-semibold">{{ $monthLabel }}</td>
                                        <td class="col-num">
                                            <span class="num-ltr">
                                                {{ $row['evaluation'] !== null ? number_format($row['evaluation'], 2).'%' : __('reports.empty_cell') }}
                                            </span>
                                        </td>
                                        <td class="col-num">
                                            <span class="num-ltr"><x-money :amount="$row['penalties']" :currency="$currency" /></span>
                                        </td>
                                        <td class="col-num">
                                            <span class="num-ltr"><x-money :amount="$row['bonuses']" :currency="$currency" /></span>
                                        </td>
                                        <td class="col-num">
                                            <span class="num-ltr"><x-money :amount="$row['deductions']" :currency="$currency" /></span>
                                        </td>
                                        <td class="col-num fw-semibold">
                                            <span class="num-ltr">
                                                @if ($row['net_pay'] !== null)
                                                    <x-money :amount="$row['net_pay']" :currency="$currency" />
                                                @else
                                                    {{ __('reports.empty_cell') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            @if ($row['payroll_status'])
                                                {{ __('payroll.status.'.$row['payroll_status']) }}
                                            @else
                                                {{ __('reports.empty_cell') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="7" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

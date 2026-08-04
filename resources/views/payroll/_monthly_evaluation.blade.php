{{-- Salaries.xlsx-style monthly evaluation: 5 averages → monthly total → year total. --}}
@php
    /** @var array<string, float>|null $scores */
    $scores = $scores ?? null;
    /** @var string|null $evaluationText */
    $evaluationText = $evaluationText ?? null;
    /** @var float|null $yearAverage */
    $yearAverage = $yearAverage ?? null;
@endphp

@if ($scores !== null)
    <div class="card border-0 shadow-sm mb-3 h-100">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0">{{ __('payroll.evaluation.title') }}</h2>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('payroll.evaluation.hint') }}</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.performance') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['performance_score'], 2) }}%</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.behavior') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['behavior_score'], 2) }}%</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.productivity') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['productivity_score'], 2) }}%</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.interest_return') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['interest_return_score'], 2) }}%</td>
                        </tr>
                        <tr>
                            <th scope="row" class="ps-3">{{ __('evaluation.fields.attendance') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['attendance_score'], 2) }}%</td>
                        </tr>
                        <tr class="table-light fw-semibold">
                            <th scope="row" class="ps-3">{{ __('payroll.evaluation.monthly_total') }}</th>
                            <td class="text-end pe-3" dir="ltr">{{ number_format((float) $scores['total_score'], 2) }}%</td>
                        </tr>
                        <tr class="fw-semibold">
                            <th scope="row" class="ps-3">{{ __('payroll.evaluation.year_total') }}</th>
                            <td class="text-end pe-3" dir="ltr">
                                @if ($yearAverage !== null)
                                    {{ number_format((float) $yearAverage, 2) }}%
                                @else
                                    {{ __('general.not_available') }}
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if (filled($evaluationText))
                <div class="border-top px-3 py-3">
                    <div class="text-body-secondary small mb-1">{{ __('payroll.evaluation.text') }}</div>
                    <p class="mb-0">{{ $evaluationText }}</p>
                </div>
            @endif
        </div>
    </div>
@endif

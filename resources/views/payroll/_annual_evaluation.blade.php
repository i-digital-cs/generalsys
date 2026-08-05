{{-- Annual evaluation strip (Excel التقرير السنوي — التقييم الشهري + L33). --}}
@php
    /** @var array<int, float|null>|null $monthlyTotals */
    $monthlyTotals = $monthlyTotals ?? null;
    /** @var float|null $yearAverage */
    $yearAverage = $yearAverage ?? null;
@endphp

@if (is_array($monthlyTotals))
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0">{{ __('payroll.evaluation.annual_strip_title') }}</h2>
            <p class="small text-body-secondary mb-0 mt-1">{{ __('payroll.evaluation.year_hint') }}</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hr table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('payroll.evaluation.month_col') }}</th>
                            @for ($m = 1; $m <= 12; $m++)
                                <th scope="col" class="col-num">{{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('M') }}</th>
                            @endfor
                            <th scope="col" class="col-num">{{ __('payroll.evaluation.year_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">{{ __('payroll.evaluation.monthly_total') }}</th>
                            @for ($m = 1; $m <= 12; $m++)
                                <td class="col-num">
                                    <span class="num-ltr">
                                        @if (($monthlyTotals[$m] ?? null) !== null)
                                            {{ number_format((float) $monthlyTotals[$m], 2) }}%
                                        @else
                                            —
                                        @endif
                                    </span>
                                </td>
                            @endfor
                            <td class="col-num fw-semibold">
                                <span class="num-ltr">
                                    @if ($yearAverage !== null)
                                        {{ number_format((float) $yearAverage, 2) }}%
                                    @else
                                        —
                                    @endif
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

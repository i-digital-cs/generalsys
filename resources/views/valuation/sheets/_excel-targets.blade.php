@php
    $score = fn (string $key) => old('input.'.$key, $input[$key] ?? 0);
    $out = $result ?? [];
    $editable = $editable ?? false;
@endphp
<div class="valuation-excel" data-valuation-targets
     data-labels="{{ e(json_encode([
         'loss' => __('valuation.labels.loss'),
         'acceptable' => __('valuation.labels.acceptable'),
         'gain' => __('valuation.labels.gain'),
         'above' => __('valuation.labels.above'),
         'negative' => __('valuation.labels.negative'),
         'positive' => __('valuation.labels.positive'),
         'safe' => __('valuation.labels.safe'),
         'high' => __('valuation.labels.high'),
         'low' => __('valuation.labels.low'),
     ], JSON_UNESCAPED_UNICODE)) }}">

    <div class="valuation-excel-head">
        <span>{{ __('valuation.sections.valuation_methods') }}</span>
    </div>
    <div class="table-responsive mb-3">
        <table class="table table-hr valuation-excel-table mb-0">
            <tbody>
                <tr>
                    <th scope="row">{{ __('valuation.fields.general_target') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_count_total">{{ __('valuation.fields.general_target') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_count_total]" id="target_count_total" value="{{ $score('target_count_total') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_count_total') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.execution') }}</td>
                    <td class="col-num"><span class="num-ltr" data-val="actual_count_total">{{ $out['actual_count_total'] ?? 0 }}</span></td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['count_ratio'] ?? 0" data-pct="count_ratio" /></td>
                </tr>
                <tr>
                    <th scope="row">{{ __('valuation.fields.target_b2b') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_b2b_count">{{ __('valuation.fields.target_b2b_count') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_b2b_count]" id="target_b2b_count" value="{{ $score('target_b2b_count') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_b2b_count') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.b2b') }}</td>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="actual_b2b_count">{{ __('valuation.fields.actual_b2b_count') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[actual_b2b_count]" id="actual_b2b_count" value="{{ $score('actual_b2b_count') }}">
                        @else
                            <span class="num-ltr">{{ $score('actual_b2b_count') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['count_b2b_ratio'] ?? 0" data-pct="count_b2b_ratio" /></td>
                </tr>
                <tr>
                    <th scope="row">{{ __('valuation.fields.target_b2c') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_b2c_count">{{ __('valuation.fields.target_b2c_count') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_b2c_count]" id="target_b2c_count" value="{{ $score('target_b2c_count') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_b2c_count') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.b2c') }}</td>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="actual_b2c_count">{{ __('valuation.fields.actual_b2c_count') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[actual_b2c_count]" id="actual_b2c_count" value="{{ $score('actual_b2c_count') }}">
                        @else
                            <span class="num-ltr">{{ $score('actual_b2c_count') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['count_b2c_ratio'] ?? 0" data-pct="count_b2c_ratio" /></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="valuation-excel-head">
        <span>{{ __('valuation.sections.financial_affairs') }}</span>
        <span class="small fw-normal">{{ __('valuation.fields.comparable') }}</span>
    </div>
    <p class="small text-body-secondary px-2 mb-1">{{ __('valuation.financial_subtitle') }}</p>
    <div class="table-responsive mb-3">
        <table class="table table-hr valuation-excel-table mb-0">
            <tbody>
                <tr>
                    <th scope="row">{{ __('valuation.fields.general_target') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_amount_total">{{ __('valuation.fields.general_target') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_amount_total]" id="target_amount_total" value="{{ $score('target_amount_total') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_amount_total') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.execution') }}</td>
                    <td class="col-num"><span class="num-ltr" data-val="actual_amount_total">{{ $out['actual_amount_total'] ?? 0 }}</span></td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['amount_ratio'] ?? 0" data-pct="amount_ratio" /></td>
                </tr>
                <tr>
                    <th scope="row">{{ __('valuation.fields.target_b2b') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_b2b_amount">{{ __('valuation.fields.target_b2b_amount') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_b2b_amount]" id="target_b2b_amount" value="{{ $score('target_b2b_amount') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_b2b_amount') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.b2b') }}</td>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="actual_b2b_amount">{{ __('valuation.fields.actual_b2b_amount') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[actual_b2b_amount]" id="actual_b2b_amount" value="{{ $score('actual_b2b_amount') }}">
                        @else
                            <span class="num-ltr">{{ $score('actual_b2b_amount') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['amount_b2b_ratio'] ?? 0" data-pct="amount_b2b_ratio" /></td>
                </tr>
                <tr>
                    <th scope="row">{{ __('valuation.fields.target_b2c') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="target_b2c_amount">{{ __('valuation.fields.target_b2c_amount') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[target_b2c_amount]" id="target_b2c_amount" value="{{ $score('target_b2c_amount') }}">
                        @else
                            <span class="num-ltr">{{ $score('target_b2c_amount') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.b2c') }}</td>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="actual_b2c_amount">{{ __('valuation.fields.actual_b2c_amount') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[actual_b2c_amount]" id="actual_b2c_amount" value="{{ $score('actual_b2c_amount') }}">
                        @else
                            <span class="num-ltr">{{ $score('actual_b2c_amount') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.percent') }}</td>
                    <td class="col-num"><x-percent :value="$out['amount_b2c_ratio'] ?? 0" data-pct="amount_b2c_ratio" /></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="valuation-excel-head">
        <span>{{ __('valuation.sections.kpi_vs_target') }}</span>
    </div>
    <div class="table-responsive mb-0">
        <table class="table table-hr valuation-excel-table mb-0">
            <tbody>
                <tr>
                    <th scope="row">{{ __('valuation.fields.general_target') }}</th>
                    <td colspan="2">
                        @if ($editable)
                            <label class="visually-hidden" for="kpi_base">{{ __('valuation.fields.kpi_base') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[kpi_base]" id="kpi_base" value="{{ $score('kpi_base') }}">
                        @else
                            <span class="num-ltr">{{ $score('kpi_base') }}</span>
                        @endif
                    </td>
                    <td>{{ __('valuation.fields.kpis') }}</td>
                    <td class="col-num" colspan="2"><x-percent :value="$out['kpi_ratio'] ?? 0" data-pct="kpi_ratio" /></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div @class([\App\Services\Valuation\ValuationCalculator::statusStripClass($out['kpi_label'] ?? 'loss')]) data-kpi-label data-status-kind="kpi">{{ isset($out['kpi_label']) ? __('valuation.labels.'.$out['kpi_label']) : '' }}</div>

    <div class="valuation-excel-head mt-3">
        <span>{{ __('valuation.sections.cashflow_details') }}</span>
    </div>
    <p class="small text-body-secondary px-2 mb-1">{{ __('valuation.cashflow_subtitle') }}</p>
    <div class="table-responsive mb-0">
        <table class="table table-hr valuation-excel-table mb-0">
            <tbody>
                <tr>
                    <th scope="row">{{ __('valuation.fields.general_target') }}</th>
                    <td>
                        @if ($editable)
                            <label class="visually-hidden" for="cashflow_base">{{ __('valuation.fields.cashflow_base') }}</label>
                            <input class="form-control form-control-sm" dir="ltr" name="input[cashflow_base]" id="cashflow_base" value="{{ $score('cashflow_base') }}">
                        @else
                            <span class="num-ltr">{{ $score('cashflow_base') }}</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div @class([\App\Services\Valuation\ValuationCalculator::statusStripClass($out['cashflow_label'] ?? 'negative')]) data-cashflow-label data-status-kind="cashflow">{{ isset($out['cashflow_label']) ? __('valuation.labels.'.$out['cashflow_label']) : '' }}</div>

    <div class="valuation-excel-head mt-3">
        <span>{{ __('valuation.sections.earnings_method') }}</span>
    </div>
    <p class="small text-body-secondary px-2 mb-1">{{ __('valuation.earnings_subtitle') }}</p>
    <div @class([\App\Services\Valuation\ValuationCalculator::statusStripClass($out['earnings_label'] ?? 'low')]) data-earnings-label data-status-kind="earnings">{{ isset($out['earnings_label']) ? __('valuation.labels.'.$out['earnings_label']) : '' }}</div>
</div>

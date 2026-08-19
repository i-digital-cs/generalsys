@extends('layouts.app')

@section('title', __('valuation.fill'))

@section('content')
    <h1 class="h4 mb-3">{{ __('portal.'.$module) }} — {{ __('valuation.fill') }}</h1>

    <form method="POST" action="{{ route('valuation.sheets.store', $module) }}" class="row g-3" data-valuation-form>
        @csrf
        <div class="col-12 col-md-4">
            <label class="form-label" for="date">{{ __('valuation.date') }}</label>
            <input type="date" name="date" id="date" class="form-control" dir="ltr"
                   value="{{ old('date', $date) }}" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="status">{{ __('valuation.status') }}</label>
            <select name="status" id="status" class="form-select">
                <option value="DRAFT" @selected(old('status', $sheet->status) === 'DRAFT')>{{ __('valuation.statuses.DRAFT') }}</option>
                <option value="SUBMITTED" @selected(old('status', $sheet->status) === 'SUBMITTED')>{{ __('valuation.statuses.SUBMITTED') }}</option>
            </select>
        </div>

        @include('valuation.sheets._body', ['editable' => true, 'input' => $input, 'result' => $result])

        <div class="col-12">
            <button type="submit" class="btn btn-primary">{{ __('valuation.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('valuation.sheets.index', $module) }}">{{ __('general.cancel') }}</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.querySelector('[data-valuation-form]');
            const root = document.querySelector('[data-valuation-targets]');
            if (!form || !root) {
                return;
            }

            const labels = JSON.parse(root.getAttribute('data-labels') || '{}');

            const parseNum = function (el) {
                if (!el) {
                    return 0;
                }
                const value = parseFloat(String(el.value).replace(',', '.'));
                return Number.isFinite(value) ? value : 0;
            };

            const num = function (name) {
                return parseNum(root.querySelector('[name="input[' + name + ']"]'));
            };

            const toPercent = function (value) {
                return value <= 1 ? value * 100 : value;
            };

            const starCount = function (score) {
                const raw = toPercent(score) / 100 * 5;
                return Math.min(5, Math.max(0, Math.floor(raw + 0.5)));
            };

            const paintStars = function (el, score) {
                if (!el) {
                    return;
                }
                const n = starCount(score);
                let html = '';
                for (let i = 1; i <= 5; i++) {
                    html += '<i class="bi ' + (i <= n ? 'bi-star-fill' : 'bi-star') + '"></i>';
                }
                el.innerHTML = html;
            };

            const fmt = function (value) {
                if (Math.abs(value - Math.round(value)) < 1e-9) {
                    return String(Math.round(value));
                }
                return value.toFixed(2);
            };

            const pct = function (numValue, den) {
                if (den === 0) {
                    return '0.00%';
                }
                return ((numValue / den) * 100).toFixed(2) + '%';
            };

            const setText = function (el, text) {
                if (el) {
                    el.textContent = text;
                }
            };

            const kpiLabel = function (ratio) {
                if (ratio <= 0.70) return labels.loss || '';
                if (ratio <= 0.80) return labels.acceptable || '';
                if (ratio <= 1.00) return labels.gain || '';
                return labels.above || '';
            };

            const cashLabel = function (actual, base) {
                if (actual < base) return labels.negative || '';
                if (actual > base) return labels.positive || '';
                return labels.safe || '';
            };

            const refreshStars = function () {
                form.querySelectorAll('[data-star-source]').forEach(function (input) {
                    const out = input.parentElement.querySelector('[data-star-out]')
                        || input.nextElementSibling;
                    paintStars(out, parseNum(input));
                });

                const channels = form.querySelectorAll('[data-sales-channel]');
                if (channels.length === 0) {
                    return;
                }
                let sum = 0;
                channels.forEach(function (el) {
                    sum += toPercent(parseNum(el));
                });
                const avg = sum / channels.length;
                setText(form.querySelector('[data-sales-avg]'), avg.toFixed(2) + '%');
                paintStars(form.querySelector('[data-sales-stars]'), avg);
            };

            const refreshTargets = function () {
                const tCount = num('target_count_total');
                const tAmount = num('target_amount_total');
                const tB2bC = num('target_b2b_count');
                const tB2cC = num('target_b2c_count');
                const aB2bC = num('actual_b2b_count');
                const aB2cC = num('actual_b2c_count');
                const tB2bA = num('target_b2b_amount');
                const tB2cA = num('target_b2c_amount');
                const aB2bA = num('actual_b2b_amount');
                const aB2cA = num('actual_b2c_amount');
                const kpiBase = num('kpi_base');
                const cashBase = num('cashflow_base');
                const aCount = aB2bC + aB2cC;
                const aAmount = aB2bA + aB2cA;
                const kpiRatio = kpiBase === 0 ? 0 : aAmount / kpiBase;

                setText(root.querySelector('[data-val="actual_count_total"]'), fmt(aCount));
                setText(root.querySelector('[data-val="actual_amount_total"]'), fmt(aAmount));
                setText(root.querySelector('[data-pct="count_ratio"]'), pct(aCount, tCount));
                setText(root.querySelector('[data-pct="count_b2b_ratio"]'), pct(aB2bC, tB2bC));
                setText(root.querySelector('[data-pct="count_b2c_ratio"]'), pct(aB2cC, tB2cC));
                setText(root.querySelector('[data-pct="amount_ratio"]'), pct(aAmount, tAmount));
                setText(root.querySelector('[data-pct="amount_b2b_ratio"]'), pct(aB2bA, tB2bA));
                setText(root.querySelector('[data-pct="amount_b2c_ratio"]'), pct(aB2cA, tB2cA));
                setText(root.querySelector('[data-pct="kpi_ratio"]'), pct(aAmount, kpiBase));
                setText(root.querySelector('[data-kpi-label]'), kpiLabel(kpiRatio));
                setText(root.querySelector('[data-cashflow-label]'), cashLabel(aAmount, cashBase));
                setText(root.querySelector('[data-earnings-label]'), aAmount >= cashBase ? (labels.high || '') : (labels.low || ''));
            };

            const refresh = function () {
                refreshTargets();
                refreshStars();
            };

            form.addEventListener('input', refresh);
            refresh();
        })();
    </script>
@endpush

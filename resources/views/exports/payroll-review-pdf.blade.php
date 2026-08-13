@php
    use App\Support\DompdfArabic;
    use App\Support\DompdfFonts;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\PayrollRecord $payroll */
    /** @var array<string, float>|null $scores */
    /** @var string|null $evaluationText */
    /** @var float|null $yearAverage */
    /** @var bool $evaluationSaved */
    /** @var array<string, int|float>|null $attendanceSummary */
    /** @var array{totals: array{planned: float, actual: float, pct: ?float}, days: list}|null $hoursMonth */

    $emp = $payroll->user;
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $name = $emp
        ? ($rtl ? ($emp->name_ar ?: $emp->name_en) : ($emp->name_en ?: $emp->name_ar))
        : __('general.not_available');
    $period = sprintf('%02d/%04d', $payroll->month, $payroll->year);
    $issuedAt = Carbon::now()->locale($locale)->translatedFormat($rtl ? 'd / m / Y' : 'd M Y');
    $docRef = sprintf('PR-%04d%02d-%d', $payroll->year, $payroll->month, $payroll->id);
    $company = config('app.name');
    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $money = static fn ($v) => number_format((float) $v, 2);
    $num = static fn ($v, int $d = 2) => number_format((float) $v, $d);
    $nonzero = static fn (array $rows) => array_filter($rows, static fn ($v) => abs((float) $v) >= 0.005);

    $payroll->loadMissing('adjustments');

    $rowsPay = $nonzero([
        'payroll.fields.base_salary' => $payroll->base_salary,
        'payroll.fields.full_time_pay' => $payroll->full_time_pay,
        'payroll.fields.online_half_pay' => $payroll->online_half_pay,
        'payroll.fields.online_full_pay' => $payroll->online_full_pay,
        'payroll.fields.overtime_pay' => $payroll->overtime_pay,
        'payroll.fields.friday_bonus' => $payroll->friday_bonus,
        'payroll.fields.day_off_bonus' => $payroll->day_off_bonus,
        'payroll.fields.official_holiday_bonus' => $payroll->official_holiday_bonus,
        'payroll.fields.extra_pay' => $payroll->extra_pay,
    ]);

    $rowsDeduct = $nonzero([
        'payroll.fields.late_penalty' => $payroll->late_penalty,
        'payroll.fields.leave_permission_penalty' => $payroll->leave_permission_penalty,
        'payroll.fields.late_permission_penalty' => $payroll->late_permission_penalty,
        'payroll.fields.excused_absence_penalty' => $payroll->excused_absence_penalty,
        'payroll.fields.unexcused_absence_penalty' => $payroll->unexcused_absence_penalty,
        'payroll.fields.forced_penalty_surcharge' => $payroll->forced_penalty_surcharge,
        'payroll.fields.hours_deduction_amount' => $payroll->hours_deduction_amount,
    ]);

    $earningAdj = [];
    $deductAdj = [];
    $hasAdvanceAdj = false;
    foreach ($payroll->adjustments as $adj) {
        $amount = abs((float) $adj->amount);
        if ($amount < 0.005) {
            continue;
        }
        $label = $t('payroll.adjustment_types.'.$adj->type);
        $note = trim((string) ($adj->note ?? ''));
        if ($note !== '') {
            $label .= ' — '.$g($note);
        }
        if (in_array($adj->type, [
            \App\Models\PayrollAdjustment::TYPE_DEPARTMENT_COMMISSION,
            \App\Models\PayrollAdjustment::TYPE_SALES_COMMISSION,
            \App\Models\PayrollAdjustment::TYPE_PROFIT_COMMISSION,
            \App\Models\PayrollAdjustment::TYPE_BONUS,
        ], true)) {
            $earningAdj[] = ['label' => $label, 'amount' => $amount];
        } elseif (in_array($adj->type, [
            \App\Models\PayrollAdjustment::TYPE_ADVANCE,
            \App\Models\PayrollAdjustment::TYPE_OTHER_DEDUCTION,
        ], true)) {
            if ($adj->type === \App\Models\PayrollAdjustment::TYPE_ADVANCE) {
                $hasAdvanceAdj = true;
            }
            $deductAdj[] = ['label' => $label, 'amount' => $amount];
        }
    }
    if (! $hasAdvanceAdj && (float) $payroll->advance_deductions >= 0.005) {
        $deductAdj[] = [
            'label' => $t('payroll.fields.advance_deductions'),
            'amount' => (float) $payroll->advance_deductions,
        ];
    }

    $draftCounts = [
        'payroll.fields.full_time_days' => (int) $payroll->full_time_days,
        'payroll.fields.online_half_days' => (int) $payroll->online_half_days,
        'payroll.fields.online_full_days' => (int) $payroll->online_full_days,
        'payroll.fields.rest_days' => (int) $payroll->rest_days,
        'payroll.fields.friday_work_days' => (int) $payroll->friday_work_days,
        'payroll.fields.day_off_work_days' => (int) $payroll->day_off_work_days,
        'payroll.fields.official_holiday_work_days' => (int) $payroll->official_holiday_work_days,
        'payroll.fields.late_minutes_total' => (int) $payroll->late_minutes_total,
        'payroll.fields.excused_absence_days' => (int) $payroll->excused_absence_days,
        'payroll.fields.unexcused_absence_days' => (int) $payroll->unexcused_absence_days,
        'payroll.fields.hours_deduction' => (float) $payroll->hours_deduction,
        'payroll.fields.extra_days' => (int) $payroll->extra_days,
        'payroll.fields.extra_pay' => (float) $payroll->extra_pay,
        'payroll.fields.overtime_hours' => (float) $payroll->overtime_hours + (float) $payroll->extra_shift_hours,
        'payroll.fields.overtime_multiplier' => (float) $payroll->overtime_multiplier,
    ];

    $attendanceRows = [
        'payroll.fields.full_time_days' => (int) $payroll->full_time_days,
        'payroll.fields.online_half_days' => (int) $payroll->online_half_days,
        'payroll.fields.online_full_days' => (int) $payroll->online_full_days,
        'payroll.fields.rest_days' => (int) $payroll->rest_days,
        'payroll.attendance_summary.vacation_days' => (int) ($attendanceSummary['vacation_days'] ?? 0),
        'payroll.attendance_summary.official_holidays' => (int) ($attendanceSummary['official_holiday_days'] ?? 0),
        'payroll.attendance_summary.excused_absence' => (int) ($attendanceSummary['excused_absence_days'] ?? 0),
        'payroll.attendance_summary.unexcused_absence' => (int) ($attendanceSummary['unexcused_absence_days'] ?? 0),
        'payroll.fields.late_minutes_total' => (int) $payroll->late_minutes_total,
        'payroll.fields.hours_deduction' => (float) $payroll->hours_deduction,
    ];

    $hourGaps = collect($hoursMonth['days'] ?? [])->filter(function (array $day) {
        return ((float) $day['planned_hours'] - (float) $day['actual_hours']) > 0.01;
    })->values();

    $rowHtml = static function (string $label, string $value, bool $rtl): string {
        if ($rtl) {
            return '<td class="amount">'.$value.'</td><th>'.$label.'</th>';
        }

        return '<th>'.$label.'</th><td class="amount">'.$value.'</td>';
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ DompdfArabic::glyphs(__('payroll.export_review_pdf').' '.$period) }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        @font-face {
            font-family: 'Tajawal';
            font-style: normal;
            font-weight: 400;
            src: url('{{ DompdfFonts::regular() }}') format('truetype');
        }
        @font-face {
            font-family: 'Tajawal';
            font-style: normal;
            font-weight: 700;
            src: url('{{ DompdfFonts::bold() }}') format('truetype');
        }
        body {
            font-family: 'Tajawal', DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            direction: ltr;
            margin: 0;
            line-height: 1.3;
        }
        .sheet {
            @if ($rtl) text-align: right; @else text-align: left; @endif
        }
        .header {
            border-bottom: 1.5px solid #1a1a1a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .company { font-size: 13px; font-weight: 700; margin: 0; }
        .doc-title { font-size: 13px; font-weight: 700; margin: 2px 0 0; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .section {
            margin: 10px 0 6px;
            padding: 3px 6px;
            background: #f0f3f7;
            border-{{ $rtl ? 'right' : 'left' }}: 3px solid #0d6efd;
            font-weight: 700;
            font-size: 10px;
        }
        table.kv { width: 100%; border-collapse: collapse; margin: 0 0 6px; }
        table.kv th, table.kv td {
            border-bottom: 1px solid #ddd;
            padding: 3px 5px;
            vertical-align: top;
        }
        table.kv th {
            font-weight: 500;
            @if ($rtl) text-align: right; @else text-align: left; @endif
        }
        table.kv td.amount {
            width: 28%;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            @if ($rtl) text-align: left; @else text-align: right; @endif
        }
        table.days { width: 100%; border-collapse: collapse; margin: 0 0 6px; font-size: 8px; }
        table.days th, table.days td {
            border: 1px solid #ddd;
            padding: 2px 4px;
        }
        table.days th { background: #f7f7f7; font-weight: 700; }
        .totals th, .totals td { font-weight: 700; }
        .net th, .net td { background: #eef6f1; }
        .empty { color: #777; font-size: 8px; padding: 4px 0; }
        .footer {
            margin-top: 10px;
            padding-top: 4px;
            border-top: 1px solid #ccc;
            font-size: 7.5px;
            color: #666;
        }
        .hero {
            margin: 6px 0 8px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            background: #fafafa;
        }
        .hero strong { font-size: 12px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="header">
        <p class="company">{{ $g($company) }}</p>
        <p class="doc-title">{{ $t('payroll.export_review_pdf') }}</p>
        <div class="meta">
            {{ $t('salary.pdf.employee') }}: {{ $g($name) }}
            · {{ $t('salary.pdf.period') }}: {{ $period }}
            · {{ $t('salary.pdf.status') }}: {{ $t('payroll.status.'.$payroll->status) }}
            · {{ $t('salary.pdf.document_no') }}: {{ $docRef }}
            · {{ $t('salary.pdf.issue_date') }}: {{ $issuedAt }}
        </div>
    </div>

    <div class="hero">
        {{ $t('payroll.fields.net') }}:
        <strong>{{ $money($payroll->net_pay) }} {{ $t('general.currencies.'.$payroll->currency) }}</strong>
        · {{ $t('payroll.fields.gross') }}: {{ $money($payroll->gross_pay) }}
        · {{ $t('payroll.fields.deductions') }}: {{ $money($payroll->total_deductions) }}
        · {{ $t('payroll.fields.base_salary') }}: {{ $money($payroll->base_salary) }}
    </div>

    {{-- 1. Pay --}}
    <div class="section">{{ $t('payroll.tabs.pay') }}</div>
    <table class="kv">
        @forelse ($rowsPay as $key => $amount)
            <tr>{!! $rowHtml($t($key), $money($amount), $rtl) !!}</tr>
        @empty
            <tr><td colspan="2" class="empty">{{ $t('payroll.zero_earnings') }}</td></tr>
        @endforelse
        @foreach ($earningAdj as $adj)
            <tr>{!! $rowHtml($adj['label'], $money($adj['amount']), $rtl) !!}</tr>
        @endforeach
        @forelse ($rowsDeduct as $key => $amount)
            <tr>{!! $rowHtml($t($key), $money($amount), $rtl) !!}</tr>
        @empty
            @if ($deductAdj === [])
                <tr><td colspan="2" class="empty">{{ $t('payroll.zero_deductions') }}</td></tr>
            @endif
        @endforelse
        @foreach ($deductAdj as $adj)
            <tr>{!! $rowHtml($adj['label'], $money($adj['amount']), $rtl) !!}</tr>
        @endforeach
        <tr class="totals">{!! $rowHtml($t('payroll.fields.gross'), $money($payroll->gross_pay), $rtl) !!}</tr>
        <tr class="totals">{!! $rowHtml($t('payroll.fields.deductions'), $money($payroll->total_deductions), $rtl) !!}</tr>
        <tr class="net">{!! $rowHtml($t('payroll.fields.net'), $money($payroll->net_pay), $rtl) !!}</tr>
    </table>

    {{-- 2. Edit draft (counts snapshot) --}}
    <div class="section">{{ $t('payroll.tabs.edit') }}</div>
    <table class="kv">
        @foreach ($draftCounts as $key => $value)
            <tr>{!! $rowHtml($t($key), is_float($value) ? $num($value) : (string) $value, $rtl) !!}</tr>
        @endforeach
    </table>

    {{-- 3. Attendance --}}
    <div class="section">{{ $t('payroll.tabs.attendance') }}</div>
    <table class="kv">
        @foreach ($attendanceRows as $key => $value)
            <tr>{!! $rowHtml($t($key), is_float($value) ? $num($value) : (string) $value, $rtl) !!}</tr>
        @endforeach
    </table>

    {{-- 4. Evaluation --}}
    <div class="section">{{ $t('payroll.tabs.evaluation') }}</div>
    @if ($scores !== null)
        <table class="kv">
            <tr>{!! $rowHtml($t('evaluation.fields.performance'), $num($scores['performance_score']).'%', $rtl) !!}</tr>
            <tr>{!! $rowHtml($t('evaluation.fields.behavior'), $num($scores['behavior_score']).'%', $rtl) !!}</tr>
            <tr>{!! $rowHtml($t('evaluation.fields.productivity'), $num($scores['productivity_score']).'%', $rtl) !!}</tr>
            <tr>{!! $rowHtml($t('evaluation.fields.interest_return'), $num($scores['interest_return_score']).'%', $rtl) !!}</tr>
            <tr>{!! $rowHtml($t('evaluation.fields.attendance'), $num($scores['attendance_score']).'%', $rtl) !!}</tr>
            <tr class="totals">{!! $rowHtml($t('payroll.evaluation.monthly_total'), $num($scores['total_score']).'%', $rtl) !!}</tr>
            <tr class="totals">{!! $rowHtml(
                $t('payroll.evaluation.year_total'),
                $yearAverage !== null ? $num($yearAverage).'%' : $g(__('general.not_available')),
                $rtl
            ) !!}</tr>
        </table>
        <p><strong>{{ $t('payroll.evaluation.text') }}</strong></p>
        <p>{{ filled($evaluationText) ? $g($evaluationText) : $t('payroll.evaluation.text_empty') }}</p>
        <p class="empty">
            {{ $evaluationSaved ? $t('payroll.evaluation.saved_badge') : $t('payroll.evaluation.preview_badge') }}
        </p>
    @else
        <p class="empty">{{ $g(__('general.not_available')) }}</p>
    @endif

    {{-- 5. Hours --}}
    <div class="section">{{ $t('payroll.tabs.hours') }}</div>
    @if ($hoursMonth !== null)
        <table class="kv">
            <tr>{!! $rowHtml($t('payroll.hours.planned'), $num($hoursMonth['totals']['planned']), $rtl) !!}</tr>
            <tr>{!! $rowHtml($t('payroll.hours.actual'), $num($hoursMonth['totals']['actual']), $rtl) !!}</tr>
            <tr>{!! $rowHtml(
                $t('payroll.hours.shortfall'),
                $num(max(0, (float) $hoursMonth['totals']['planned'] - (float) $hoursMonth['totals']['actual'])),
                $rtl
            ) !!}</tr>
        </table>
        @if ($hourGaps->isEmpty())
            <p class="empty">{{ $t('payroll.hours.no_gaps') }}</p>
        @else
            <table class="days">
                <thead>
                    <tr>
                        @if ($rtl)
                            <th>{{ $t('payroll.hours.shortfall') }}</th>
                            <th>{{ $t('payroll.hours.actual') }}</th>
                            <th>{{ $t('payroll.hours.planned') }}</th>
                            <th>{{ $t('payroll.hours.shift') }}</th>
                            <th>{{ $t('payroll.hours.date') }}</th>
                        @else
                            <th>{{ $t('payroll.hours.date') }}</th>
                            <th>{{ $t('payroll.hours.shift') }}</th>
                            <th>{{ $t('payroll.hours.planned') }}</th>
                            <th>{{ $t('payroll.hours.actual') }}</th>
                            <th>{{ $t('payroll.hours.shortfall') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hourGaps as $day)
                        @php
                            $short = round((float) $day['planned_hours'] - (float) $day['actual_hours'], 2);
                            $labels = collect($day['planned_intervals'] ?? [])->pluck('label')->filter()->implode(' · ');
                        @endphp
                        <tr>
                            @if ($rtl)
                                <td>{{ $num($short) }}</td>
                                <td>{{ $num($day['actual_hours']) }}</td>
                                <td>{{ $num($day['planned_hours']) }}</td>
                                <td>{{ $g($labels !== '' ? $labels : __('general.not_available')) }}</td>
                                <td>{{ $day['date'] }}</td>
                            @else
                                <td>{{ $day['date'] }}</td>
                                <td>{{ $g($labels !== '' ? $labels : __('general.not_available')) }}</td>
                                <td>{{ $num($day['planned_hours']) }}</td>
                                <td>{{ $num($day['actual_hours']) }}</td>
                                <td>{{ $num($short) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <p class="empty">{{ $g(__('general.not_available')) }}</p>
    @endif

    <div class="footer">
        {{ $g($company) }} · {{ $docRef }} · {{ $issuedAt }}
    </div>
</div>
</body>
</html>

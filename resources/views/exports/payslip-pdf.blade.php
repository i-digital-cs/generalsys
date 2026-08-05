@php
    use App\Support\DompdfArabic;
    use App\Support\DompdfFonts;
    use Illuminate\Support\Carbon;

    $emp = $payroll->user;
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $name = $emp
        ? ($rtl
            ? ($emp->name_ar ?: $emp->name_en)
            : ($emp->name_en ?: $emp->name_ar))
        : __('general.not_available');
    $period = sprintf('%02d/%04d', $payroll->month, $payroll->year);
    $issuedAt = Carbon::now()->locale($locale)->translatedFormat($rtl ? 'd / m / Y' : 'd M Y');
    $docRef = sprintf('PS-%04d%02d-%d', $payroll->year, $payroll->month, $payroll->id);
    $company = config('app.name');
    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $money = static fn ($v) => number_format((float) $v, 2);
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

    // Manual / advance lines (shown under earnings or deductions by type).
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

    // Fallback aggregate when draft has advance_deductions but no ADVANCE adjustment rows.
    if (! $hasAdvanceAdj && (float) $payroll->advance_deductions >= 0.005) {
        $deductAdj[] = [
            'label' => $t('payroll.fields.advance_deductions'),
            'amount' => (float) $payroll->advance_deductions,
        ];
    }

    $hasDeductions = $rowsDeduct !== [] || $deductAdj !== [];
    $hasEarnings = $rowsPay !== [] || $earningAdj !== [];

    $rowsTotal = [
        'payroll.fields.gross' => $payroll->gross_pay,
        'payroll.fields.deductions' => $payroll->total_deductions,
        'payroll.fields.net' => $payroll->net_pay,
    ];

    $metaPairs = [
        [
            ['salary.pdf.employee', $g($name)],
            ['salary.pdf.role', $emp ? $t('employees.roles.'.$emp->role) : $g(__('general.not_available'))],
        ],
        [
            ['salary.pdf.period', $period],
            ['salary.pdf.status', $t('payroll.status.'.$payroll->status)],
        ],
        [
            ['salary.pdf.currency', $t('general.currencies.'.$payroll->currency)],
            ['salary.pdf.document_no', $docRef],
        ],
        [
            ['salary.pdf.issue_date', $issuedAt],
            [null, null],
        ],
    ];

    $rowHtml = static function (string $label, string $amount, bool $rtl): string {
        if ($rtl) {
            return '<td class="amount">'.$amount.'</td><th>'.$label.'</th>';
        }

        return '<th>'.$label.'</th><td class="amount">'.$amount.'</td>';
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ DompdfArabic::glyphs(__('salary.breakdown').' '.$period) }}</title>
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
            font-weight: 500;
            src: url('{{ DompdfFonts::medium() }}') format('truetype');
        }
        @font-face {
            font-family: 'Tajawal';
            font-style: normal;
            font-weight: 700;
            src: url('{{ DompdfFonts::bold() }}') format('truetype');
        }

        body {
            font-family: 'Tajawal', DejaVu Sans, sans-serif;
            font-size: 9.5px;
            color: #1a1a1a;
            direction: ltr;
            margin: 0;
            line-height: 1.25;
        }
        .sheet {
            @if ($rtl)
                text-align: right;
            @else
                text-align: left;
            @endif
        }
        .header {
            border-bottom: 1.5px solid #1a1a1a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .company {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
        }
        .doc-title {
            font-size: 14px;
            font-weight: 700;
            margin: 2px 0 0;
        }
        .doc-sub {
            color: #555;
            font-size: 8.5px;
            margin: 1px 0 0;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            margin: 8px 0 3px;
            padding-bottom: 1px;
            border-bottom: 1px solid #bbb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        th, td {
            border: 1px solid #bdbdbd;
            padding: 3px 6px;
            vertical-align: middle;
        }
        th {
            background: #f3f3f3;
            font-weight: 500;
            @if ($rtl)
                text-align: right;
            @else
                text-align: left;
            @endif
        }
        td.amount {
            direction: ltr;
            unicode-bidi: embed;
            white-space: nowrap;
            @if ($rtl)
                text-align: left;
            @else
                text-align: right;
            @endif
        }
        table.meta th { width: 18%; background: #fafafa; }
        table.meta td {
            width: 32%;
            @if ($rtl) text-align: right; @else text-align: left; @endif
        }
        table.lines th { width: 65%; }
        table.lines td.amount { width: 35%; }
        .cols { width: 100%; border-collapse: collapse; margin: 0; }
        .cols > tbody > tr > td {
            border: none;
            width: 50%;
            vertical-align: top;
            padding: 0;
            @if ($rtl)
                padding-left: 4px;
            @else
                padding-right: 4px;
            @endif
        }
        .cols > tbody > tr > td + td {
            @if ($rtl)
                padding-left: 0;
                padding-right: 4px;
            @else
                padding-right: 0;
                padding-left: 4px;
            @endif
        }
        .empty-hint {
            color: #777;
            font-size: 8.5px;
            margin: 2px 0 0;
            padding: 4px 6px;
            border: 1px dashed #ccc;
        }
        .totals th, .totals td { font-weight: 700; }
        .net th, .net td { background: #eef6f1; }
        .ack {
            margin: 6px 0 0;
            font-size: 8px;
            color: #444;
        }
        .signatures {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .signatures td {
            border: none;
            width: 50%;
            vertical-align: top;
            padding: 0 8px;
            @if ($rtl)
                text-align: right;
            @else
                text-align: left;
            @endif
        }
        .sig-box {
            border-top: 1px solid #1a1a1a;
            padding-top: 4px;
            margin-top: 22px;
        }
        .sig-role {
            font-weight: 700;
            font-size: 9px;
            margin: 0 0 4px;
        }
        .sig-line {
            margin: 0 0 3px;
            font-size: 8.5px;
            color: #444;
        }
        .sig-blank {
            display: inline-block;
            border-bottom: 1px solid #666;
            min-width: 110px;
            height: 11px;
            margin: 0 3px;
            vertical-align: bottom;
        }
        .footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px solid #ccc;
            font-size: 7.5px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <p class="company">{{ $g($company) }}</p>
            <p class="doc-title">{{ $t('salary.breakdown') }}</p>
            <p class="doc-sub">{{ $t('salary.pdf.subtitle', ['period' => $period]) }}</p>
        </div>

        <p class="section-title">{{ $t('salary.pdf.employee_details') }}</p>
        <table class="meta">
            @foreach ($metaPairs as $pair)
                <tr>
                    @if ($rtl)
                        @foreach (array_reverse($pair) as [$labelKey, $value])
                            @if ($labelKey)
                                <td>{{ $value }}</td>
                                <th>{{ $t($labelKey) }}</th>
                            @else
                                <td></td>
                                <th></th>
                            @endif
                        @endforeach
                    @else
                        @foreach ($pair as [$labelKey, $value])
                            @if ($labelKey)
                                <th>{{ $t($labelKey) }}</th>
                                <td>{{ $value }}</td>
                            @else
                                <th></th>
                                <td></td>
                            @endif
                        @endforeach
                    @endif
                </tr>
            @endforeach
        </table>

        <table class="cols">
            <tr>
                @if ($rtl)
                    <td>
                        <p class="section-title">{{ $t('salary.pdf.deductions') }}</p>
                        @unless ($hasDeductions)
                            <p class="empty-hint">{{ $t('salary.pdf.none') }}</p>
                        @else
                            <table class="lines">
                                @foreach ($rowsDeduct as $key => $value)
                                    <tr>{!! $rowHtml($t($key), $money($value), $rtl) !!}</tr>
                                @endforeach
                                @foreach ($deductAdj as $line)
                                    <tr>{!! $rowHtml($line['label'], $money($line['amount']), $rtl) !!}</tr>
                                @endforeach
                            </table>
                        @endunless
                    </td>
                    <td>
                        <p class="section-title">{{ $t('salary.pdf.earnings') }}</p>
                        @unless ($hasEarnings)
                            <p class="empty-hint">{{ $t('salary.pdf.none') }}</p>
                        @else
                            <table class="lines">
                                @foreach ($rowsPay as $key => $value)
                                    <tr>{!! $rowHtml($t($key), $money($value), $rtl) !!}</tr>
                                @endforeach
                                @foreach ($earningAdj as $line)
                                    <tr>{!! $rowHtml($line['label'], $money($line['amount']), $rtl) !!}</tr>
                                @endforeach
                            </table>
                        @endunless
                    </td>
                @else
                    <td>
                        <p class="section-title">{{ $t('salary.pdf.earnings') }}</p>
                        @unless ($hasEarnings)
                            <p class="empty-hint">{{ $t('salary.pdf.none') }}</p>
                        @else
                            <table class="lines">
                                @foreach ($rowsPay as $key => $value)
                                    <tr>{!! $rowHtml($t($key), $money($value), $rtl) !!}</tr>
                                @endforeach
                                @foreach ($earningAdj as $line)
                                    <tr>{!! $rowHtml($line['label'], $money($line['amount']), $rtl) !!}</tr>
                                @endforeach
                            </table>
                        @endunless
                    </td>
                    <td>
                        <p class="section-title">{{ $t('salary.pdf.deductions') }}</p>
                        @unless ($hasDeductions)
                            <p class="empty-hint">{{ $t('salary.pdf.none') }}</p>
                        @else
                            <table class="lines">
                                @foreach ($rowsDeduct as $key => $value)
                                    <tr>{!! $rowHtml($t($key), $money($value), $rtl) !!}</tr>
                                @endforeach
                                @foreach ($deductAdj as $line)
                                    <tr>{!! $rowHtml($line['label'], $money($line['amount']), $rtl) !!}</tr>
                                @endforeach
                            </table>
                        @endunless
                    </td>
                @endif
            </tr>
        </table>

        <p class="section-title">{{ $t('salary.pdf.summary') }}</p>
        <table class="totals lines">
            @foreach ($rowsTotal as $key => $value)
                <tr @class(['net' => $key === 'payroll.fields.net'])>
                    {!! $rowHtml($t($key), $money($value), $rtl) !!}
                </tr>
            @endforeach
        </table>

        <p class="ack">{{ $t('salary.pdf.acknowledgement_text') }}</p>

        <table class="signatures">
            <tr>
                @if ($rtl)
                    <td>
                        <div class="sig-box">
                            <p class="sig-role">{{ $t('salary.pdf.authorized_signature') }}</p>
                            <p class="sig-line">{{ $t('salary.pdf.signature') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.date') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.name') }}: <span class="sig-blank"></span></p>
                        </div>
                    </td>
                    <td>
                        <div class="sig-box">
                            <p class="sig-role">{{ $t('salary.pdf.employee_signature') }}</p>
                            <p class="sig-line">{{ $t('salary.pdf.signature') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.date') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.name') }}: <span class="sig-blank"></span></p>
                        </div>
                    </td>
                @else
                    <td>
                        <div class="sig-box">
                            <p class="sig-role">{{ $t('salary.pdf.employee_signature') }}</p>
                            <p class="sig-line">{{ $t('salary.pdf.signature') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.date') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.name') }}: <span class="sig-blank"></span></p>
                        </div>
                    </td>
                    <td>
                        <div class="sig-box">
                            <p class="sig-role">{{ $t('salary.pdf.authorized_signature') }}</p>
                            <p class="sig-line">{{ $t('salary.pdf.signature') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.date') }}: <span class="sig-blank"></span></p>
                            <p class="sig-line">{{ $t('salary.pdf.name') }}: <span class="sig-blank"></span></p>
                        </div>
                    </td>
                @endif
            </tr>
        </table>

        <div class="footer">
            {{ $t('salary.pdf.footer', ['company' => $company, 'ref' => $docRef, 'date' => $issuedAt]) }}
        </div>
    </div>
</body>
</html>

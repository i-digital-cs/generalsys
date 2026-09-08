@php
    use App\Support\DompdfArabic;
    use App\Support\DompdfFonts;
    use App\Services\Valuation\ValuationCalculator;
    use App\Services\Valuation\ValuationCopy;
    use Illuminate\Support\Carbon;

    $module = $module ?? 'service';
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $in = $input;
    $out = $result;
    $user = $sheet->user;
    $name = $user
        ? ($rtl ? ($user->name_ar ?: $user->name_en) : ($user->name_en ?: $user->name_ar))
        : '';
    $line = static fn (string $key, array $replace = []) => ValuationCopy::line($module, $key, $replace);
    $tl = static fn (string $key, array $replace = []) => DompdfArabic::glyphs($line($key, $replace));
    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $note = static function (?string $text) use ($g): string {
        $text = trim((string) $text);
        if ($text === '') {
            return '—';
        }

        return nl2br(e($g($text)), false);
    };
    $pctScore = static fn ($v) => number_format(ValuationCalculator::toPercent($v), 2).'%';
    $ratio = static fn ($v) => ValuationCalculator::formatPercent((float) $v);
    $stars = static fn ($v) => ValuationCalculator::starHtml($v);
    $num = static fn ($v) => (string) $v;
    $kv = static function (string $label, string $value, bool $rtl): string {
        if ($rtl) {
            return '<td class="amount">'.$value.'</td><th>'.$label.'</th>';
        }

        return '<th>'.$label.'</th><td class="amount">'.$value.'</td>';
    };
    $dateLabel = Carbon::parse($sheet->date)->locale($locale)->translatedFormat($rtl ? 'd / m / Y' : 'd M Y');
    $depts = ValuationCopy::departmentBlocks($module);
    $driverUsers = $driverUsers ?? collect();
    $driverRows = ValuationCopy::showDynamicDrivers($module)
        ? (is_array($out['drivers'] ?? null)
            ? $out['drivers']
            : \App\Services\Valuation\ValuationFields::normalizeDrivers($in['drivers'] ?? []))
        : [];
    $driverName = static function (array $row) use ($driverUsers, $rtl): string {
        return \App\Services\Valuation\ValuationFields::driverDisplayName($row, function (int $id) use ($driverUsers, $rtl): string {
            $user = $driverUsers->get($id);
            if ($user === null) {
                return '';
            }

            return $rtl
                ? ($user->name_ar ?: $user->name_en ?: '')
                : ($user->name_en ?: $user->name_ar ?: '');
        });
    };
    $salesKeys = ValuationCopy::salesChannelKeys();
    $providerKeys = ['provider_mart','provider_kings','provider_golden','provider_rug','work_photo','work_video','work_software','ads_google','ads_meta'];
    $pdfTitle = $tl('pdf_title');
    $b2bExecution = $tl('fields.b2b');
    $b2cExecution = $tl('fields.b2c');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $pdfTitle }}</title>
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
            font-size: 8.5px;
            color: #1a1a1a;
            direction: ltr;
            margin: 0;
            line-height: 1.3;
        }
        .sheet {
            @if ($rtl) text-align: right; @else text-align: left; @endif
        }
        .header { border-bottom: 1.5px solid #1a1a1a; padding-bottom: 6px; margin-bottom: 8px; }
        .company { font-size: 12px; font-weight: 700; margin: 0; }
        .doc-title { font-size: 13px; font-weight: 700; margin: 2px 0 0; }
        .doc-sub { color: #555; font-size: 8px; margin: 1px 0 0; }
        .section-title {
            font-size: 9.5px; font-weight: 700; margin: 8px 0 3px;
            padding: 3px 6px; background: #1b4f8a; color: #fff;
        }
        .status {
            background: #c0392b; color: #fff; font-weight: 700;
            text-align: center; padding: 3px; margin: 0 0 6px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #bdbdbd; padding: 3px 5px; vertical-align: top; }
        th { background: #d4e6f8; font-weight: 700; @if ($rtl) text-align: right; @else text-align: left; @endif }
        td.amount { direction: ltr; unicode-bidi: embed; white-space: nowrap; @if ($rtl) text-align: left; @else text-align: right; @endif }
        .stars { font-family: DejaVu Sans, sans-serif; font-size: 10px; letter-spacing: 1px; white-space: nowrap; }
        .star { color: #d5d8dc; }
        .star.on { color: #e6a817; }
        .note { white-space: pre-wrap; }
        .cols { width: 100%; border-collapse: collapse; }
        .cols > tbody > tr > td { border: none; width: 50%; padding: 0 3px; vertical-align: top; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="header">
        <p class="company">{{ $g(config('app.name')) }}</p>
        <p class="doc-title">{{ $pdfTitle }} — {{ $t('portal.'.$module) }}</p>
        <p class="doc-sub">{{ $g($name) }} · {{ $dateLabel }} · {{ $t('valuation.statuses.'.$sheet->status) }}</p>
    </div>

    <table>
        <tr>
            {!! $kv($t('valuation.kpi'), $t('valuation.labels.'.($out['kpi_label'] ?? 'loss')), $rtl) !!}
            {!! $kv($t('valuation.cashflow'), $t('valuation.labels.'.($out['cashflow_label'] ?? 'negative')), $rtl) !!}
        </tr>
        <tr>
            {!! $kv($t('valuation.earnings'), $t('valuation.labels.'.($out['earnings_label'] ?? 'low')), $rtl) !!}
            {!! $kv($tl('fields.actual_amount_total'), $num($out['actual_amount_total'] ?? 0), $rtl) !!}
        </tr>
    </table>

    <div class="section-title">{{ $tl('sections.market') }}</div>
    <p class="note">{!! $note($in['market_status'] ?? '') !!}</p>
    <table>
        <tr>
            {!! $kv($tl('fields.orders_movement'), $num($in['orders_movement'] ?? 0), $rtl) !!}
            {!! $kv($tl('fields.growth_percent'), $num($in['growth_percent'] ?? 0), $rtl) !!}
        </tr>
        <tr>
            {!! $kv($tl('fields.recession_index'), $num($in['recession_index'] ?? 0), $rtl) !!}
            {!! $kv($tl('fields.sales_spread'), $num($in['sales_spread'] ?? 0), $rtl) !!}
        </tr>
        <tr>
            {!! $kv($tl('fields.marketing_spread'), $num($in['marketing_spread'] ?? 0), $rtl) !!}
            @if (ValuationCopy::showHaroonNewsInMarket($module))
                {!! $kv($tl('fields.haroon_news'), $note($in['haroon_news'] ?? ''), $rtl) !!}
            @endif
        </tr>
    </table>

    @if (ValuationCopy::showOperationFlowHeader($module))
        <div class="section-title">{{ $tl('sections.operation_flow') }}</div>
    @endif

    <table class="cols">
        <tr>
            <td>
                <div class="section-title">{{ $tl('sections.laundry_king') }}</div>
                <table>
                    <tr>{!! $kv($tl('fields.score'), $pctScore($in['laundry_king_score'] ?? 0).' '.$stars($in['laundry_king_score'] ?? 0), $rtl) !!}</tr>
                    <tr>{!! $kv($tl('fields.laundry_king_positives'), $note($in['laundry_king_positives'] ?? ''), $rtl) !!}</tr>
                    <tr>{!! $kv($tl('fields.laundry_king_negatives'), $note($in['laundry_king_negatives'] ?? ''), $rtl) !!}</tr>
                </table>
            </td>
            <td>
                <div class="section-title">{{ $tl('sections.laundry_mart') }}</div>
                <table>
                    <tr>{!! $kv($tl('fields.score'), $pctScore($in['laundry_mart_score'] ?? 0).' '.$stars($in['laundry_mart_score'] ?? 0), $rtl) !!}</tr>
                    <tr>{!! $kv($tl('fields.laundry_mart_positives'), $note($in['laundry_mart_positives'] ?? ''), $rtl) !!}</tr>
                    <tr>{!! $kv($tl('fields.laundry_mart_negatives'), $note($in['laundry_mart_negatives'] ?? ''), $rtl) !!}</tr>
                </table>
            </td>
        </tr>
    </table>

    @if (ValuationCopy::showOperationalNotesAfterSuppliers($module))
        <div class="section-title">{{ $tl('fields.haroon_news') }}</div>
        <p class="note">{!! $note($in['haroon_news'] ?? '') !!}</p>
    @endif

    @if (ValuationCopy::showLogisticsBlock($module))
        <div class="section-title">{{ $tl('sections.customer_care_logistics') }}</div>
        <table>
            <tr>{!! $kv($tl('fields.score'), $pctScore($in['customer_care_logistics_score'] ?? 0).' '.$stars($in['customer_care_logistics_score'] ?? 0), $rtl) !!}</tr>
            <tr>{!! $kv($tl('fields.notes'), $note($in['customer_care_logistics_notes'] ?? ''), $rtl) !!}</tr>
        </table>
    @endif

    <div class="section-title">{{ $tl('sections.sales') }}</div>
    <table>
        @foreach ($salesKeys as $sk)
            <tr>{!! $kv($tl('fields.'.$sk), $pctScore($in[$sk] ?? 0).' '.$stars($in[$sk] ?? 0), $rtl) !!}</tr>
        @endforeach
        <tr>{!! $kv($tl('fields.sales_average'), number_format((float) ($out['sales_average'] ?? 0), 2).'% '.$stars($out['sales_average'] ?? 0), $rtl) !!}</tr>
        <tr>{!! $kv($tl('fields.notes'), $note($in['sales_notes'] ?? ''), $rtl) !!}</tr>
    </table>

    <div class="section-title">{{ $tl('sections.drivers') }}</div>
    @if ($driverRows === [])
        <table>
            <tr>{!! $kv($tl('fields.score'), $pctScore($in['drivers_score'] ?? 0).' '.$stars($in['drivers_score'] ?? 0), $rtl) !!}</tr>
            <tr>{!! $kv($tl('fields.notes'), $note($in['drivers_notes'] ?? ''), $rtl) !!}</tr>
        </table>
    @else
        <table>
            <tr>{!! $kv($tl('drivers.avg_score'), $pctScore($out['drivers_score_avg'] ?? $in['drivers_score'] ?? 0).' '.$stars($out['drivers_score_avg'] ?? $in['drivers_score'] ?? 0), $rtl) !!}</tr>
        </table>
        @foreach ($driverRows as $drow)
            @php
                $dOrders = (float) ($drow['orders'] ?? 0);
                $dTarget = (float) ($drow['target'] ?? \App\Services\Valuation\ValuationFields::DRIVER_ORDERS_TARGET);
                $dOver = (float) ($drow['over_target'] ?? \App\Services\Valuation\ValuationFields::driverOverTarget($dOrders, $dTarget));
                $dUnder = (float) ($drow['under_target'] ?? \App\Services\Valuation\ValuationFields::driverUnderTarget($dOrders, $dTarget));
                $dScore = (float) ($drow['score'] ?? 0);
                $dGap = $dOver > 0
                    ? ' · '.$tl('drivers.over_target', ['x' => $dOver])
                    : ($dUnder > 0 ? ' · '.$tl('drivers.under_target', ['x' => $dUnder]) : '');
            @endphp
            <table>
                <tr>{!! $kv($tl('drivers.employee'), $g($driverName($drow)), $rtl) !!}</tr>
                <tr>{!! $kv($tl('drivers.orders'), $num($dOrders).' / '.$tl('drivers.target').' '.$num($dTarget).$dGap, $rtl) !!}</tr>
                <tr>{!! $kv($tl('fields.score'), $pctScore($dScore).' '.$stars($dScore), $rtl) !!}</tr>
                <tr>{!! $kv($tl('fields.notes'), $note($drow['notes'] ?? ''), $rtl) !!}</tr>
            </table>
        @endforeach
    @endif

    @foreach ($depts as [$key, $scoreKey, $notesKey])
        <div class="section-title">{{ $tl('sections.'.$key) }}</div>
        <table>
            <tr>{!! $kv($tl('fields.score'), $pctScore($in[$scoreKey] ?? 0).' '.$stars($in[$scoreKey] ?? 0), $rtl) !!}</tr>
            <tr>{!! $kv($tl('fields.notes'), $note($in[$notesKey] ?? ''), $rtl) !!}</tr>
        </table>
    @endforeach

    <div class="section-title">{{ $t('valuation.sections.valuation_methods') }}</div>
    <table>
        <tr>
            <th>{{ $t('valuation.fields.general_target') }}</th>
            <td class="amount">{{ $num($in['target_count_total'] ?? $out['target_count_total'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.execution') }}</th>
            <td class="amount">{{ $num($out['actual_count_total'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['count_ratio'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>{{ $t('valuation.fields.target_b2b') }}</th>
            <td class="amount">{{ $num($in['target_b2b_count'] ?? 0) }}</td>
            <th>{{ $b2bExecution }}</th>
            <td class="amount">{{ $num($in['actual_b2b_count'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['count_b2b_ratio'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>{{ $t('valuation.fields.target_b2c') }}</th>
            <td class="amount">{{ $num($in['target_b2c_count'] ?? 0) }}</td>
            <th>{{ $b2cExecution }}</th>
            <td class="amount">{{ $num($in['actual_b2c_count'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['count_b2c_ratio'] ?? 0) }}</td>
        </tr>
    </table>

    <div class="section-title">{{ $t('valuation.sections.financial_affairs') }}</div>
    <table>
        <tr>
            <th>{{ $t('valuation.fields.general_target') }}</th>
            <td class="amount">{{ $num($in['target_amount_total'] ?? $out['target_amount_total'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.execution') }}</th>
            <td class="amount">{{ $num($out['actual_amount_total'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['amount_ratio'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>{{ $t('valuation.fields.target_b2b') }}</th>
            <td class="amount">{{ $num($in['target_b2b_amount'] ?? 0) }}</td>
            <th>{{ $b2bExecution }}</th>
            <td class="amount">{{ $num($in['actual_b2b_amount'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['amount_b2b_ratio'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>{{ $t('valuation.fields.target_b2c') }}</th>
            <td class="amount">{{ $num($in['target_b2c_amount'] ?? 0) }}</td>
            <th>{{ $b2cExecution }}</th>
            <td class="amount">{{ $num($in['actual_b2c_amount'] ?? 0) }}</td>
            <th>{{ $t('valuation.fields.percent') }}</th>
            <td class="amount">{{ $ratio($out['amount_b2c_ratio'] ?? 0) }}</td>
        </tr>
    </table>

    <div class="section-title">{{ $t('valuation.sections.kpi_vs_target') }}</div>
    <table>
        <tr>
            {!! $kv($t('valuation.fields.general_target'), $num($in['kpi_base'] ?? 0), $rtl) !!}
            {!! $kv($t('valuation.fields.kpis'), $ratio($out['kpi_ratio'] ?? 0), $rtl) !!}
        </tr>
    </table>
    <div class="status">{{ $t('valuation.labels.'.($out['kpi_label'] ?? 'loss')) }}</div>

    <div class="section-title">{{ $t('valuation.sections.cashflow_details') }}</div>
    <table>
        <tr>{!! $kv($t('valuation.fields.general_target'), $num($in['cashflow_base'] ?? 0), $rtl) !!}</tr>
    </table>
    <div class="status">{{ $t('valuation.labels.'.($out['cashflow_label'] ?? 'negative')) }}</div>
    <div class="status">{{ $t('valuation.labels.'.($out['earnings_label'] ?? 'low')) }}</div>

    @if (ValuationCopy::showProviders($module))
        <div class="section-title">{{ $t('valuation.sections.providers') }}</div>
        <table>
            @foreach ($providerKeys as $pk)
                <tr>{!! $kv($t('valuation.fields.'.$pk), $pctScore($in[$pk] ?? 0), $rtl) !!}</tr>
            @endforeach
        </table>
    @endif
</div>
</body>
</html>

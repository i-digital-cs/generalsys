@php
    use App\Support\DompdfArabic;

    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $money = static fn ($v) => number_format((float) $v, 2);
    $rtl = app()->getLocale() === 'ar';
    $hasReturns = ! empty($display['has_returns']);
    $hasAdjustments = $hasReturns && (
        ($display['returned_merchandise'] ?? 0) > 0
        || (float) ($display['net_grand_total'] ?? 0) !== (float) $invoice->total
    );
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Tajawal, DejaVu Sans, sans-serif; font-size: {{ $hasReturns ? '10px' : '12px' }}; color: #222; }
        .header { width: 100%; margin-bottom: 16px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .header td { vertical-align: top; }
        .num { direction: ltr; unicode-bidi: embed; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #ddd; padding: {{ $hasReturns ? '4px' : '6px' }}; }
        table.lines th { background: #f5f5f5; font-size: {{ $hasReturns ? '9px' : 'inherit' }}; }
        .totals { width: 50%; margin-{{ $rtl ? 'right' : 'left' }}: auto; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .badge { display: inline-block; padding: 2px 8px; background: #eee; border-radius: 4px; }
        .warn { color: #856404; }
        .primary { color: #0d6efd; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div>{{ $g(config('app.name')) }} · {{ $t('gross.purchases.title') }}</div>
                <h1 style="margin: 4px 0" class="num">{{ $invoice->invoice_number }}</h1>
                <div>{{ $invoice->created_at?->format('Y-m-d H:i') }}</div>
            </td>
            <td style="text-align: {{ $rtl ? 'left' : 'right' }}">
                <span class="badge">{{ $t('gross.statuses.'.$invoice->status) }}</span>
                <div>{{ $t('gross.fields.currency') }}: {{ $invoice->currency }}</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-bottom: 12px">
        <tr>
            <td style="width:50%; vertical-align:top">
                <strong>{{ $t('gross.fields.supplier') }}</strong><br>
                {{ $g($invoice->supplier_name) }}<br>
                <span class="num">{{ $invoice->supplier_phone }}</span><br>
                {{ $g($invoice->supplier_address) }}
            </td>
            <td style="width:50%; vertical-align:top">
                <strong>{{ $t('gross.fields.created_by') }}</strong>: {{ $g($invoice->creator?->localizedName()) }}<br>
                <strong>{{ $t('gross.fields.notes') }}</strong>: {{ $g($invoice->notes) }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ $t('gross.fields.code') }}</th>
                <th>{{ $t('gross.fields.name') }}</th>
                <th>{{ $t('gross.fields.size') }}</th>
                @if ($hasReturns)
                    <th>{{ $t('gross.fields.qty') }}</th>
                    <th>{{ $t('gross.fields.qty_returned') }}</th>
                    <th>{{ $t('gross.fields.qty_remaining') }}</th>
                @else
                    <th>{{ $t('gross.fields.qty') }}</th>
                @endif
                <th>{{ $t('gross.fields.buy_price') }}</th>
                <th>{{ $t('gross.fields.sale_price') }}</th>
                <th>{{ $t('gross.fields.line_total') }}</th>
                @if ($hasReturns)
                    <th>{{ $t('gross.purchases.returned_amount') }}</th>
                    <th>{{ $t('gross.purchases.net_line_total') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($hasReturns ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line]) as $row)
                @php $line = $row['line'] ?? $row; @endphp
                <tr>
                    <td class="num">{{ $line->item_code_snapshot }}</td>
                    <td>{{ $g($line->item_name_snapshot) }}</td>
                    <td>{{ $g($line->size_snapshot) }}</td>
                    @if ($hasReturns)
                        <td class="num">{{ $row['qty_purchased'] }}</td>
                        <td class="num">{{ $row['qty_returned'] }}</td>
                        <td class="num">{{ $row['qty_remaining'] }}</td>
                    @else
                        <td class="num">{{ $line->qty }}</td>
                    @endif
                    <td class="num">{{ $money($line->buy_price) }}</td>
                    <td class="num">{{ $money($line->sale_price) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                    @if ($hasReturns)
                        <td class="num warn">
                            @if ($row['returned_amount'] > 0)
                                −{{ $money($row['returned_amount']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="num"><strong>{{ $money($row['net_line_total']) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ $t('gross.fields.subtotal') }}</td>
            <td class="num" style="text-align:right">{{ $money($invoice->subtotal) }} {{ $invoice->currency }}</td>
        </tr>
        @if ($hasAdjustments)
            <tr>
                <td class="warn">{{ $t('gross.purchases.returned_merchandise') }}</td>
                <td class="num warn" style="text-align:right">−{{ $money($display['returned_merchandise']) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ $t('gross.purchases.net_subtotal') }}</td>
                <td class="num" style="text-align:right"><strong>{{ $money($display['net_subtotal']) }} {{ $invoice->currency }}</strong></td>
            </tr>
        @endif
        <tr>
            <td>{{ $t('gross.fields.invoice_discount') }}</td>
            <td class="num" style="text-align:right">
                {{ $money($invoice->discount_amount) }} {{ $invoice->currency }}
                @if ($hasAdjustments && ($display['net_discount'] ?? 0) != (float) $invoice->discount_amount)
                    <br><span style="font-size:9px">{{ $t('gross.purchases.net_discount') }}: {{ $money($display['net_discount']) }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>{{ $t('gross.fields.grand_total') }}</strong></td>
            <td class="num" style="text-align:right"><strong>{{ $money($invoice->total) }} {{ $invoice->currency }}</strong></td>
        </tr>
        @if ($hasReturns)
            <tr>
                <td class="primary">{{ $t('gross.purchases.net_payable') }}</td>
                <td class="num primary" style="text-align:right">{{ $money($display['net_grand_total']) }} {{ $invoice->currency }}</td>
            </tr>
        @endif
    </table>
</body>
</html>

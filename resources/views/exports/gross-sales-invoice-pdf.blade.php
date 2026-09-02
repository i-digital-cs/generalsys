@php
    use App\Support\DompdfArabic;

    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $money = static fn ($v) => number_format((float) $v, 2);
    $rtl = app()->getLocale() === 'ar';
    $tracked = ! empty($display['has_tracking']);
    $hasAdjustments = $tracked && (
        ($display['returned_merchandise'] ?? 0) > 0
        || collect($display['lines'] ?? [])->contains(fn (array $row): bool => ($row['lost_amount'] ?? 0) > 0)
        || (float) ($display['net_grand_total'] ?? 0) !== (float) $invoice->total
    );
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Tajawal, DejaVu Sans, sans-serif; font-size: {{ $tracked ? '10px' : '12px' }}; color: #222; }
        .header { width: 100%; margin-bottom: 16px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .header td { vertical-align: top; }
        .num { direction: ltr; unicode-bidi: embed; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #ddd; padding: {{ $tracked ? '4px' : '6px' }}; }
        table.lines th { background: #f5f5f5; font-size: {{ $tracked ? '9px' : 'inherit' }}; }
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
                <div>{{ $g(config('app.name')) }} · {{ $t('gross.sales.title') }}</div>
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
                <strong>{{ $t('gross.fields.customer') }}</strong><br>
                {{ $g($invoice->customer_name) }}<br>
                <span class="num">{{ $invoice->customer_phone }}</span><br>
                {{ $g($invoice->customer_address) }}
            </td>
            <td style="width:50%; vertical-align:top">
                <strong>{{ $t('gross.fields.sales_person') }}</strong>: {{ $g($invoice->salesUser?->localizedName()) }}<br>
                <strong>{{ $t('gross.fields.shipping_company') }}</strong>: {{ $g($invoice->shipping_company_name) }}<br>
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
                @if ($tracked)
                    <th>{{ $t('gross.sales.qty_shipped') }}</th>
                    <th>{{ $t('gross.fields.returned') }}</th>
                    <th>{{ $t('gross.fields.with_courier') }}</th>
                    <th>{{ $t('gross.fields.delivered') }}</th>
                @else
                    <th>{{ $t('gross.fields.qty') }}</th>
                @endif
                <th>{{ $t('gross.fields.unit_price') }}</th>
                <th>{{ $t('gross.fields.line_discount') }}</th>
                <th>{{ $t('gross.fields.line_total') }}</th>
                @if ($tracked)
                    <th>{{ $t('gross.sales.returned_amount') }}</th>
                    <th>{{ $t('gross.sales.net_line_total') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($tracked ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line]) as $row)
                @php $line = $row['line'] ?? $row; @endphp
                <tr>
                    <td class="num">{{ $line->item_code_snapshot }}</td>
                    <td>{{ $g($line->item_name_snapshot) }}</td>
                    <td>{{ $g($line->size_snapshot) }}</td>
                    @if ($tracked)
                        <td class="num">{{ $row['qty_shipped'] }}</td>
                        <td class="num">{{ $row['qty_returned'] }}</td>
                        <td class="num">{{ $row['qty_with_courier'] }}</td>
                        <td class="num">{{ $row['qty_delivered'] }}</td>
                    @else
                        <td class="num">{{ $line->qty }}</td>
                    @endif
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->discount_amount) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                    @if ($tracked)
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
                <td class="warn">{{ $t('gross.sales.returned_merchandise') }}</td>
                <td class="num warn" style="text-align:right">−{{ $money($display['returned_merchandise']) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>{{ $t('gross.sales.net_subtotal') }}</td>
                <td class="num" style="text-align:right"><strong>{{ $money($display['net_subtotal']) }} {{ $invoice->currency }}</strong></td>
            </tr>
        @endif
        <tr>
            <td>{{ $t('gross.fields.invoice_discount') }}</td>
            <td class="num" style="text-align:right">
                {{ $money($invoice->discount_amount) }} {{ $invoice->currency }}
                @if ($hasAdjustments && ($display['net_discount'] ?? 0) != (float) $invoice->discount_amount)
                    <br><span style="font-size:9px">{{ $t('gross.sales.net_discount') }}: {{ $money($display['net_discount']) }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>{{ $t('gross.fields.shipping_fee') }}</td>
            <td class="num" style="text-align:right">{{ $money($invoice->shipping_fee) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td><strong>{{ $t('gross.fields.grand_total') }}</strong></td>
            <td class="num" style="text-align:right"><strong>{{ $money($invoice->total) }} {{ $invoice->currency }}</strong></td>
        </tr>
        @if ($tracked)
            <tr>
                <td class="primary">{{ $t('gross.sales.net_collectible') }}</td>
                <td class="num primary" style="text-align:right">{{ $money($display['net_grand_total']) }} {{ $invoice->currency }}</td>
            </tr>
        @endif
    </table>
</body>
</html>

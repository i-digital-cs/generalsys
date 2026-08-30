@php
    use App\Support\DompdfArabic;

    $t = static fn (string $key, array $replace = []) => DompdfArabic::glyphs(__($key, $replace));
    $g = static fn (?string $text) => DompdfArabic::glyphs($text ?? '');
    $money = static fn ($v) => number_format((float) $v, 2);
    $rtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Tajawal, DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .header { width: 100%; margin-bottom: 16px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .header td { vertical-align: top; }
        .num { direction: ltr; unicode-bidi: embed; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #ddd; padding: 6px; }
        table.lines th { background: #f5f5f5; }
        .totals { width: 45%; margin-{{ $rtl ? 'right' : 'left' }}: auto; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .badge { display: inline-block; padding: 2px 8px; background: #eee; border-radius: 4px; }
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
                <th>{{ $t('gross.fields.qty') }}</th>
                <th>{{ $t('gross.fields.unit_price') }}</th>
                <th>{{ $t('gross.fields.line_discount') }}</th>
                <th>{{ $t('gross.fields.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td class="num">{{ $line->item_code_snapshot }}</td>
                    <td>{{ $g($line->item_name_snapshot) }}</td>
                    <td>{{ $g($line->size_snapshot) }}</td>
                    <td class="num">{{ $line->qty }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->discount_amount) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ $t('gross.fields.subtotal') }}</td>
            <td class="num" style="text-align:right">{{ $money($invoice->subtotal) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td>{{ $t('gross.fields.invoice_discount') }}</td>
            <td class="num" style="text-align:right">{{ $money($invoice->discount_amount) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td>{{ $t('gross.fields.shipping_fee') }}</td>
            <td class="num" style="text-align:right">{{ $money($invoice->shipping_fee) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td><strong>{{ $t('gross.fields.grand_total') }}</strong></td>
            <td class="num" style="text-align:right"><strong>{{ $money($invoice->total) }} {{ $invoice->currency }}</strong></td>
        </tr>
    </table>
</body>
</html>

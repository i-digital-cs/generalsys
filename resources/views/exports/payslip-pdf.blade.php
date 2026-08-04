@php
    $emp = $payroll->user;
    $name = $emp ? ($emp->name_en ?: $emp->name_ar) : __('general.not_available');
    $period = sprintf('%02d/%04d', $payroll->month, $payroll->year);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('salary.breakdown') }} {{ $period }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; width: 45%; }
        .totals td { font-weight: bold; }
        .ltr { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>
    <h1>{{ __('salary.breakdown') }}</h1>
    <p class="muted">{{ $name }} · {{ $period }} · {{ __('payroll.status.'.$payroll->status) }} · {{ __('general.currencies.'.$payroll->currency) }}</p>

    <table>
        <tr><th>{{ __('payroll.fields.base_salary') }}</th><td class="ltr">{{ number_format((float) $payroll->base_salary, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.full_time_pay') }}</th><td class="ltr">{{ number_format((float) $payroll->full_time_pay, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.online_half_pay') }}</th><td class="ltr">{{ number_format((float) $payroll->online_half_pay, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.online_full_pay') }}</th><td class="ltr">{{ number_format((float) $payroll->online_full_pay, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.overtime_pay') }}</th><td class="ltr">{{ number_format((float) $payroll->overtime_pay, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.friday_bonus') }}</th><td class="ltr">{{ number_format((float) $payroll->friday_bonus, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.day_off_bonus') }}</th><td class="ltr">{{ number_format((float) $payroll->day_off_bonus, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.official_holiday_bonus') }}</th><td class="ltr">{{ number_format((float) $payroll->official_holiday_bonus, 2) }}</td></tr>
    </table>

    <table>
        <tr><th>{{ __('payroll.fields.late_penalty') }}</th><td class="ltr">{{ number_format((float) $payroll->late_penalty, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.leave_permission_penalty') }}</th><td class="ltr">{{ number_format((float) $payroll->leave_permission_penalty, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.late_permission_penalty') }}</th><td class="ltr">{{ number_format((float) $payroll->late_permission_penalty, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.excused_absence_penalty') }}</th><td class="ltr">{{ number_format((float) $payroll->excused_absence_penalty, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.unexcused_absence_penalty') }}</th><td class="ltr">{{ number_format((float) $payroll->unexcused_absence_penalty, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.forced_penalty_surcharge') }}</th><td class="ltr">{{ number_format((float) $payroll->forced_penalty_surcharge, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.advance_deductions') }}</th><td class="ltr">{{ number_format((float) $payroll->advance_deductions, 2) }}</td></tr>
    </table>

    <table class="totals">
        <tr><th>{{ __('payroll.fields.gross') }}</th><td class="ltr">{{ number_format((float) $payroll->gross_pay, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.deductions') }}</th><td class="ltr">{{ number_format((float) $payroll->total_deductions, 2) }}</td></tr>
        <tr><th>{{ __('payroll.fields.net') }}</th><td class="ltr">{{ number_format((float) $payroll->net_pay, 2) }}</td></tr>
    </table>
</body>
</html>

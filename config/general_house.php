<?php

/**
 * General House (service/gh) operational configuration.
 * Does NOT change config('app.timezone') — Attendance/Payroll/Gross keep app TZ.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Business timezone (UAE)
    |--------------------------------------------------------------------------
    |
    | Form pickup windows are interpreted in this zone. Canonical storage is
    | UTC via App\Casts\GhUtcDateTime (independent of app.timezone).
    | Display uses each order's stored `timezone` column.
    | See docs/general-house/TIMEZONE.md.
    |
    */
    'timezone' => env('GH_TIMEZONE', 'Asia/Dubai'),

    'pickup_eligibility_lead_hours' => (int) env('GH_PICKUP_LEAD_HOURS', 24),

    /*
    | Default standard-rated VAT percent (tax-exclusive prices). Not legal advice.
    */
    'standard_vat_rate' => env('GH_STANDARD_VAT_RATE', '5.00'),

    /*
    | Tax class for minimum-order SURCHARGE adjustments (C-GH-TAX-MIN).
    | Null = unresolved → ISSUE blocked when surcharge amount > 0.
    | Set only after owner/tax-advisor confirmation (e.g. STANDARD_RATED).
    */
    'min_order_surcharge_tax_class' => env('GH_MIN_ORDER_SURCHARGE_TAX_CLASS'),
];

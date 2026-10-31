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
];

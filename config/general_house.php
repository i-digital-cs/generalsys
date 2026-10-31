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
    | New pickup windows are interpreted in this zone. Absolute instants are
    | stored via Laravel datetime casting (DB TIMESTAMP / UTC-normalized).
    | Display uses each order's stored `timezone` column so GH-3 rows that
    | recorded Africa/Cairo (if any) are never silently reinterpreted.
    |
    */
    'timezone' => env('GH_TIMEZONE', 'Asia/Dubai'),

    'pickup_eligibility_lead_hours' => (int) env('GH_PICKUP_LEAD_HOURS', 24),
];

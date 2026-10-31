<?php

/**
 * General House (service/gh) operational configuration.
 * Does NOT change config('app.timezone') — Attendance/Payroll/Gross keep app TZ.
 *
 * GH-9.2 owner-approved initial launch matrix (TEST implementation):
 * cash + partial ON; online/bank/driver/refunds/overpay OFF; min-order VALIDATION_ONLY;
 * surcharge DISABLED. See docs/general-house/GH9_2_APPROVED_LAUNCH_POLICY.md.
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
    | Null = unresolved → ISSUE blocked when surcharge amount > 0 (fail-closed).
    | Launch keeps surcharge disabled; do not set without accountant approval.
    */
    'min_order_surcharge_tax_class' => env('GH_MIN_ORDER_SURCHARGE_TAX_CLASS'),

    /*
    |--------------------------------------------------------------------------
    | Minimum order (GH-9.2 approved launch)
    |--------------------------------------------------------------------------
    */
    'min_order' => [
        'default_policy' => env('GH_MIN_ORDER_DEFAULT_POLICY', 'VALIDATION_ONLY'),
        // Owner-approved: no automatic surcharge for initial launch.
        'surcharge_enabled' => filter_var(
            env('GH_MIN_ORDER_SURCHARGE_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments — GH-9.2 approved launch (fail closed when unset)
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'cash_enabled' => filter_var(env('GH_PAY_CASH_ENABLED', true), FILTER_VALIDATE_BOOL),
        'online_initiation_enabled' => filter_var(env('GH_PAY_ONLINE_INIT_ENABLED', false), FILTER_VALIDATE_BOOL),
        'online_gateway_confirm_enabled' => filter_var(env('GH_PAY_ONLINE_GATEWAY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'bank_transfer_enabled' => filter_var(env('GH_PAY_BANK_ENABLED', false), FILTER_VALIDATE_BOOL),
        'partial_payments_enabled' => filter_var(env('GH_PAY_PARTIAL_ENABLED', true), FILTER_VALIDATE_BOOL),
        'overpayment_allowed' => filter_var(env('GH_PAY_OVERPAYMENT_ALLOWED', false), FILTER_VALIDATE_BOOL),
        'refunds_enabled' => filter_var(env('GH_PAY_REFUNDS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'driver_collection_enabled' => filter_var(env('GH_PAY_DRIVER_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
];

<?php

/**
 * General House (service/gh) operational configuration.
 * Does NOT change config('app.timezone') — Attendance/Payroll/Gross keep app TZ.
 *
 * GH-9.2 launch matrix + GH-10 Option B corrections (TEST):
 * cash + partial ON; online/bank/driver/overpay OFF; min-order VALIDATION_ONLY;
 * surcharge DISABLED; credit notes + cash refunds ON; payment-correction EXECUTE blocked
 * until owner accounting policy. See docs/general-house/GH10_*.md.
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
    | When staff enter a single scheduled pickup datetime, legacy pickup_ends_at
    | is derived as start + this many minutes (schema/compat only; not shown in UI).
    */
    'pickup_default_slot_minutes' => (int) env('GH_PICKUP_DEFAULT_SLOT_MINUTES', 60),

    /*
    | Fallback standard-rated VAT percent when settings.key gh.standard_vat_rate
    | is unset. Authoritative runtime source is GhVatConfigService (DB → this).
    | New drafts stamp the rate; changing this alone does not rewrite existing drafts.
    */
    'standard_vat_rate' => env('GH_STANDARD_VAT_RATE', '5.00'),

    /*
    | Default tax class for new catalog offerings in routine create UI.
    | Advanced / exempt / zero-rated remain editable in offering edit + import.
    */
    'default_offering_tax_class' => env('GH_DEFAULT_OFFERING_TAX_CLASS', 'STANDARD_RATED'),

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
        // Legacy payment-ledger flag; GH-10 cash refunds use corrections.refunds_enabled.
        'refunds_enabled' => filter_var(env('GH_PAY_REFUNDS_ENABLED', false), FILTER_VALIDATE_BOOL),
        // Legacy kill-switch (unused by one-step delivery payment).
        'driver_collection_enabled' => filter_var(env('GH_PAY_DRIVER_COLLECTION_ENABLED', false), FILTER_VALIDATE_BOOL),
        // Driver delivery: select CASH|ONLINE and confirm → CONFIRMED immediately (no manager review).
        'driver_delivery_payment_enabled' => filter_var(
            env('GH_PAY_DRIVER_DELIVERY_PAYMENT_ENABLED', env('GH_PAY_DRIVER_DECLARATION_ENABLED', true)),
            FILTER_VALIDATE_BOOL
        ),
        // Legacy env alias kept for existing TEST .env compatibility.
        'driver_payment_declaration_enabled' => filter_var(
            env('GH_PAY_DRIVER_DECLARATION_ENABLED', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | GH-10 financial corrections (owner Option B)
    |--------------------------------------------------------------------------
    */
    'corrections' => [
        'credit_notes_enabled' => filter_var(env('GH_CN_ENABLED', true), FILTER_VALIDATE_BOOL),
        'refunds_enabled' => filter_var(env('GH_REFUND_ENABLED', true), FILTER_VALIDATE_BOOL),
        // REQUEST/APPROVE allowed; EXECUTE blocked until accounting policy approved.
        'payment_correction_execute_enabled' => filter_var(
            env('GH_PAYMENT_CORRECTION_EXECUTE_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),
        'require_distinct_approver' => filter_var(env('GH_CORR_DISTINCT_APPROVER', true), FILTER_VALIDATE_BOOL),
        'require_distinct_payout_actor' => filter_var(env('GH_CORR_DISTINCT_PAYOUT', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Android app download (Capacitor APK) — TEST vs LIVE
    |--------------------------------------------------------------------------
    |
    | Artifact lives under storage/app/private/gh-android/ (not public web root).
    | Manifest (current.manifest.json) is the single runtime source for version,
    | package id, SHA-256, and filename. Replace the APK + update the manifest
    | (or run `php artisan gh:publish-android-apk`) — no Blade edits required.
    |
    | LIVE: set channel=live and distribution_enabled=false until a signed,
    | owner-approved APK is published. Never publish TEST/UNVERIFIED/unsigned
    | artifacts on the LIVE channel (service refuses them).
    |
    */
    'android_app' => [
        // Trusted deployment channel: test|live. LIVE must set GH_ANDROID_APP_CHANNEL=live
        // in its own .env (default is test). Never trust the mobile client for this value.
        'channel' => env('GH_ANDROID_APP_CHANNEL', 'test'),
        'distribution_enabled' => filter_var(
            env('GH_ANDROID_APP_DISTRIBUTION_ENABLED', true),
            FILTER_VALIDATE_BOOL
        ),
        'directory' => env('GH_ANDROID_APP_DIRECTORY', 'gh-android'),
        'manifest' => env('GH_ANDROID_APP_MANIFEST', 'gh-android/current.manifest.json'),
        'artifact' => env('GH_ANDROID_APP_ARTIFACT', 'gh-android/current.apk'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile app version policies
    |--------------------------------------------------------------------------
    |
    | Trusted channel = android_app.channel above. Clients cannot select policy
    | by sending a different app_channel. iOS URLs must be HTTPS App Store /
    | TestFlight or hosts listed in ios_url_hosts (enterprise).
    |
    */
    'app_versions' => [
        'ios_url_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GH_IOS_URL_HOSTS', ''))
        ))),
        'check_cache_ttl_seconds' => (int) env('GH_APP_VERSION_CHECK_CACHE_TTL', 900),
    ],
];

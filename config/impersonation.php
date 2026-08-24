<?php

/**
 * Admin "login as user". Enabling the feature in Settings requires a fixed OTP from .env.
 * Once on, any Manager can use Login as (global flag). Leave OTP empty to block enabling.
 */
return [
    'enable_otp' => (string) env('IMPERSONATION_ENABLE_OTP', ''),
];

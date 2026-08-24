<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto clock-out grace (minutes after expected end)
    |--------------------------------------------------------------------------
    |
    | Open punches are force-closed at expected_end + this grace.
    | Clock-out time is that moment (option B: grace hour counts as worked).
    |
    */
    'auto_clock_out_grace_minutes' => (int) env('ATTENDANCE_AUTO_CLOCK_OUT_GRACE_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Stale open punch — force-close on next clock-in (no cron)
    |--------------------------------------------------------------------------
    |
    | When a cross-day open punch exists and the employee opens /clock or
    | clocks in within this many minutes of today's shift start, close the
    | stale segment at that day's expected end (Hostinger-safe; no scheduler).
    |
    */
    'force_close_before_shift_minutes' => (int) env('ATTENDANCE_FORCE_CLOSE_BEFORE_SHIFT_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Clock-in / clock-out photos (T-6.6)
    |--------------------------------------------------------------------------
    |
    | Optional multi-image proof at punch. No count limit — only per-file size
    | / mime. Files live on the `local` disk (outside web root). Host PHP
    | upload_max_filesize / post_max_size must allow the chosen size.
    |
    */
    'photos' => [
        'max_kilobytes' => (int) env('ATTENDANCE_PHOTOS_MAX_KB', 51200), // 50 MB each
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft office geofence (T-7.2)
    |--------------------------------------------------------------------------
    |
    | Punch GPS is required. Outside radius still accepts the punch but warns
    | employee + TL + Admin. Office lat/lng live in settings (Admin UI).
    |
    */
    'location' => [
        'radius_meters' => (int) env('ATTENDANCE_OFFICE_RADIUS_METERS', 1000),
    ],

];

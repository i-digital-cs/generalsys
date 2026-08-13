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

];

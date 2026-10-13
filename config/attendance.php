<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grace policy effective date (attendance workday) — Task 1.6
    |--------------------------------------------------------------------------
    |
    | Y-m-d. New 15-minute arrival/departure grace applies only when the
    | attendance.date is on or after this day. Missing date → historical path.
    |
    */
    'grace_policy_effective_date' => env('ATTENDANCE_GRACE_POLICY_EFFECTIVE_DATE', '2026-10-01'),

    /*
    |--------------------------------------------------------------------------
    | Late-arrival grace (minutes) — Task 1 invariant (from effective date)
    |--------------------------------------------------------------------------
    |
    | Threshold, not a deduction: ≤ N counted late = 0; > N counted = full raw.
    | Also exempts the matching working-hours deficit when within grace.
    |
    */
    'arrival_grace_minutes' => (int) env('ATTENDANCE_ARRIVAL_GRACE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Early-departure grace (minutes) — Task 1 invariant (from effective date)
    |--------------------------------------------------------------------------
    |
    | Independent of arrival grace. Same threshold semantics.
    |
    */
    'departure_grace_minutes' => (int) env('ATTENDANCE_DEPARTURE_GRACE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Auto clock-out grace (minutes after expected end)
    |--------------------------------------------------------------------------
    |
    | Open punches are force-closed at expected_end + this grace.
    | Clock-out time is that moment (option B: grace hour counts as worked).
    | Unrelated to arrival/departure policy grace above.
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

    /*
    |--------------------------------------------------------------------------
    | Clock CTA colors (minutes vs scheduled start or end)
    |--------------------------------------------------------------------------
    |
    | Example start 10:00: green 09:55–10:05; yellow 09:45–09:54 and 10:06–10:30;
    | orange ≤09:44 or ≥10:31. Clock-out uses the same windows vs expected end.
    |
    */
    'cta_colors' => [
        'green_before' => 5,
        'green_after' => 5,
        'yellow_before' => 15,
        'yellow_after' => 30,
        'orange_before' => 16,
        'orange_after' => 31,
    ],

];

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

];

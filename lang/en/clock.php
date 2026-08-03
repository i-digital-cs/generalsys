<?php

return [
    'title' => 'Clock In / Out',
    'status_open' => 'You are clocked in',
    'status_closed' => 'You are not clocked in',
    'shift_type' => 'Shift type',
    'clock_in' => 'Clock In',
    'clock_out' => 'Clock Out',
    'clocked_at' => 'Clocked in at',
    'elapsed' => 'Elapsed',
    'planned_hours' => 'Planned hours',
    'no_ot_hint' => 'Overtime / extra shift appear here only when your team leader assigns them for today.',
    'rotation_hint' => 'Team leader may assign today’s rotation. You can still switch Full time ↔ Online. Overtime / extra need an assignment.',
    'assigned_today' => 'Assigned today: :shift',
    'no_shifts_left' => 'You already recorded every available shift type for today.',
    'flags' => [
        'friday' => 'Friday work',
        'rest_day' => 'Rest-day work',
        'holiday' => 'Official holiday work',
    ],
    'shifts' => [
        'FULL_TIME' => 'Full time',
        'ONLINE_HALF' => 'Online (half day)',
        'ONLINE_FULL' => 'Online (full day)',
        'EXTRA_SHIFT' => 'Extra shift',
        'OVERTIME' => 'Overtime',
    ],
    'flash' => [
        'clocked_in' => 'Clocked in successfully.',
        'clocked_out' => 'Clocked out successfully.',
    ],
    'errors' => [
        'open_exists' => 'You already have an open clock-in. Clock out first.',
        'no_open' => 'No open clock-in to clock out.',
        'assignment_required' => 'A scheduled overtime / extra shift assignment is required.',
        'invalid_shift' => 'Invalid shift type.',
        'shift_already_recorded' => 'You already recorded this shift type today. Choose a different shift (e.g. Online) or wait until tomorrow.',
    ],
];

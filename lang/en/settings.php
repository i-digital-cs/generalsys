<?php

return [
    'title' => 'Settings',
    'defaults_section' => 'System Defaults',
    'default_language' => 'Default Language',
    'default_currency' => 'Default Currency',
    'multipliers_section' => 'Payroll multipliers',
    'multipliers_hint' => 'Applied when generating or recalculating payslips. Overtime 1.50 = time-and-a-half; set 1.00 for straight time.',
    'multipliers' => [
        'friday_full_time' => 'Friday (full-time)',
        'friday_online' => 'Friday (online)',
        'day_off_full_time' => 'Rest-day work (full-time)',
        'day_off_online' => 'Rest-day work (online)',
        'overtime' => 'Overtime / extra work hours',
        'official_holiday_work' => 'Official holiday work',
        'extra_pay' => 'Extra pay from days (إضافي)',
    ],
    'flash' => [
        'updated' => 'Settings updated successfully.',
    ],
    'office_section' => 'Office location (soft geofence)',
    'office_hint' => 'One company office. Punches outside the radius are still accepted, but the employee, team leader, and admin are warned.',
    'office_latitude' => 'Office latitude',
    'office_longitude' => 'Office longitude',
    'office_radius_meters' => 'Allowed radius (meters)',
    'office_radius_hint' => 'Default 1000 m (1 km). Leave lat/lng empty to disable distance checks (GPS is still required on punch).',
];

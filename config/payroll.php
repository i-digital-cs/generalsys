<?php

/**
 * Payroll engine knobs (data, not code branches) — docs/PAYROLL_CALCULATION.md §7–§8.
 */
return [
    'month_divisor' => 30,
    'planned_hours_full_time' => 8.0,
    'multipliers' => [
        'friday_full_time' => 1.0,
        'friday_online' => 0.5,
        'day_off_full_time' => 2.0,
        'day_off_online' => 1.5,
        'overtime' => 1.5,
        'official_holiday_work' => 1.0,
    ],
];

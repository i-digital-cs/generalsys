<?php

/**
 * Interim fallback rates when no active penalty_rules row exists (T-4.1+).
 * Prefer DatabaseSeeder / PenaltyRuleSeeder + Admin UI (T-4.2).
 */
return [
    'EGP' => [
        'LEAVE_PERMISSION' => 1.00,
        'LATE_PERMISSION' => 1.00,
        'LATE_ARRIVAL' => 1.00,
    ],
    'AED' => [
        'LEAVE_PERMISSION' => 2.00,
        'LATE_PERMISSION' => 2.00,
        'LATE_ARRIVAL' => 2.00,
    ],
    'double_penalty_multiplier' => 2.0,
];

<?php

/**
 * Payroll engine knobs (data, not code branches) — docs/PAYROLL_CALCULATION.md §7–§8.
 */
return [
    'month_divisor' => 30,
    'planned_hours_full_time' => 8.0,
    /**
     * Hostinger shared: process generation in the web request (no Supervisor).
     * Set PAYROLL_GENERATE_INLINE=false to only queue jobs.
     */
    'generate_inline' => (bool) env('PAYROLL_GENERATE_INLINE', true),
    /**
     * Excel G10: every calendar day classified. When false (default), Admin may still
     * generate drafts from partial attendance; incomplete days are warned, not blocked.
     */
    'require_full_month_coverage' => (bool) env('PAYROLL_REQUIRE_FULL_MONTH_COVERAGE', false),
    /**
     * Phase 6: when false (default), late/absence/permission/forced/hours EGP are NOT
     * auto-applied — Admin adds manual penalty lines. Facts still stored on the draft.
     * Set PAYROLL_AUTO_PENALTIES=true to restore Excel-style auto money (fixture 2808).
     */
    'auto_penalties' => (bool) env('PAYROLL_AUTO_PENALTIES', false),
    'multipliers' => [
        'friday_full_time' => 1.0,
        'friday_online' => 0.5,
        'day_off_full_time' => 2.0,
        'day_off_online' => 1.5,
        'overtime' => 1.5,
        'official_holiday_work' => 1.0,
        /** Applied when filling Excel «إضافي» from extra_days × day_rate. */
        'extra_pay' => 1.0,
    ],
];

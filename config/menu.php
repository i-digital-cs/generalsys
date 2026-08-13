<?php

/**
 * Role-filtered sidebar menu (docs/UI_FRAMEWORK.md §3, §5).
 *
 * Routes are placeholders until their phase ships — the sidebar renders '#'
 * for any route name that is not registered yet.
 *
 * PRIVACY RULE: payroll/money entries exist under MANAGER and ACCOUNTANT only.
 * TEAM_LEADER must never see money (AGENTS.md §5.1).
 */
return [
    'MANAGER' => [
        ['key' => 'nav.dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.employees', 'route' => 'admin.employees.index', 'icon' => 'bi-people'],
        ['key' => 'nav.shifts', 'route' => 'team-leader.shifts', 'icon' => 'bi-calendar3'],
        ['key' => 'nav.rest_days', 'route' => 'team-leader.rest-days', 'icon' => 'bi-calendar-week'],
        ['key' => 'nav.approvals', 'route' => 'team-leader.approvals', 'icon' => 'bi-check2-square'],
        ['key' => 'nav.reports_review', 'route' => 'team-leader.reports', 'icon' => 'bi-journal-text'],
        ['key' => 'nav.evaluations', 'route' => 'team-leader.evaluations', 'icon' => 'bi-star'],
        ['key' => 'nav.penalties', 'route' => 'admin.config.penalties', 'icon' => 'bi-slash-circle'],
        // Holidays CRUD UI not shipped yet (table + dashboard markers exist).
        // ['key' => 'nav.holidays', 'route' => 'admin.config.holidays', 'icon' => 'bi-calendar-event'],
        ['key' => 'nav.payroll', 'route' => 'admin.payroll.index', 'icon' => 'bi-cash-stack'],
        ['key' => 'nav.advances', 'route' => 'admin.advances.index', 'icon' => 'bi-cash-coin'],
        ['key' => 'nav.annual_reports', 'route' => 'admin.reports.annual', 'icon' => 'bi-bar-chart'],
        ['key' => 'nav.settings', 'route' => 'admin.settings', 'icon' => 'bi-gear'],
    ],

    'ACCOUNTANT' => [
        ['key' => 'nav.dashboard', 'route' => 'employee.dashboard', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.clock', 'route' => 'employee.clock', 'icon' => 'bi-clock'],
        ['key' => 'nav.profile', 'route' => 'employee.profile', 'icon' => 'bi-person'],
        ['key' => 'nav.daily_report', 'route' => 'employee.report', 'icon' => 'bi-journal-text'],
        ['key' => 'nav.requests', 'route' => 'employee.requests', 'icon' => 'bi-inbox'],
        ['key' => 'nav.salary', 'route' => 'employee.salary', 'icon' => 'bi-wallet2'],
        ['key' => 'nav.evaluation', 'route' => 'employee.evaluation', 'icon' => 'bi-star'],
        ['key' => 'nav.payroll', 'route' => 'admin.payroll.index', 'icon' => 'bi-cash-stack'],
        ['key' => 'nav.advances', 'route' => 'admin.advances.index', 'icon' => 'bi-cash-coin'],
    ],

    'TEAM_LEADER' => [
        ['key' => 'nav.dashboard', 'route' => 'team-leader.dashboard', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.clock', 'route' => 'employee.clock', 'icon' => 'bi-clock'],
        ['key' => 'nav.daily_report', 'route' => 'employee.report', 'icon' => 'bi-journal-check'],
        ['key' => 'nav.requests', 'route' => 'employee.requests', 'icon' => 'bi-inbox'],
        ['key' => 'nav.shifts', 'route' => 'team-leader.shifts', 'icon' => 'bi-calendar3'],
        ['key' => 'nav.rest_days', 'route' => 'team-leader.rest-days', 'icon' => 'bi-calendar-week'],
        ['key' => 'nav.approvals', 'route' => 'team-leader.approvals', 'icon' => 'bi-check2-square'],
        ['key' => 'nav.reports_review', 'route' => 'team-leader.reports', 'icon' => 'bi-journal-text'],
        ['key' => 'nav.evaluations', 'route' => 'team-leader.evaluations', 'icon' => 'bi-star'],
        ['key' => 'nav.evaluation', 'route' => 'employee.evaluation', 'icon' => 'bi-star-half'],
    ],

    'EMPLOYEE' => [
        ['key' => 'nav.dashboard', 'route' => 'employee.dashboard', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.clock', 'route' => 'employee.clock', 'icon' => 'bi-clock'],
        ['key' => 'nav.profile', 'route' => 'employee.profile', 'icon' => 'bi-person'],
        ['key' => 'nav.daily_report', 'route' => 'employee.report', 'icon' => 'bi-journal-text'],
        ['key' => 'nav.requests', 'route' => 'employee.requests', 'icon' => 'bi-inbox'],
        ['key' => 'nav.salary', 'route' => 'employee.salary', 'icon' => 'bi-wallet2'],
        ['key' => 'nav.advances', 'route' => 'employee.advances', 'icon' => 'bi-cash-coin'],
        ['key' => 'nav.evaluation', 'route' => 'employee.evaluation', 'icon' => 'bi-star'],
    ],
];

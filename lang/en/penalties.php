<?php

return [
    'title' => 'Penalty Rules',
    'subtitle' => 'Per-currency rates used when leave/late requests are rejected or forced. Team leaders never see these amounts.',
    'list' => 'Rules',
    'create' => 'Add rule',
    'edit' => 'Edit rule',
    'empty_title' => 'No penalty rules yet',
    'empty_text' => 'Seed defaults or add a rule to start.',
    'fields' => [
        'name' => 'Name',
        'type' => 'Type',
        'currency' => 'Currency',
        'amount_per_unit' => 'Amount per unit',
        'unit' => 'Unit',
        'double_penalty_multiplier' => 'Force multiplier',
        'is_active' => 'Active',
        'status' => 'Status',
    ],
    'types' => [
        'LATE_ARRIVAL' => 'Late arrival',
        'LEAVE_PERMISSION' => 'Leave permission',
        'LATE_PERMISSION' => 'Late permission',
        'EXCUSED_ABSENCE' => 'Excused absence',
        'UNEXCUSED_ABSENCE' => 'Unexcused absence',
    ],
    'units' => [
        'MINUTE' => 'Per minute',
        'HOUR' => 'Per hour',
        'DAY' => 'Per day',
    ],
    'active' => 'Active',
    'inactive' => 'Inactive',
    'flash' => [
        'created' => 'Penalty rule created.',
        'updated' => 'Penalty rule updated.',
    ],
];

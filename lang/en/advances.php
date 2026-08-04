<?php

return [
    'title' => 'Advances',
    'subtitle' => 'Salary advances (سلف). Monthly deductions are pulled into draft payroll and remaining balance decreases on approve.',
    'list' => 'Advances',
    'create' => 'Add advance',
    'edit' => 'Edit advance',
    'my_title' => 'My advances',
    'my_subtitle' => 'Your salary advances and remaining balance.',
    'empty_title' => 'No advances yet',
    'empty_text' => 'Create an advance for an employee to deduct it on the next payroll.',
    'my_empty_title' => 'No advances',
    'my_empty_text' => 'You have no recorded salary advances.',
    'fields' => [
        'employee' => 'Employee',
        'amount' => 'Amount',
        'date_given' => 'Date given',
        'deduction_amount_monthly' => 'Monthly deduction',
        'remaining_amount' => 'Remaining',
        'status' => 'Status',
    ],
    'status' => [
        'ACTIVE' => 'Active',
        'FULLY_DEDUCTED' => 'Fully deducted',
    ],
    'flash' => [
        'created' => 'Advance created.',
        'updated' => 'Advance updated.',
    ],
];

<?php

/**
 * Mohra Gross sidebar page keys (Admin assigns per non-Manager employee).
 * Labels: employees.gross_pages.{key}
 */
return [
    'keys' => [
        'items',
        'categories',
        'attributes',
        'stock',
        'suppliers',
        'purchases',
        'customers',
        'sales',
        'sales_stats',
        'shipping',
        'treasury',
        'expense_types',
        'receipt_purposes',
    ],

    /** Default pages for existing/new Sales (with Product module). */
    'defaults_sales' => [
        'items',
        'stock',
        'customers',
        'sales',
        'shipping',
    ],

    /** Default pages for existing Accountants (preserve prior master/finance access). */
    'defaults_accountant' => [
        'items',
        'categories',
        'attributes',
        'stock',
        'suppliers',
        'purchases',
        'customers',
        'sales',
        'sales_stats',
        'shipping',
        'treasury',
        'expense_types',
        'receipt_purposes',
    ],
];

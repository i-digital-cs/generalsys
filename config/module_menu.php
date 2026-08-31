<?php

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryVoucher;

/**
 * Sidebars for Service and Product (same shell as HR, different items).
 */
return [
    'service' => [
        ['key' => 'nav.dashboard', 'route' => 'portal.service', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.valuation', 'route' => 'valuation.sheets.index', 'params' => ['module' => 'service'], 'icon' => 'bi-clipboard-data'],
    ],
    'product' => [
        ['key' => 'nav.dashboard', 'route' => 'portal.product', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.valuation', 'route' => 'valuation.sheets.index', 'params' => ['module' => 'product'], 'icon' => 'bi-clipboard-data'],
        ['key' => 'nav.gross_items', 'route' => 'gross.items.index', 'icon' => 'bi-box-seam'],
        ['key' => 'nav.gross_categories', 'route' => 'gross.categories.index', 'icon' => 'bi-tags'],
        ['key' => 'nav.gross_attributes', 'route' => 'gross.attributes.index', 'icon' => 'bi-palette'],
        ['key' => 'nav.gross_stock', 'route' => 'gross.stock.index', 'icon' => 'bi-boxes'],
        ['key' => 'nav.gross_stock_movements', 'route' => 'gross.stock.movements', 'icon' => 'bi-arrow-left-right'],
        ['key' => 'nav.gross_customers', 'route' => 'gross.customers.index', 'icon' => 'bi-people'],
        ['key' => 'nav.gross_suppliers', 'route' => 'gross.suppliers.index', 'icon' => 'bi-truck-flatbed'],
        ['key' => 'nav.gross_purchases', 'route' => 'gross.purchases.index', 'icon' => 'bi-cart-plus'],
        ['key' => 'nav.gross_shipping', 'route' => 'gross.shipping.index', 'icon' => 'bi-truck'],
        ['key' => 'nav.gross_sales', 'route' => 'gross.sales.index', 'icon' => 'bi-receipt'],
        ['key' => 'nav.gross_collections', 'route' => 'gross.sales.collections', 'icon' => 'bi-cash-coin'],
        [
            'key' => 'nav.gross_treasury',
            'route' => 'gross.treasury.index',
            'icon' => 'bi-wallet2',
            'can' => ['viewAny', GrossTreasuryVoucher::class],
        ],
        [
            'key' => 'nav.gross_expense_types',
            'route' => 'gross.expense-types.index',
            'icon' => 'bi-tags',
            'can' => ['viewAny', GrossExpenseType::class],
        ],
    ],
];

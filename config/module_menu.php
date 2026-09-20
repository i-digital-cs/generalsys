<?php

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryVoucher;

/**
 * Sidebars for Service and Product (same shell as HR, different items).
 *
 * Product (Mohra Gross) groups: Overview → Catalog → Inventory → Purchasing → Sales → Finance.
 * Items with type "heading" render as section labels (no link).
 */
return [
    'service' => [
        ['key' => 'nav.dashboard', 'route' => 'portal.service', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.valuation', 'route' => 'valuation.sheets.index', 'params' => ['module' => 'service'], 'icon' => 'bi-clipboard-data'],
    ],
    'product' => [
        ['key' => 'nav.dashboard', 'route' => 'portal.product', 'icon' => 'bi-speedometer2'],
        ['key' => 'nav.valuation', 'route' => 'valuation.sheets.index', 'params' => ['module' => 'product'], 'icon' => 'bi-clipboard-data'],

        ['type' => 'heading', 'key' => 'nav.gross_section_catalog'],
        ['key' => 'nav.gross_items', 'route' => 'gross.items.index', 'icon' => 'bi-box-seam'],
        ['key' => 'nav.gross_categories', 'route' => 'gross.categories.index', 'icon' => 'bi-tags'],
        ['key' => 'nav.gross_attributes', 'route' => 'gross.attributes.index', 'icon' => 'bi-palette'],

        ['type' => 'heading', 'key' => 'nav.gross_section_inventory'],
        ['key' => 'nav.gross_stock', 'route' => 'gross.stock.index', 'icon' => 'bi-boxes'],
        ['key' => 'nav.gross_stock_movements', 'route' => 'gross.stock.movements', 'icon' => 'bi-arrow-left-right'],

        ['type' => 'heading', 'key' => 'nav.gross_section_purchasing'],
        ['key' => 'nav.gross_suppliers', 'route' => 'gross.suppliers.index', 'icon' => 'bi-truck-flatbed'],
        ['key' => 'nav.gross_purchases', 'route' => 'gross.purchases.index', 'icon' => 'bi-cart-plus'],

        ['type' => 'heading', 'key' => 'nav.gross_section_sales'],
        ['key' => 'nav.gross_customers', 'route' => 'gross.customers.index', 'icon' => 'bi-people'],
        ['key' => 'nav.gross_sales', 'route' => 'gross.sales.index', 'icon' => 'bi-receipt'],
        ['key' => 'nav.gross_sales_stats', 'route' => 'gross.sales.stats', 'icon' => 'bi-bar-chart-line'],
        ['key' => 'nav.gross_collections', 'route' => 'gross.sales.collections', 'icon' => 'bi-cash-coin'],
        ['key' => 'nav.gross_shipping', 'route' => 'gross.shipping.index', 'icon' => 'bi-truck'],
        ['type' => 'heading', 'key' => 'nav.gross_section_fulfillment'],
        ['key' => 'nav.gross_fulfillment_preparing', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'preparing'], 'icon' => 'bi-hourglass-split'],
        ['key' => 'nav.gross_fulfillment_ready', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'ready'], 'icon' => 'bi-box-seam'],
        ['key' => 'nav.gross_fulfillment_with_courier', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'with_courier'], 'icon' => 'bi-bicycle'],
        ['key' => 'nav.gross_fulfillment_delivered', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'delivered'], 'icon' => 'bi-check2-circle'],
        ['key' => 'nav.gross_fulfillment_returned', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'returned'], 'icon' => 'bi-arrow-return-left'],

        [
            'type' => 'heading',
            'key' => 'nav.gross_section_finance',
            'can' => ['viewAny', GrossTreasuryVoucher::class],
        ],
        [
            'key' => 'nav.gross_treasury',
            'route' => 'gross.treasury.index',
            'icon' => 'bi-wallet2',
            'can' => ['viewAny', GrossTreasuryVoucher::class],
        ],
        [
            'key' => 'nav.gross_expense_types',
            'route' => 'gross.expense-types.index',
            'icon' => 'bi-ui-checks-grid',
            'can' => ['viewAny', GrossExpenseType::class],
        ],
    ],
];

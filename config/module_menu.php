<?php

/**
 * Sidebars for Service and Product (same shell as HR, different items).
 *
 * Product (Mohra Gross) groups: Overview → Catalog → Inventory → Purchasing → Sales → Finance.
 * Items with type "heading" render as section labels (no link).
 * Product link items use `page` keys from config/gross_pages.php (Manager always; others assigned).
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
        ['key' => 'nav.gross_items', 'route' => 'gross.items.index', 'icon' => 'bi-box-seam', 'page' => 'items'],
        ['key' => 'nav.gross_categories', 'route' => 'gross.categories.index', 'icon' => 'bi-tags', 'page' => 'categories'],
        ['key' => 'nav.gross_attributes', 'route' => 'gross.attributes.index', 'icon' => 'bi-palette', 'page' => 'attributes'],

        ['type' => 'heading', 'key' => 'nav.gross_section_inventory'],
        ['key' => 'nav.gross_stock', 'route' => 'gross.stock.index', 'icon' => 'bi-boxes', 'page' => 'stock'],
        ['key' => 'nav.gross_stock_movements', 'route' => 'gross.stock.movements', 'icon' => 'bi-arrow-left-right', 'page' => 'stock'],

        ['type' => 'heading', 'key' => 'nav.gross_section_purchasing'],
        ['key' => 'nav.gross_suppliers', 'route' => 'gross.suppliers.index', 'icon' => 'bi-truck-flatbed', 'page' => 'suppliers'],
        ['key' => 'nav.gross_purchases', 'route' => 'gross.purchases.index', 'icon' => 'bi-cart-plus', 'page' => 'purchases'],

        ['type' => 'heading', 'key' => 'nav.gross_section_sales'],
        ['key' => 'nav.gross_customers', 'route' => 'gross.customers.index', 'icon' => 'bi-people', 'page' => 'customers'],
        ['key' => 'nav.gross_sales', 'route' => 'gross.sales.index', 'icon' => 'bi-receipt', 'page' => 'sales'],
        ['key' => 'nav.gross_sales_stats', 'route' => 'gross.sales.stats', 'icon' => 'bi-bar-chart-line', 'page' => 'sales_stats'],
        ['key' => 'nav.gross_collections', 'route' => 'gross.sales.collections', 'icon' => 'bi-cash-coin', 'page' => 'sales'],
        ['key' => 'nav.gross_shipping', 'route' => 'gross.shipping.index', 'icon' => 'bi-truck', 'page' => 'shipping'],
        ['type' => 'heading', 'key' => 'nav.gross_section_fulfillment'],
        ['key' => 'nav.gross_fulfillment_preparing', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'preparing'], 'icon' => 'bi-hourglass-split', 'page' => 'sales'],
        ['key' => 'nav.gross_fulfillment_ready', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'ready'], 'icon' => 'bi-box-seam', 'page' => 'sales'],
        ['key' => 'nav.gross_fulfillment_with_courier', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'with_courier'], 'icon' => 'bi-bicycle', 'page' => 'sales'],
        ['key' => 'nav.gross_fulfillment_delivered', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'delivered'], 'icon' => 'bi-check2-circle', 'page' => 'sales'],
        ['key' => 'nav.gross_fulfillment_returned', 'route' => 'gross.sales.fulfillment', 'params' => ['queue' => 'returned'], 'icon' => 'bi-arrow-return-left', 'page' => 'sales'],

        ['type' => 'heading', 'key' => 'nav.gross_section_finance'],
        ['key' => 'nav.gross_treasury', 'route' => 'gross.treasury.index', 'icon' => 'bi-wallet2', 'page' => 'treasury'],
        ['key' => 'nav.gross_expense_types', 'route' => 'gross.expense-types.index', 'icon' => 'bi-ui-checks-grid', 'page' => 'expense_types'],
        ['key' => 'nav.gross_receipt_purposes', 'route' => 'gross.receipt-purposes.index', 'icon' => 'bi-cash-coin', 'page' => 'receipt_purposes'],
    ],
];

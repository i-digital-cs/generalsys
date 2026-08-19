<?php

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
    ],
];

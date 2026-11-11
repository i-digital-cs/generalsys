<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$files = [
    'resources/views/admin/employees/index.blade.php',
    'resources/views/admin/payroll/index.blade.php',
    'resources/views/admin/advances/index.blade.php',
    'resources/views/employee/salary/index.blade.php',
    'resources/views/employee/advances/index.blade.php',
    'resources/views/team-leader/approvals/index.blade.php',
    'resources/views/team-leader/evaluations/index.blade.php',
    'resources/views/team-leader/shifts/index.blade.php',
    'resources/views/valuation/sheets/index.blade.php',
    'resources/views/gh/customers/index.blade.php',
    'resources/views/gh/zones/index.blade.php',
    'resources/views/gh/categories/index.blade.php',
    'resources/views/gh/products/index.blade.php',
    'resources/views/gh/treatments/index.blade.php',
    'resources/views/gh/offerings/index.blade.php',
    'resources/views/gh/contracts/index.blade.php',
    'resources/views/gh/invoices/index.blade.php',
    'resources/views/gh/payments/index.blade.php',
    'resources/views/gh/credit-notes/index.blade.php',
    'resources/views/gh/refunds/index.blade.php',
    'resources/views/gh/financial-corrections/index.blade.php',
    'resources/views/gh/dashboard/index.blade.php',
];

foreach ($files as $f) {
    if (! is_file($f)) {
        echo "missing {$f}\n";

        continue;
    }
    $c = file_get_contents($f);
    if (! preg_match("/@include\\('([^']+)\\._mode-cards'\\)/", $c, $m)) {
        echo "skip {$f}\n";

        continue;
    }
    $base = $m[1];
    $title = "''";
    if (preg_match("/@section\\('title'\\s*,\\s*(.+)\\)/", $c, $tm)) {
        $title = trim($tm[1]);
    }
    $extends = 'layouts.app';
    if (preg_match("/@extends\\('([^']+)'\\)/", $c, $em)) {
        $extends = $em[1];
    }

    $out = <<<BLADE
@extends('{$extends}')

@section('title', {$title})

@section('content')
    @php
        \$listViewIsCards = \\App\\Support\\Ui\\ListViewMode::isCards(auth()->user());
    @endphp

    @if (\$listViewIsCards)
        @include('{$base}._mode-cards')
    @else
        @include('{$base}._mode-legacy')
    @endif
@endsection

BLADE;
    file_put_contents($f, $out);
    echo "normalized {$f}\n";
}

file_put_contents('resources/views/valuation/sheets/_list-table.blade.php', <<<'BLADE'
@php
    $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user());
@endphp
@if ($listViewIsCards)
    @include('valuation.sheets._list-table-cards')
@else
    @include('valuation.sheets._list-table-legacy')
@endif

BLADE
);

echo "done\n";

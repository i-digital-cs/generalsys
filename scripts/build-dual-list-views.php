<?php

/**
 * One-shot TEST helper: split converted list indexes into legacy (HEAD) + cards (working tree).
 * Does not touch git. Run from project root: php scripts/build-dual-list-views.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

/** @var array<string, array{path:string, page:string, title?:string}> */
$pages = [
    'admin/employees/index' => ['path' => 'resources/views/admin/employees/index.blade.php', 'page' => 'admin.employees'],
    'admin/payroll/index' => ['path' => 'resources/views/admin/payroll/index.blade.php', 'page' => 'admin.payroll'],
    'admin/advances/index' => ['path' => 'resources/views/admin/advances/index.blade.php', 'page' => 'admin.advances'],
    'employee/salary/index' => ['path' => 'resources/views/employee/salary/index.blade.php', 'page' => 'employee.salary'],
    'employee/advances/index' => ['path' => 'resources/views/employee/advances/index.blade.php', 'page' => 'employee.advances'],
    'employee/evaluation' => ['path' => 'resources/views/employee/evaluation.blade.php', 'page' => 'employee.evaluation'],
    'team-leader/evaluations/index' => ['path' => 'resources/views/team-leader/evaluations/index.blade.php', 'page' => 'team-leader.evaluations'],
    'team-leader/shifts/index' => ['path' => 'resources/views/team-leader/shifts/index.blade.php', 'page' => 'team-leader.shifts'],
    'team-leader/approvals/index' => ['path' => 'resources/views/team-leader/approvals/index.blade.php', 'page' => 'team-leader.approvals'],
    'valuation/sheets/index' => ['path' => 'resources/views/valuation/sheets/index.blade.php', 'page' => 'valuation.sheets'],
    'gh/customers/index' => ['path' => 'resources/views/gh/customers/index.blade.php', 'page' => 'gh.customers'],
    'gh/zones/index' => ['path' => 'resources/views/gh/zones/index.blade.php', 'page' => 'gh.zones'],
    'gh/categories/index' => ['path' => 'resources/views/gh/categories/index.blade.php', 'page' => 'gh.categories'],
    'gh/products/index' => ['path' => 'resources/views/gh/products/index.blade.php', 'page' => 'gh.products'],
    'gh/treatments/index' => ['path' => 'resources/views/gh/treatments/index.blade.php', 'page' => 'gh.treatments'],
    'gh/offerings/index' => ['path' => 'resources/views/gh/offerings/index.blade.php', 'page' => 'gh.offerings'],
    'gh/contracts/index' => ['path' => 'resources/views/gh/contracts/index.blade.php', 'page' => 'gh.contracts'],
    'gh/invoices/index' => ['path' => 'resources/views/gh/invoices/index.blade.php', 'page' => 'gh.invoices'],
    'gh/payments/index' => ['path' => 'resources/views/gh/payments/index.blade.php', 'page' => 'gh.payments'],
    'gh/credit-notes/index' => ['path' => 'resources/views/gh/credit-notes/index.blade.php', 'page' => 'gh.credit-notes'],
    'gh/refunds/index' => ['path' => 'resources/views/gh/refunds/index.blade.php', 'page' => 'gh.refunds'],
    'gh/financial-corrections/index' => ['path' => 'resources/views/gh/financial-corrections/index.blade.php', 'page' => 'gh.financial-corrections'],
    'gh/dashboard/index' => ['path' => 'resources/views/gh/dashboard/index.blade.php', 'page' => 'gh.dashboard'],
];

function gitShow(string $path): string
{
    $cmd = 'git show HEAD:'.escapeshellarg($path);
    exec($cmd, $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("git show failed for {$path}");
    }

    return implode("\n", $out)."\n";
}

function extractLayoutMeta(string $blade): array
{
    $extends = 'layouts.app';
    if (preg_match("/@extends\\('([^']+)'\\)/", $blade, $m)) {
        $extends = $m[1];
    }

    $title = null;
    if (preg_match("/@section\\('title'\\s*,\\s*(.+)\\)/", $blade, $m)) {
        $title = trim($m[1]);
    }

    return compact('extends', 'title');
}

function extractContentSection(string $blade): string
{
    if (! preg_match("/@section\\('content'\\)\\s*/", $blade, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('No @section(content) found');
    }
    $start = $m[0][1] + strlen($m[0][0]);
    $rest = substr($blade, $start);
    // Find matching @endsection at depth 0 (ignore nested @section/@endsection pairs)
    $depth = 1;
    $offset = 0;
    $len = strlen($rest);
    while ($offset < $len) {
        if (preg_match('/@(section|endsection|stop)\\b/', $rest, $mm, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            break;
        }
        $token = $mm[1][0];
        $pos = $mm[0][1];
        if ($token === 'section') {
            $depth++;
        } else {
            $depth--;
            if ($depth === 0) {
                return rtrim(substr($rest, 0, $pos))."\n";
            }
        }
        $offset = $pos + strlen($mm[0][0]);
    }

    throw new RuntimeException('Unbalanced @section(content)');
}

function writeFile(string $path, string $contents): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $contents);
    echo "Wrote {$path}\n";
}

foreach ($pages as $key => $cfg) {
    $path = $cfg['path'];
    $page = $cfg['page'];
    $abs = $root.'/'.$path;
    if (! is_file($abs)) {
        echo "SKIP missing working file {$path}\n";

        continue;
    }

    $cardsFull = file_get_contents($abs);
    $legacyFull = gitShow($path);
    $meta = extractLayoutMeta($legacyFull !== '' ? $legacyFull : $cardsFull);

    $dir = dirname($abs);
    $legacyPartial = $dir.'/_mode-legacy.blade.php';
    $cardsPartial = $dir.'/_mode-cards.blade.php';

    // Skip re-extraction if already dual-wrapped (idempotent for cards source)
    if (str_contains($cardsFull, 'list-view-toggle') && is_file($legacyPartial) && is_file($cardsPartial)) {
        echo "SKIP already dual {$path}\n";

        continue;
    }

    $legacyBody = extractContentSection($legacyFull);
    $cardsBody = extractContentSection($cardsFull);

    writeFile($legacyPartial, "{{-- Legacy baseline: git HEAD for {$path} --}}\n".$legacyBody);
    writeFile($cardsPartial, "{{-- Modern card presentation (working tree) --}}\n".$cardsBody);

    $titleLine = $meta['title'] !== null
        ? "@section('title', {$meta['title']})\n"
        : "@section('title', '')\n";

    // Blade view names use dots under resources/views
    $viewBase = str_replace('/', '.', dirname(str_replace('resources/views/', '', $path)));
    if (str_ends_with($path, 'evaluation.blade.php')) {
        $viewBase = 'employee';
    }

    $wrapper = <<<BLADE
@extends('{$meta['extends']}')

{$titleLine}
@section('content')
    @php
        \$listViewMode = \\App\\Support\\Ui\\ListViewMode::for(auth()->user(), '{$page}');
        \$listViewIsCards = \$listViewMode === \\App\\Support\\Ui\\ListViewMode::CARDS;
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
        <x-ui.list-view-toggle page="{$page}" />
    </div>

    @if (\$listViewIsCards)
        @include('{$viewBase}._mode-cards')
    @else
        @include('{$viewBase}._mode-legacy')
    @endif
@endsection

BLADE;

    writeFile($abs, $wrapper);
}

// Valuation list partial: dual inside _list-table include used by index
$listTable = 'resources/views/valuation/sheets/_list-table.blade.php';
if (is_file($root.'/'.$listTable) && ! is_file($root.'/resources/views/valuation/sheets/_list-table-legacy.blade.php')) {
    $cards = file_get_contents($root.'/'.$listTable);
    $legacy = gitShow($listTable);
    writeFile($root.'/resources/views/valuation/sheets/_list-table-legacy.blade.php', $legacy);
    writeFile($root.'/resources/views/valuation/sheets/_list-table-cards.blade.php', $cards);
    writeFile($root.'/'.$listTable, <<<'BLADE'
@php
    $listViewIsCards = \App\Support\Ui\ListViewMode::isCards(auth()->user(), 'valuation.sheets');
@endphp
@if ($listViewIsCards)
    @include('valuation.sheets._list-table-cards')
@else
    @include('valuation.sheets._list-table-legacy')
@endif

BLADE
    );
    echo "Split valuation _list-table\n";
}

echo "Done.\n";

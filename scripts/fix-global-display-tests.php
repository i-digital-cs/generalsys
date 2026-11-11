<?php

declare(strict_types=1);

$files = [
    'tests/Feature/GeneralHouse/CardUiConversionTest.php',
    'tests/Feature/Hr/HrCardUiConversionTest.php',
    'tests/Feature/Employee/EvaluationCardUiTest.php',
    'tests/Feature/TableFilterUiTest.php',
    'tests/Feature/Valuation/ValuationModuleTest.php',
];

foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace(
        '/ListViewMode::put\(\$(\w+),\s*[\'"][^\'"]+[\'"],\s*ListViewMode::CARDS\)/',
        'ListViewMode::put($$1, ListViewMode::CARDS)',
        $c
    );
    $c = preg_replace(
        '/private function enableCards\(User \$user, string \$page\): void\s*\{\s*ListViewMode::put\(\$user, \$page, ListViewMode::CARDS\);\s*\}/s',
        "private function enableCards(User \$user): void\n    {\n        ListViewMode::put(\$user, ListViewMode::CARDS);\n    }",
        $c
    );
    $c = preg_replace('/\$this->enableCards\(\$(\w+),\s*[\'"][^\'"]+[\'"]\)/', '$this->enableCards($$1)', $c);
    // Remove unused $page in loop if present
    $c = preg_replace(
        '/\$page = str_replace\(\'\.index\', \'\', \$routeName\);\s*\$this->enableCards\(\$manager\);/',
        '$this->enableCards($manager);',
        $c
    );
    file_put_contents($f, $c);
    echo "updated {$f}\n";
}

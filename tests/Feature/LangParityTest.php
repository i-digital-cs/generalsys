<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gate 1.7 / 5.3 — every lang file has AR + EN key parity; no empty values.
 */
class LangParityTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $flat = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $fullKey);
            } else {
                $flat[$fullKey] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    private function langFiles(): array
    {
        $files = glob(lang_path('en/*.php')) ?: [];

        return array_values(array_map(
            fn (string $path): string => basename($path, '.php'),
            $files,
        ));
    }

    public function test_ar_and_en_lang_files_have_identical_keys(): void
    {
        $enFiles = $this->langFiles();
        sort($enFiles);
        $arFiles = array_values(array_map(
            fn (string $path): string => basename($path, '.php'),
            glob(lang_path('ar/*.php')) ?: [],
        ));
        sort($arFiles);

        $this->assertSame(
            $enFiles,
            $arFiles,
            'lang/en and lang/ar must contain the same PHP files.',
        );

        foreach ($enFiles as $file) {
            $ar = $this->flatten(require lang_path("ar/{$file}.php"));
            $en = $this->flatten(require lang_path("en/{$file}.php"));

            $this->assertSame(
                array_keys($en),
                array_keys($ar),
                "Key mismatch in {$file}.php: missing in ar: ["
                .implode(', ', array_diff(array_keys($en), array_keys($ar)))
                .'] / missing in en: ['
                .implode(', ', array_diff(array_keys($ar), array_keys($en))).']'
            );

            foreach ($en as $key => $value) {
                $this->assertNotSame('', trim($value), "Empty EN value: {$file}.{$key}");
                $this->assertNotSame('', trim($ar[$key] ?? ''), "Empty AR value: {$file}.{$key}");
            }
        }
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gate 1.7 — every shipped screen must have AR + EN keys.
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
                $flat[$fullKey] = $value;
            }
        }

        return $flat;
    }

    public function test_ar_and_en_lang_files_have_identical_keys(): void
    {
        $files = ['auth', 'general', 'nav', 'employees', 'dashboard', 'documents', 'settings', 'profile', 'validation', 'clock', 'shifts'];

        foreach ($files as $file) {
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
        }
    }
}

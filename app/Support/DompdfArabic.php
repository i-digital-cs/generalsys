<?php

namespace App\Support;

use ArPHP\I18N\Arabic;

/**
 * Dompdf does not shape/join Arabic. Pre-convert to presentation forms for LTR PDF engines.
 *
 * Mixed Latin, digits, and brackets must stay out of the Arabic glyph pass — otherwise
 * ArPHP folds `(0–100)` into the Arabic run and mirrors it to `)٠)–١٠٠`.
 */
final class DompdfArabic
{
    private static ?Arabic $engine = null;

    public static function glyphs(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        $holders = [];
        $protected = preg_replace_callback(
            '/[^\p{Arabic}\s]+/u',
            static function (array $match) use (&$holders): string {
                $token = '@@PH'.count($holders).'@@';
                $holders[$token] = $match[0];

                return $token;
            },
            $text,
        );

        if (! is_string($protected)) {
            return self::engine()->utf8Glyphs($text, 10000, false, false);
        }

        $shaped = self::engine()->utf8Glyphs($protected, 10000, false, false);

        if ($holders === []) {
            return $shaped;
        }

        return strtr($shaped, $holders);
    }

    private static function engine(): Arabic
    {
        return self::$engine ??= new Arabic;
    }
}

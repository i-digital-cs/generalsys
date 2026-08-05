<?php

namespace App\Support;

use ArPHP\I18N\Arabic;

/**
 * Dompdf does not shape/join Arabic. Pre-convert to presentation forms for LTR PDF engines.
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

        return self::engine()->utf8Glyphs($text);
    }

    private static function engine(): Arabic
    {
        return self::$engine ??= new Arabic;
    }
}

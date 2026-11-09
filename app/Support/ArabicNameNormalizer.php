<?php

namespace App\Support;

/**
 * Normalize Arabic person names for tolerant matching (alef / teh marbuta / yeh variants).
 */
final class ArabicNameNormalizer
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        $map = [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ى' => 'ي',
            'ة' => 'ه',
            'ؤ' => 'و',
            'ئ' => 'ي',
            'ٱ' => 'ا',
        ];

        $value = strtr($value, $map);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

        return $value;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        $hay = self::normalize($haystack);
        $nee = self::normalize($needle);

        if ($nee === '') {
            return false;
        }

        if (mb_strpos($hay, $nee, 0, 'UTF-8') !== false) {
            return true;
        }

        // Tolerate missing / extra spaces ("عبدالحليم" vs "عبد الحليم").
        $hayCompact = preg_replace('/\s+/u', '', $hay) ?? $hay;
        $neeCompact = preg_replace('/\s+/u', '', $nee) ?? $nee;

        return $neeCompact !== ''
            && mb_strpos($hayCompact, $neeCompact, 0, 'UTF-8') !== false;
    }
}

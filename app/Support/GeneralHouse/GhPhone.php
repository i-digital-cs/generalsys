<?php

namespace App\Support\GeneralHouse;

/**
 * Normalize UAE-leaning phone strings for duplicate matching.
 * Comparison key keeps digits only; leading 00 → country code digits.
 */
final class GhPhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Local UAE 05XXXXXXXX → 9715XXXXXXXX
        if (preg_match('/^0(5\d{8})$/', $digits, $m)) {
            return '971'.$m[1];
        }
        if (preg_match('/^(5\d{8})$/', $digits)) {
            return '971'.$digits;
        }

        return $digits;
    }

    public static function display(?string $phone): string
    {
        return trim((string) $phone);
    }
}

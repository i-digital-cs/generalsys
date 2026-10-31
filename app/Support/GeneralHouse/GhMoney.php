<?php

namespace App\Support\GeneralHouse;

/**
 * Decimal money helpers for GH invoice math (bcmath, ROUND_HALF_UP to 2 dp).
 */
final class GhMoney
{
    public static function of(int|float|string $value): string
    {
        if (is_int($value)) {
            return number_format($value, 2, '.', '');
        }
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        $raw = trim((string) $value);
        if ($raw === '' || ! is_numeric($raw)) {
            return '0.00';
        }

        return self::roundHalfUp($raw, 2);
    }

    public static function roundHalfUp(string $amount, int $scale = 2): string
    {
        $amount = self::normalize($amount);
        if ($scale < 0) {
            $scale = 0;
        }

        $negative = str_starts_with($amount, '-');
        if ($negative) {
            $amount = substr($amount, 1);
        }

        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($amount, $factor, $scale + 4);
        $whole = bcadd($scaled, '0', 0);
        $frac = bcsub($scaled, $whole, $scale + 4);

        if (bccomp($frac, '0.5', $scale + 4) >= 0) {
            $whole = bcadd($whole, '1', 0);
        }

        $result = bcdiv($whole, $factor, $scale);
        if ($negative && bccomp($result, '0', $scale) !== 0) {
            $result = '-'.$result;
        }

        return self::formatScale($result, $scale);
    }

    public static function add(string $a, string $b, int $scale = 8): string
    {
        return bcadd(self::normalize($a), self::normalize($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = 8): string
    {
        return bcsub(self::normalize($a), self::normalize($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = 8): string
    {
        return bcmul(self::normalize($a), self::normalize($b), $scale);
    }

    public static function div(string $a, string $b, int $scale = 8): string
    {
        if (bccomp(self::normalize($b), '0', $scale) === 0) {
            return self::formatScale('0', $scale);
        }

        return bcdiv(self::normalize($a), self::normalize($b), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = 8): int
    {
        return bccomp(self::normalize($a), self::normalize($b), $scale);
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    private static function normalize(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '' || ! is_numeric($amount)) {
            return '0';
        }

        return $amount;
    }

    private static function formatScale(string $amount, int $scale): string
    {
        if (! str_contains($amount, '.')) {
            return $amount.($scale > 0 ? '.'.str_repeat('0', $scale) : '');
        }

        [$i, $f] = explode('.', $amount, 2);
        $f = substr(str_pad($f, $scale, '0'), 0, $scale);

        return $scale > 0 ? $i.'.'.$f : $i;
    }
}

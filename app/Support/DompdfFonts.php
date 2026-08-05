<?php

namespace App\Support;

/**
 * Dompdf font paths for payslip PDF (Tajawal TTF — woff2 is not supported by Dompdf).
 */
final class DompdfFonts
{
    public static function directory(): string
    {
        $dir = storage_path('fonts');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (['Tajawal-Regular.ttf', 'Tajawal-Medium.ttf', 'Tajawal-Bold.ttf'] as $file) {
            $source = resource_path('fonts/'.$file);
            $target = $dir.DIRECTORY_SEPARATOR.$file;

            if (is_file($source) && ! is_file($target)) {
                copy($source, $target);
            }
        }

        return $dir;
    }

    public static function regular(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.'Tajawal-Regular.ttf';
    }

    public static function medium(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.'Tajawal-Medium.ttf';
    }

    public static function bold(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.'Tajawal-Bold.ttf';
    }
}

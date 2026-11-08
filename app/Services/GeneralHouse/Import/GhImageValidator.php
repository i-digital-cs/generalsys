<?php

namespace App\Services\GeneralHouse\Import;

class GhImageValidator
{
    /** @var array<string, string> */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @return string File extension derived from the real content (never from the file name). */
    public function extensionFor(string $absolutePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $absolutePath);
        if (! is_string($mime) || ! isset(self::MIME_TO_EXT[$mime]) || @getimagesize($absolutePath) === false) {
            throw new GhImageImportException(__('gh.import.errors.image_not_valid'));
        }

        return self::MIME_TO_EXT[$mime];
    }
}

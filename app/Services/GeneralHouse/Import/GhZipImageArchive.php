<?php

namespace App\Services\GeneralHouse\Import;

use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Safely extracts product images from an uploaded ZIP into a private temp directory.
 */
class GhZipImageArchive
{
    public const MAX_FILES = 50;

    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const FORBIDDEN_EXTENSIONS = [
        'php', 'phtml', 'phar', 'pht', 'exe', 'dll', 'sh', 'bat', 'cmd', 'com', 'js', 'jar', 'py', 'pl', 'cgi', 'htaccess', 'svg', 'html', 'htm',
    ];

    public function __construct(private readonly GhImageValidator $images) {}

    public function baseDirectory(): string
    {
        return storage_path('app/gh-import-tmp');
    }

    /**
     * @return array<string, string> lower-case basename => absolute extracted path
     *
     * @throws ValidationException
     */
    public function extract(string $zipPath, string $destination): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw $this->error(__('gh.import.errors.zip_unreadable'));
        }

        try {
            $entries = $this->vetEntries($zip);
            if (! is_dir($destination) && ! mkdir($destination, 0700, true) && ! is_dir($destination)) {
                throw $this->error(__('gh.import.errors.zip_unreadable'));
            }

            return $this->extractEntries($zip, $entries, $destination);
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{index: int, basename: string}> */
    private function vetEntries(ZipArchive $zip): array
    {
        $entries = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            $this->assertSafeName($name);

            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || str_starts_with(basename($name), '.')) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, self::FORBIDDEN_EXTENSIONS, true) || str_contains(strtolower(basename($name)), '.php')) {
                throw $this->error(__('gh.import.errors.zip_forbidden_file', ['name' => $name]));
            }
            if (! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $total += (int) ($stat['size'] ?? 0);
            $entries[] = ['index' => $i, 'basename' => basename($name)];
        }

        if (count($entries) > self::MAX_FILES || $total > self::MAX_TOTAL_BYTES) {
            throw $this->error(__('gh.import.errors.zip_too_large', ['files' => self::MAX_FILES, 'mb' => self::MAX_TOTAL_BYTES / 1048576]));
        }

        return $entries;
    }

    private function assertSafeName(string $name): void
    {
        $segments = explode('/', $name);
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1 || in_array('..', $segments, true)) {
            throw $this->error(__('gh.import.errors.zip_unsafe_path'));
        }
    }

    /**
     * @param  list<array{index: int, basename: string}>  $entries
     * @return array<string, string>
     */
    private function extractEntries(ZipArchive $zip, array $entries, string $destination): array
    {
        $files = [];
        $written = 0;
        foreach ($entries as $entry) {
            $target = $destination.'/'.bin2hex(random_bytes(8)).'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $entry['basename']);
            $written += $this->copyEntry($zip, $entry['index'], $target, self::MAX_TOTAL_BYTES - $written);
            try {
                $this->images->extensionFor($target);
            } catch (GhImageImportException) {
                @unlink($target);

                continue;
            }
            $files[strtolower($entry['basename'])] = $target;
        }

        return $files;
    }

    private function copyEntry(ZipArchive $zip, int $index, string $target, int $remaining): int
    {
        $in = $zip->getStream($zip->getNameIndex($index));
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw $this->error(__('gh.import.errors.zip_unreadable'));
        }

        $size = 0;
        while (! feof($in)) {
            $chunk = fread($in, 65536);
            $size += strlen((string) $chunk);
            if ($size > $remaining) {
                fclose($in);
                fclose($out);
                @unlink($target);
                throw $this->error(__('gh.import.errors.zip_too_large', ['files' => self::MAX_FILES, 'mb' => self::MAX_TOTAL_BYTES / 1048576]));
            }
            fwrite($out, (string) $chunk);
        }
        fclose($in);
        fclose($out);

        return $size;
    }

    public function deleteDirectory(?string $directory): void
    {
        if ($directory === null || $directory === '' || ! is_dir($directory) || ! str_starts_with(realpath($directory) ?: '', realpath($this->baseDirectory()) ?: "\0")) {
            return;
        }
        foreach (glob($directory.'/*') ?: [] as $file) {
            is_file($file) && @unlink($file);
        }
        @rmdir($directory);
    }

    /** Remove abandoned extraction folders older than one hour. */
    public function purgeStale(): void
    {
        foreach (glob($this->baseDirectory().'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < time() - 3600) {
                $this->deleteDirectory($dir);
            }
        }
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['zip' => [$message]])->errorBag('ghImport');
    }
}

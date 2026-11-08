<?php

namespace App\Services\GeneralHouse\App;

use App\Support\GeneralHouse\GhAppChannel;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Single source of truth for the distributable General House Android APK.
 * Controllers/menus are consumers only — never hardcode artifact paths in Blade.
 */
final class GhAndroidAppService
{
    public const MIME_APK = 'application/vnd.android.package-archive';

    public const CHANNEL_TEST = 'test';

    public const CHANNEL_LIVE = 'live';

    /**
     * @return array{
     *     available: bool,
     *     channel: string,
     *     is_test: bool,
     *     version_name: string,
     *     package_id: string,
     *     app_label: string,
     *     download_filename: string,
     *     sha256: string,
     *     built_at: ?string,
     *     size_bytes: int,
     *     size_label: string,
     *     absolute_path: ?string,
     *     reason: ?string
     * }
     */
    public function metadata(): array
    {
        $channel = $this->configuredChannel();
        $base = [
            'available' => false,
            'channel' => $channel,
            'is_test' => $channel === self::CHANNEL_TEST,
            'version_name' => '',
            'package_id' => '',
            'app_label' => '',
            'download_filename' => '',
            'sha256' => '',
            'built_at' => null,
            'size_bytes' => 0,
            'size_label' => '',
            'absolute_path' => null,
            'reason' => null,
        ];

        if (! (bool) config('general_house.android_app.distribution_enabled', false)) {
            return array_merge($base, ['reason' => 'distribution_disabled']);
        }

        if (! in_array($channel, [self::CHANNEL_TEST, self::CHANNEL_LIVE], true)) {
            return array_merge($base, ['reason' => 'invalid_channel']);
        }

        $manifest = $this->readManifest();
        if ($manifest === null) {
            return array_merge($base, ['reason' => 'manifest_missing']);
        }

        $manifestChannel = strtolower((string) ($manifest['channel'] ?? ''));
        if ($manifestChannel !== $channel) {
            return array_merge($base, ['reason' => 'channel_mismatch']);
        }

        if ($channel === self::CHANNEL_LIVE && ! ($manifest['distribution_approved'] ?? false)) {
            return array_merge($base, ['reason' => 'live_not_approved']);
        }

        $relativeArtifact = $this->artifactRelativePath($manifest);
        if ($relativeArtifact === null || ! Storage::disk('local')->exists($relativeArtifact)) {
            return array_merge($base, ['reason' => 'artifact_missing']);
        }

        $absolute = Storage::disk('local')->path($relativeArtifact);
        $basename = basename($absolute);

        if (! $this->artifactAllowedForChannel($channel, $basename, $manifest)) {
            return array_merge($base, ['reason' => 'artifact_forbidden_for_channel']);
        }

        $size = (int) filesize($absolute);
        $sha256 = strtolower((string) ($manifest['sha256'] ?? ''));
        if ($sha256 === '' || ! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            return array_merge($base, ['reason' => 'sha256_missing']);
        }

        $actual = hash_file('sha256', $absolute);
        if (! hash_equals($sha256, strtolower((string) $actual))) {
            return array_merge($base, ['reason' => 'sha256_mismatch']);
        }

        $version = (string) ($manifest['version_name'] ?? '');
        $packageId = (string) ($manifest['package_id'] ?? '');
        $filename = (string) ($manifest['download_filename'] ?? $basename);
        $label = (string) ($manifest['app_label'] ?? ($channel === self::CHANNEL_TEST
            ? 'General House TEST'
            : 'General House'));

        if ($version === '' || $packageId === '' || $filename === '') {
            return array_merge($base, ['reason' => 'manifest_incomplete']);
        }

        return [
            'available' => true,
            'channel' => $channel,
            'is_test' => $channel === self::CHANNEL_TEST,
            'version_name' => $version,
            'package_id' => $packageId,
            'app_label' => $label,
            'download_filename' => $filename,
            'sha256' => $sha256,
            'built_at' => isset($manifest['built_at']) ? (string) $manifest['built_at'] : null,
            'size_bytes' => $size,
            'size_label' => $this->formatBytes($size),
            'absolute_path' => $absolute,
            'reason' => null,
        ];
    }

    public function isAvailable(): bool
    {
        return $this->metadata()['available'] === true;
    }

    /**
     * @return array{path: string, filename: string, mime: string, sha256: string}
     */
    public function downloadPayload(): array
    {
        $meta = $this->metadata();
        if (! $meta['available'] || $meta['absolute_path'] === null) {
            throw new RuntimeException('Android APK is not available for download.');
        }

        return [
            'path' => $meta['absolute_path'],
            'filename' => $meta['download_filename'],
            'mime' => self::MIME_APK,
            'sha256' => $meta['sha256'],
        ];
    }

    public function configuredChannel(): string
    {
        return GhAppChannel::trusted();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(): ?array
    {
        $relative = (string) config('general_house.android_app.manifest', 'gh-android/current.manifest.json');
        if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
            return null;
        }

        $raw = Storage::disk('local')->get($relative);
        $decoded = json_decode($raw ?: '', true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function artifactRelativePath(array $manifest): ?string
    {
        $directory = trim((string) config('general_house.android_app.directory', 'gh-android'), '/');
        $fromManifest = (string) ($manifest['artifact'] ?? '');
        if ($fromManifest !== '') {
            if (str_contains($fromManifest, '..') || str_starts_with($fromManifest, '/')) {
                return null;
            }

            return $directory.'/'.ltrim($fromManifest, '/');
        }

        $configured = (string) config('general_house.android_app.artifact', 'gh-android/current.apk');

        return $configured !== '' ? $configured : null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function artifactAllowedForChannel(string $channel, string $basename, array $manifest): bool
    {
        $haystack = strtolower($basename.' '.(string) ($manifest['download_filename'] ?? '').' '.(string) ($manifest['package_id'] ?? ''));

        if ($channel === self::CHANNEL_LIVE) {
            // Never distribute TEST / unverified / unsigned builds from LIVE.
            foreach (['test', 'unverified', 'unsigned', '.debug'] as $forbidden) {
                if (str_contains($haystack, $forbidden)) {
                    return false;
                }
            }

            $packageId = strtolower((string) ($manifest['package_id'] ?? ''));
            if ($packageId !== '' && str_ends_with($packageId, '.test')) {
                return false;
            }

            return true;
        }

        // TEST channel must stay on the TEST package identity.
        $packageId = strtolower((string) ($manifest['package_id'] ?? ''));

        return str_ends_with($packageId, '.test') || str_contains($haystack, 'test');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
    }
}

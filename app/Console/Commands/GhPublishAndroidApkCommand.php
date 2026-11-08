<?php

namespace App\Console\Commands;

use App\Services\GeneralHouse\App\GhAppVersionPolicyService;
use App\Support\GeneralHouse\GhAppChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Copy a built Capacitor APK into private storage, refresh the download manifest,
 * and sync the Android version-policy record for the matching channel.
 * Publishing never auto-enables mandatory updates / never raises minimum_build.
 */
class GhPublishAndroidApkCommand extends Command
{
    protected $signature = 'gh:publish-android-apk
        {source : Absolute or project-relative path to the APK}
        {--channel=test : test|live}
        {--version-name= : Override version name (defaults to APK versionName)}
        {--version-code= : Override version code (defaults to APK versionCode)}
        {--package= : Android applicationId}
        {--label= : Display app name}
        {--filename= : Browser download filename}
        {--approved : Mark LIVE distribution as owner-approved (required for live channel availability)}';

    protected $description = 'Publish a GNRL Operations Android APK into private storage and sync version policy metadata';

    public function handle(GhAppVersionPolicyService $policies): int
    {
        $source = (string) $this->argument('source');
        if (! str_starts_with($source, '/')) {
            $source = base_path($source);
        }

        if (! is_file($source)) {
            $this->error("APK not found: {$source}");

            return self::FAILURE;
        }

        $channel = strtolower((string) $this->option('channel'));
        if (! GhAppChannel::isValid($channel)) {
            $this->error('Channel must be test or live.');

            return self::FAILURE;
        }

        $basename = basename($source);
        $haystack = strtolower($basename);

        if ($channel === GhAppChannel::LIVE) {
            foreach (['test', 'unverified', 'unsigned', '.debug'] as $forbidden) {
                if (str_contains($haystack, $forbidden)) {
                    $this->error("Refusing to publish {$basename} on LIVE channel ({$forbidden}).");

                    return self::FAILURE;
                }
            }
        }

        $apkMeta = $this->readApkMetadata($source);
        $sha256 = hash_file('sha256', $source);
        $builtAt = gmdate('Y-m-d\TH:i:s\Z', (int) filemtime($source));

        $defaults = $channel === GhAppChannel::TEST
            ? [
                'version' => $apkMeta['versionName'] ?: '1.0.0-test',
                'package' => 'net.genral.generalhouse.test',
                'label' => 'GNRL Operations TEST',
                'filename' => 'GeneralHouse-TEST.apk',
                'approved' => true,
            ]
            : [
                'version' => $apkMeta['versionName'] ?: '1.0.0',
                'package' => 'net.genral.generalhouse',
                'label' => 'GNRL Operations',
                'filename' => 'GeneralHouse.apk',
                'approved' => (bool) $this->option('approved'),
            ];

        $version = (string) ($this->option('version-name') ?: $defaults['version']);
        $versionCode = (int) ($this->option('version-code') ?: ($apkMeta['versionCode'] ?: 0));
        $package = (string) ($this->option('package') ?: ($apkMeta['package'] ?: $defaults['package']));
        $label = (string) ($this->option('label') ?: $defaults['label']);
        $filename = (string) ($this->option('filename') ?: $defaults['filename']);
        $approved = $channel === GhAppChannel::TEST ? true : (bool) $this->option('approved');

        if ($versionCode < 1) {
            $this->error('Could not determine a positive versionCode from the APK. Pass --version-code.');

            return self::FAILURE;
        }

        if ($channel === GhAppChannel::LIVE && (str_ends_with(strtolower($package), '.test') || str_contains(strtolower($filename), 'test'))) {
            $this->error('LIVE publish cannot use a TEST package id or TEST download filename.');

            return self::FAILURE;
        }

        if ($channel === GhAppChannel::TEST && $package !== 'net.genral.generalhouse.test') {
            $this->error('TEST publish must use package net.genral.generalhouse.test');

            return self::FAILURE;
        }

        if ($channel === GhAppChannel::LIVE && $package !== 'net.genral.generalhouse') {
            $this->error('LIVE publish must use package net.genral.generalhouse');

            return self::FAILURE;
        }

        // Only write the local download artifact when publishing for this server's trusted channel.
        $trusted = GhAppChannel::trusted();
        if ($channel === $trusted) {
            Storage::disk('local')->makeDirectory('gh-android');
            Storage::disk('local')->put('gh-android/current.apk', file_get_contents($source) ?: '');

            $manifest = [
                'channel' => $channel,
                'version_name' => $version,
                'version_code' => $versionCode,
                'package_id' => $package,
                'app_label' => $label,
                'download_filename' => $filename,
                'artifact' => 'current.apk',
                'sha256' => $sha256,
                'built_at' => $builtAt,
                'distribution_approved' => $approved,
            ];

            Storage::disk('local')->put(
                'gh-android/current.manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
            );
        } else {
            $this->warn("Channel {$channel} differs from this server's trusted channel ({$trusted}); APK file not written to local download storage.");
        }

        $existing = $policies->find('android', $channel);
        $updateUrl = $channel === $trusted
            ? url('/app/download/apk')
            : (string) ($existing->update_url ?? '');

        try {
            $policy = $policies->syncAndroidPublish($channel, [
                'version_name' => $version,
                'version_code' => $versionCode,
                'package_id' => $package,
                'sha256' => $sha256,
                'update_url' => $updateUrl,
                'artifact_path' => $channel === $trusted ? 'gh-android/current.apk' : $basename,
                'distribution_approved' => $approved,
            ]);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info('Published Android APK metadata and synced version policy.');
        $this->line(json_encode([
            'channel' => $channel,
            'package_id' => $package,
            'version_name' => $version,
            'version_code' => $versionCode,
            'sha256' => $sha256,
            'size_bytes' => filesize($source),
            'distribution_approved' => $approved,
            'policy_id' => $policy->id,
            'minimum_build_unchanged' => (int) $policy->minimum_build,
            'update_mode' => $policy->update_mode,
            'local_download_updated' => $channel === $trusted,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($channel === GhAppChannel::LIVE && ! $approved) {
            $this->warn('LIVE artifact rejected for distribution until --approved.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{package: ?string, versionName: ?string, versionCode: int}
     */
    private function readApkMetadata(string $apkPath): array
    {
        $aapt = $this->findAapt();
        if ($aapt === null) {
            return ['package' => null, 'versionName' => null, 'versionCode' => 0];
        }

        $cmd = escapeshellarg($aapt).' dump badging '.escapeshellarg($apkPath).' 2>/dev/null';
        $out = shell_exec($cmd) ?: '';
        $package = null;
        $versionName = null;
        $versionCode = 0;

        if (preg_match("/name='([^']+)'/", $out, $m)) {
            $package = $m[1];
        }
        if (preg_match("/versionCode='(\\d+)'/", $out, $m)) {
            $versionCode = (int) $m[1];
        }
        if (preg_match("/versionName='([^']+)'/", $out, $m)) {
            $versionName = $m[1];
        }

        return [
            'package' => $package,
            'versionName' => $versionName,
            'versionCode' => $versionCode,
        ];
    }

    private function findAapt(): ?string
    {
        $sdk = getenv('ANDROID_HOME') ?: getenv('ANDROID_SDK_ROOT') ?: '/home/d14098/Android/Sdk';
        $pattern = rtrim($sdk, '/').'/build-tools/*/aapt';
        $matches = glob($pattern) ?: [];
        if ($matches === []) {
            return null;
        }
        sort($matches);

        return end($matches) ?: null;
    }
}

<?php

namespace App\Services\GeneralHouse\App;

use App\Models\GeneralHouse\GhAppVersionPolicy;
use App\Support\GeneralHouse\GhAppChannel;
use App\Support\GeneralHouse\GhAppPlatform;
use App\Support\GeneralHouse\GhAppPublicationStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Read-only snapshot of the Android mobile app configuration for the trusted
 * server channel. Never trusts client-supplied environment parameters.
 */
final class GhMobileAppConfigService
{
    private const CAPACITOR_CONFIG = [
        GhAppChannel::TEST => 'mobile/config/capacitor.test.json',
        GhAppChannel::LIVE => 'mobile/config/capacitor.live.json',
    ];

    private const EXPECTED_PACKAGE = [
        GhAppChannel::TEST => 'net.genral.generalhouse.test',
        GhAppChannel::LIVE => 'net.genral.generalhouse',
    ];

    private const EXPECTED_HOST = [
        GhAppChannel::TEST => 'generalsys.idigital-cs.com',
        GhAppChannel::LIVE => 'system.genral.net',
    ];

    public function __construct(
        private readonly GhAndroidAppService $androidApp,
    ) {}

    /**
     * @return array{
     *     channel: string,
     *     channel_label: string,
     *     platform: string,
     *     application_name: string,
     *     package_id: string,
     *     server_url: string,
     *     server_host: string,
     *     version_name: ?string,
     *     version_build: ?int,
     *     version_source: ?string,
     *     publication_status: string,
     *     apk_available: bool,
     *     apk_status_key: string,
     *     download_url: ?string,
     *     app_url_host: string,
     *     host_matches_channel: bool,
     *     config_path: string,
     *     config_readable: bool
     * }
     */
    public function snapshot(): array
    {
        $channel = GhAppChannel::trusted();
        $capacitor = $this->readCapacitorConfig($channel);
        $policy = $this->androidPolicyFor($channel);
        $apk = $this->androidApp->metadata();

        $applicationName = (string) ($capacitor['appName'] ?? '');
        $packageId = (string) ($capacitor['appId'] ?? (self::EXPECTED_PACKAGE[$channel] ?? ''));
        $serverUrl = (string) ($capacitor['server']['url'] ?? '');
        $serverHost = $this->hostFromUrl($serverUrl) ?: (self::EXPECTED_HOST[$channel] ?? '');

        [$versionName, $versionBuild, $versionSource] = $this->resolveVersion($apk, $policy);

        $published = $policy !== null
            && $policy->publication_status === GhAppPublicationStatus::PUBLISHED;
        $downloadUrl = null;
        if ($published && filled($policy?->update_url)) {
            $downloadUrl = (string) $policy->update_url;
        } elseif ($apk['available'] === true) {
            $downloadUrl = route('app.download.file');
        }

        $appUrlHost = $this->hostFromUrl((string) config('app.url', ''));
        $expectedHost = self::EXPECTED_HOST[$channel] ?? '';
        $hostMatches = $expectedHost !== ''
            && ($appUrlHost === $expectedHost || Str::endsWith($appUrlHost, '.'.$expectedHost));

        return [
            'channel' => $channel,
            'channel_label' => strtoupper($channel),
            'platform' => GhAppPlatform::ANDROID,
            'application_name' => $applicationName !== '' ? $applicationName : '—',
            'package_id' => $packageId !== '' ? $packageId : '—',
            'server_url' => $serverUrl !== '' ? $serverUrl : '—',
            'server_host' => $serverHost !== '' ? $serverHost : '—',
            'version_name' => $versionName,
            'version_build' => $versionBuild,
            'version_source' => $versionSource,
            'publication_status' => $policy?->publication_status ?? GhAppPublicationStatus::UNPUBLISHED,
            'apk_available' => (bool) $apk['available'],
            'apk_status_key' => $this->apkStatusKey($apk, $published),
            'download_url' => $downloadUrl,
            'app_url_host' => $appUrlHost !== '' ? $appUrlHost : '—',
            'host_matches_channel' => $hostMatches,
            'config_path' => self::CAPACITOR_CONFIG[$channel] ?? '',
            'config_readable' => $capacitor !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $apk
     * @return array{0: ?string, 1: ?int, 2: ?string}
     */
    private function resolveVersion(array $apk, ?GhAppVersionPolicy $policy): array
    {
        if ($apk['available'] === true && ($apk['version_name'] ?? '') !== '') {
            $build = null;
            $manifest = $this->readPublishedManifestBuild();
            if ($manifest !== null) {
                $build = $manifest;
            } elseif ($policy !== null && (int) $policy->latest_build > 0) {
                $build = (int) $policy->latest_build;
            }

            return [(string) $apk['version_name'], $build, 'apk_manifest'];
        }

        if ($policy !== null && filled($policy->latest_version_name) && (int) $policy->latest_build > 0) {
            return [
                (string) $policy->latest_version_name,
                (int) $policy->latest_build,
                'policy',
            ];
        }

        return [null, null, null];
    }

    private function readPublishedManifestBuild(): ?int
    {
        $relative = (string) config('general_house.android_app.manifest', 'gh-android/current.manifest.json');
        if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($relative), true);
        if (! is_array($decoded)) {
            return null;
        }

        $code = (int) ($decoded['version_code'] ?? 0);

        return $code > 0 ? $code : null;
    }

    /**
     * @param  array<string, mixed>  $apk
     */
    private function apkStatusKey(array $apk, bool $policyPublished): string
    {
        if ($apk['available'] === true) {
            return 'available';
        }

        $reason = (string) ($apk['reason'] ?? '');

        return match ($reason) {
            'distribution_disabled' => 'distribution_disabled',
            'manifest_missing', 'artifact_missing' => 'not_published',
            'channel_mismatch' => 'channel_mismatch',
            'live_not_approved' => 'live_not_approved',
            'artifact_forbidden_for_channel' => 'forbidden_artifact',
            'sha256_missing', 'sha256_mismatch', 'manifest_incomplete' => 'artifact_invalid',
            default => $policyPublished ? 'policy_published_apk_unavailable' : 'not_published',
        };
    }

    private function androidPolicyFor(string $channel): ?GhAppVersionPolicy
    {
        return GhAppVersionPolicy::query()
            ->where('platform', GhAppPlatform::ANDROID)
            ->where('channel', $channel)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function readCapacitorConfig(string $channel): array
    {
        $relative = self::CAPACITOR_CONFIG[$channel] ?? null;
        if ($relative === null) {
            return [];
        }

        $absolute = base_path($relative);
        if (! is_readable($absolute)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($absolute), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hostFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }
}

<?php

namespace App\Services\GeneralHouse\App;

use App\Models\GeneralHouse\GhAppVersionPolicy;
use App\Models\GeneralHouse\GhAppVersionPolicyAudit;
use App\Models\User;
use App\Support\GeneralHouse\GhAppChannel;
use App\Support\GeneralHouse\GhAppPlatform;
use App\Support\GeneralHouse\GhAppPublicationStatus;
use App\Support\GeneralHouse\GhAppUpdateMode;
use App\Support\GnrlAppAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * SSOT for GNRL Operations mobile update policies (Android/iOS × TEST/LIVE).
 * Comparisons use numeric build numbers only — never version-string lexicography.
 */
final class GhAppVersionPolicyService
{
    public function __construct(
        private readonly GhAndroidAppService $androidApp,
    ) {}

    /**
     * @return Collection<int, GhAppVersionPolicy>
     */
    public function allPolicies(): Collection
    {
        return GhAppVersionPolicy::query()
            ->with('updater')
            ->orderBy('platform')
            ->orderBy('channel')
            ->get();
    }

    public function find(string $platform, string $channel): GhAppVersionPolicy
    {
        $this->assertPlatform($platform);
        $this->assertChannel($channel);

        return GhAppVersionPolicy::query()
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->firstOrFail();
    }

    /**
     * Trusted server channel only — client-supplied channel never selects policy.
     */
    public function trustedChannel(): string
    {
        return GhAppChannel::trusted();
    }

    /**
     * @return array{
     *     platform: string,
     *     channel: string,
     *     latest_version: ?string,
     *     latest_build: int,
     *     minimum_build: int,
     *     update_available: bool,
     *     update_required: bool,
     *     update_url: ?string,
     *     release_notes: ?string,
     *     publication_status: string,
     *     service_ok: bool
     * }
     */
    public function evaluateCheck(string $platform, int $buildNumber, ?string $locale = null, ?string $clientChannel = null): array
    {
        $this->assertPlatform($platform);
        if ($buildNumber < 0) {
            throw ValidationException::withMessages([
                'build_number' => [__('gh.app_versions.errors.invalid_build')],
            ]);
        }

        $channel = $this->trustedChannel();
        if ($clientChannel !== null && $clientChannel !== '' && GhAppChannel::isValid($clientChannel) && $clientChannel !== $channel) {
            // Soft signal only — never switch policies. Reject as invalid cross-env probe.
            throw ValidationException::withMessages([
                'app_channel' => [__('gh.app_versions.errors.channel_mismatch')],
            ]);
        }

        $policy = $this->find($platform, $channel);
        $published = $policy->isPublished();
        $latest = (int) $policy->latest_build;
        $minimum = (int) $policy->minimum_build;
        $updateUrl = $published ? $this->resolvedUpdateUrl($policy) : null;
        // Never advertise or force an update unless a channel-ready artifact/URL exists.
        $releaseReady = $updateUrl !== null
            && (
                $policy->platform !== GhAppPlatform::ANDROID
                || $this->androidReleaseReady($policy->channel, $updateUrl)
            );

        $updateAvailable = $published && $latest > $buildNumber && $releaseReady;
        $updateRequired = false;
        if ($published
            && $policy->update_mode === GhAppUpdateMode::REQUIRED_BY_MINIMUM
            && $minimum > 0
            && $buildNumber < $minimum
            && $releaseReady
            && $latest >= $minimum
        ) {
            $updateRequired = true;
        }

        $notes = null;
        if ($published) {
            $notes = ($locale === 'ar')
                ? ($policy->release_notes_ar ?: $policy->release_notes_en)
                : ($policy->release_notes_en ?: $policy->release_notes_ar);
        }

        return [
            'platform' => $platform,
            'channel' => $channel,
            'latest_version' => $published ? $policy->latest_version_name : null,
            'latest_build' => $published ? $latest : 0,
            'minimum_build' => $published ? $minimum : 0,
            'update_available' => $updateAvailable,
            'update_required' => $updateRequired,
            'update_url' => $updateUrl,
            'release_notes' => $notes,
            'publication_status' => $policy->publication_status,
            'service_ok' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updatePolicy(User $actor, GhAppVersionPolicy $policy, array $input, ?string $reason = null): GhAppVersionPolicy
    {
        if (! GnrlAppAccess::mayManageVersions($actor)) {
            throw ValidationException::withMessages([
                'policy' => [__('gh.app_versions.errors.forbidden')],
            ]);
        }

        $before = $this->snapshot($policy);

        $latestBuild = (int) ($input['latest_build'] ?? $policy->latest_build);
        $minimumBuild = (int) ($input['minimum_build'] ?? $policy->minimum_build);
        $updateMode = (string) ($input['update_mode'] ?? $policy->update_mode);
        $publicationStatus = (string) ($input['publication_status'] ?? $policy->publication_status);
        $updateUrl = isset($input['update_url']) ? trim((string) $input['update_url']) : $policy->update_url;
        $updateUrl = $updateUrl === '' ? null : $updateUrl;
        $latestVersion = isset($input['latest_version_name'])
            ? trim((string) $input['latest_version_name'])
            : $policy->latest_version_name;
        $latestVersion = $latestVersion === '' ? null : $latestVersion;

        if ($latestBuild < 0 || $minimumBuild < 0) {
            throw ValidationException::withMessages([
                'latest_build' => [__('gh.app_versions.errors.invalid_build')],
            ]);
        }

        if ($minimumBuild > $latestBuild) {
            throw ValidationException::withMessages([
                'minimum_build' => [__('gh.app_versions.errors.minimum_exceeds_latest')],
            ]);
        }

        if (! GhAppUpdateMode::isValid($updateMode)) {
            throw ValidationException::withMessages([
                'update_mode' => [__('gh.app_versions.errors.invalid_mode')],
            ]);
        }

        if (! GhAppPublicationStatus::isValid($publicationStatus)) {
            throw ValidationException::withMessages([
                'publication_status' => [__('gh.app_versions.errors.invalid_status')],
            ]);
        }

        if ($publicationStatus === GhAppPublicationStatus::PUBLISHED) {
            if ($latestBuild < 1 || $latestVersion === null) {
                throw ValidationException::withMessages([
                    'publication_status' => [__('gh.app_versions.errors.publish_requires_release')],
                ]);
            }
            if ($policy->platform === GhAppPlatform::ANDROID && ! $this->androidReleaseReady($policy->channel, $updateUrl)) {
                throw ValidationException::withMessages([
                    'publication_status' => [__('gh.app_versions.errors.android_artifact_required')],
                ]);
            }
            if ($policy->platform === GhAppPlatform::IOS && ($updateUrl === null || ! $this->isApprovedIosUrl($updateUrl))) {
                throw ValidationException::withMessages([
                    'update_url' => [__('gh.app_versions.errors.ios_url_required')],
                ]);
            }
        }

        if ($updateMode === GhAppUpdateMode::REQUIRED_BY_MINIMUM) {
            $this->assertMandatoryAllowed($policy, $latestBuild, $minimumBuild, $publicationStatus, $updateUrl);
        }

        return DB::transaction(function () use (
            $actor,
            $policy,
            $before,
            $latestBuild,
            $minimumBuild,
            $updateMode,
            $publicationStatus,
            $updateUrl,
            $latestVersion,
            $input,
            $reason
        ) {
            $policy->latest_build = $latestBuild;
            $policy->minimum_build = $minimumBuild;
            $policy->update_mode = $updateMode;
            $policy->publication_status = $publicationStatus;
            $policy->update_url = $updateUrl;
            $policy->latest_version_name = $latestVersion;
            $policy->release_notes_ar = isset($input['release_notes_ar'])
                ? (trim((string) $input['release_notes_ar']) ?: null)
                : $policy->release_notes_ar;
            $policy->release_notes_en = isset($input['release_notes_en'])
                ? (trim((string) $input['release_notes_en']) ?: null)
                : $policy->release_notes_en;
            $policy->updated_by = $actor->id;
            if ($publicationStatus === GhAppPublicationStatus::PUBLISHED && $policy->published_at === null) {
                $policy->published_at = now();
            }
            if ($publicationStatus === GhAppPublicationStatus::UNPUBLISHED) {
                $policy->published_at = null;
            }
            $policy->save();

            $this->writeAudit($policy, $actor, 'policy.update', $before, $this->snapshot($policy), $reason);

            return $policy->fresh(['updater']);
        });
    }

    /**
     * Called by publish command after APK lands in storage.
     * Never auto-raises minimum_build / never forces REQUIRED mode.
     *
     * @param  array{
     *     version_name: string,
     *     version_code: int,
     *     package_id: string,
     *     sha256: string,
     *     update_url: string,
     *     artifact_path: string,
     *     distribution_approved: bool
     * }  $meta
     */
    public function syncAndroidPublish(string $channel, array $meta, ?User $actor = null): GhAppVersionPolicy
    {
        $this->assertChannel($channel);
        $policy = $this->find(GhAppPlatform::ANDROID, $channel);
        $before = $this->snapshot($policy);

        if ($channel === GhAppChannel::LIVE && ! ($meta['distribution_approved'] ?? false)) {
            throw ValidationException::withMessages([
                'channel' => [__('gh.app_versions.errors.live_not_approved')],
            ]);
        }

        $versionCode = (int) $meta['version_code'];
        if ($versionCode < 1) {
            throw ValidationException::withMessages([
                'version_code' => [__('gh.app_versions.errors.invalid_build')],
            ]);
        }

        return DB::transaction(function () use ($policy, $before, $meta, $versionCode, $actor) {
            $policy->latest_version_name = (string) $meta['version_name'];
            $policy->latest_build = $versionCode;
            // Keep existing minimum; clamp if it somehow exceeded the new latest.
            if ((int) $policy->minimum_build > $versionCode) {
                $policy->minimum_build = $versionCode;
            }
            $policy->package_id = (string) $meta['package_id'];
            $policy->sha256 = strtolower((string) $meta['sha256']);
            $policy->artifact_path = (string) $meta['artifact_path'];
            $incomingUrl = trim((string) ($meta['update_url'] ?? ''));
            if ($incomingUrl !== '') {
                $policy->update_url = $incomingUrl;
            }
            $policy->publication_status = GhAppPublicationStatus::PUBLISHED;
            $policy->published_at = now();
            $policy->updated_by = $actor?->id;
            // Explicit: publishing does not enable mandatory updates.
            if ($policy->update_mode === GhAppUpdateMode::REQUIRED_BY_MINIMUM
                && ((int) $policy->minimum_build < 1 || (int) $policy->minimum_build > $versionCode)
            ) {
                $policy->update_mode = GhAppUpdateMode::OPTIONAL;
            }
            $policy->save();

            $this->writeAudit(
                $policy,
                $actor,
                'android.publish',
                $before,
                $this->snapshot($policy),
                'APK published; minimum_build unchanged unless clamped'
            );

            return $policy->fresh(['updater']);
        });
    }

    public function iosDownloadUrlForTrustedChannel(): ?string
    {
        $policy = $this->find(GhAppPlatform::IOS, $this->trustedChannel());
        if (! $policy->isPublished()) {
            return null;
        }
        $url = $this->resolvedUpdateUrl($policy);

        return ($url !== null && $this->isApprovedIosUrl($url)) ? $url : null;
    }

    public function androidDownloadAvailable(): bool
    {
        // Download endpoint remains gated by the verified APK artifact for this channel.
        return $this->androidApp->isAvailable();
    }

    private function assertMandatoryAllowed(
        GhAppVersionPolicy $policy,
        int $latestBuild,
        int $minimumBuild,
        string $publicationStatus,
        ?string $updateUrl
    ): void {
        if ($publicationStatus !== GhAppPublicationStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'update_mode' => [__('gh.app_versions.errors.mandatory_requires_publish')],
            ]);
        }
        if ($latestBuild < 1 || $minimumBuild < 1) {
            throw ValidationException::withMessages([
                'update_mode' => [__('gh.app_versions.errors.mandatory_requires_builds')],
            ]);
        }
        if ($minimumBuild > $latestBuild) {
            throw ValidationException::withMessages([
                'minimum_build' => [__('gh.app_versions.errors.minimum_exceeds_latest')],
            ]);
        }
        $resolved = $updateUrl ?: $this->resolvedUpdateUrl($policy);
        if ($resolved === null) {
            throw ValidationException::withMessages([
                'update_url' => [__('gh.app_versions.errors.mandatory_requires_url')],
            ]);
        }
        if ($policy->platform === GhAppPlatform::ANDROID && ! $this->androidReleaseReady($policy->channel, $resolved)) {
            throw ValidationException::withMessages([
                'update_mode' => [__('gh.app_versions.errors.android_artifact_required')],
            ]);
        }
        if ($policy->platform === GhAppPlatform::IOS && ! $this->isApprovedIosUrl($resolved)) {
            throw ValidationException::withMessages([
                'update_url' => [__('gh.app_versions.errors.ios_url_required')],
            ]);
        }
    }

    private function androidReleaseReady(string $channel, ?string $updateUrl): bool
    {
        if ($channel !== $this->trustedChannel()) {
            // Editing LIVE policy on TEST host: require explicit HTTPS URL, not local artifact.
            return is_string($updateUrl) && str_starts_with($updateUrl, 'https://');
        }

        if (! $this->androidApp->isAvailable()) {
            return false;
        }

        return true;
    }

    private function resolvedUpdateUrl(GhAppVersionPolicy $policy): ?string
    {
        if (is_string($policy->update_url) && $policy->update_url !== '') {
            return $policy->update_url;
        }

        if ($policy->platform === GhAppPlatform::ANDROID
            && $policy->channel === $this->trustedChannel()
            && $this->androidApp->isAvailable()
        ) {
            return Route::has('app.download.file')
                ? route('app.download.file')
                : route('gh.app.download.file');
        }

        return null;
    }

    private function isApprovedIosUrl(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }

        // Approved distribution hosts only — never APK / unknown stores.
        $allowed = [
            'apps.apple.com',
            'itunes.apple.com',
            'testflight.apple.com',
        ];
        foreach ($allowed as $ok) {
            if ($host === $ok || str_ends_with($host, '.'.$ok)) {
                return true;
            }
        }

        // Enterprise / MDM HTTPS URLs must be explicitly allow-listed via config.
        $extra = config('general_house.app_versions.ios_url_hosts', []);
        if (is_array($extra)) {
            foreach ($extra as $ok) {
                $ok = strtolower((string) $ok);
                if ($ok !== '' && ($host === $ok || str_ends_with($host, '.'.$ok))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(GhAppVersionPolicy $policy): array
    {
        return [
            'platform' => $policy->platform,
            'channel' => $policy->channel,
            'latest_version_name' => $policy->latest_version_name,
            'latest_build' => (int) $policy->latest_build,
            'minimum_build' => (int) $policy->minimum_build,
            'update_mode' => $policy->update_mode,
            'update_url' => $policy->update_url,
            'release_notes_ar' => $policy->release_notes_ar,
            'release_notes_en' => $policy->release_notes_en,
            'publication_status' => $policy->publication_status,
            'package_id' => $policy->package_id,
            'sha256' => $policy->sha256,
            'artifact_path' => $policy->artifact_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function writeAudit(
        GhAppVersionPolicy $policy,
        ?User $actor,
        string $action,
        array $before,
        array $after,
        ?string $reason
    ): void {
        GhAppVersionPolicyAudit::query()->create([
            'policy_id' => $policy->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'before_json' => $before,
            'after_json' => $after,
            'reason' => $reason ? mb_substr(trim($reason), 0, 500) : null,
            'recorded_at' => now(),
        ]);
    }

    private function assertPlatform(string $platform): void
    {
        if (! GhAppPlatform::isValid($platform)) {
            throw ValidationException::withMessages([
                'platform' => [__('gh.app_versions.errors.invalid_platform')],
            ]);
        }
    }

    private function assertChannel(string $channel): void
    {
        if (! GhAppChannel::isValid($channel)) {
            throw ValidationException::withMessages([
                'channel' => [__('gh.app_versions.errors.invalid_channel')],
            ]);
        }
    }
}

<?php

namespace App\Services\GeneralHouse\Import;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

class GhImportSession
{
    public const TTL_MINUTES = 30;

    public function __construct(private readonly Repository $cache) {}

    /**
     * @param  array<string, string>  $archiveFiles  lower-case filename => absolute path of extracted image
     */
    public function put(int $userId, string $entity, GhImportPreviewResult $preview, array $archiveFiles = [], ?string $archiveDir = null): string
    {
        $token = Str::random(40);
        $this->cache->put($this->key($token), [
            'user_id' => $userId,
            'entity' => $entity,
            'preview' => $preview->toArray(),
            'archive_files' => $archiveFiles,
            'archive_dir' => $archiveDir,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * @return array{preview: GhImportPreviewResult, archive_files: array<string, string>, archive_dir: ?string}|null
     */
    public function get(string $token, int $userId, string $entity): ?array
    {
        $payload = $this->cache->get($this->key($token));
        if (! is_array($payload) || $payload['user_id'] !== $userId || $payload['entity'] !== $entity) {
            return null;
        }

        return [
            'preview' => GhImportPreviewResult::fromArray($payload['preview']),
            'archive_files' => $payload['archive_files'],
            'archive_dir' => $payload['archive_dir'],
        ];
    }

    public function forget(string $token): void
    {
        $this->cache->forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'gh_import:'.$token;
    }
}

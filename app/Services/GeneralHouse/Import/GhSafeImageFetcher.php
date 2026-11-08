<?php

namespace App\Services\GeneralHouse\Import;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Downloads a product image from a public HTTPS URL (SSRF-hardened).
 */
class GhSafeImageFetcher
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const TIMEOUT_SECONDS = 10;

    private const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly GhImageValidator $images,
    ) {}

    /**
     * Cheap syntactic/IP check used at preview time (no DNS lookups).
     *
     * @throws GhImageImportException
     */
    public function assertUrlAllowed(string $url, bool $resolveDns = false): void
    {
        $this->guardedHostIps($url, $resolveDns);
    }

    /**
     * @return array{path: string, ext: string} Temp file the caller must delete.
     *
     * @throws GhImageImportException
     */
    public function fetch(string $url): array
    {
        $current = $url;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $ips = $this->guardedHostIps($current, true);
            $response = $this->request($current, $ips[0] ?? null);

            if ($response->redirect()) {
                $current = $this->resolveLocation($current, (string) $response->header('Location'));

                continue;
            }

            return $this->storeBody($response);
        }

        throw new GhImageImportException(__('gh.import.errors.image_redirects'));
    }

    private function request(string $url, ?string $pinnedIp): Response
    {
        $parts = parse_url($url);
        $options = ['allow_redirects' => false, 'stream' => false];
        if ($pinnedIp !== null && filter_var($parts['host'] ?? '', FILTER_VALIDATE_IP) === false && defined('CURLOPT_RESOLVE')) {
            $options['curl'] = [CURLOPT_RESOLVE => [$parts['host'].':443:'.$pinnedIp]];
        }

        $exceeded = new \stdClass;
        $exceeded->value = false;

        try {
            return $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions($options + ['progress' => $this->progressGuard($exceeded)])
                ->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp'])
                ->get($url);
        } catch (\Throwable) {
            throw new GhImageImportException(
                $exceeded->value ? __('gh.import.errors.image_too_large') : __('gh.import.errors.image_download_failed')
            );
        }
    }

    private function progressGuard(\stdClass $exceeded): \Closure
    {
        return function ($downloadTotal, $downloaded) use ($exceeded): void {
            if ($downloaded > self::MAX_BYTES || $downloadTotal > self::MAX_BYTES) {
                $exceeded->value = true;

                throw new GhImageImportException(__('gh.import.errors.image_too_large'));
            }
        };
    }

    /** @return array{path: string, ext: string} */
    private function storeBody(Response $response): array
    {
        if (! $response->successful()) {
            throw new GhImageImportException(__('gh.import.errors.image_download_failed'));
        }
        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new GhImageImportException(__('gh.import.errors.image_too_large'));
        }

        $path = tempnam(sys_get_temp_dir(), 'ghimg');
        file_put_contents($path, $body);

        try {
            return ['path' => $path, 'ext' => $this->images->extensionFor($path)];
        } catch (GhImageImportException $e) {
            @unlink($path);
            throw $e;
        }
    }

    private function resolveLocation(string $base, string $location): string
    {
        if ($location === '') {
            throw new GhImageImportException(__('gh.import.errors.image_redirects'));
        }
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($base);

        return 'https://'.$parts['host'].'/'.ltrim($location, '/');
    }

    /** @return list<string> Resolved public IPs (empty when DNS was not requested). */
    private function guardedHostIps(string $url, bool $resolveDns): array
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower(trim((string) ($parts['host'] ?? ''), '[]')) : '';
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new GhImageImportException(__('gh.import.errors.image_url_https'));
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return [$host];
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new GhImageImportException(__('gh.import.errors.image_url_private'));
        }
        if (! $resolveDns) {
            return [];
        }

        $ips = $this->resolveHost($host);
        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return $ips;
    }

    /** @return list<string> */
    private function resolveHost(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $ips[] = (string) ($record['ipv6'] ?? '');
        }
        $ips = array_values(array_filter($ips));
        if ($ips === []) {
            throw new GhImageImportException(__('gh.import.errors.image_download_failed'));
        }

        return $ips;
    }

    private function assertPublicIp(string $ip): void
    {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m) === 1) {
            $ip = $m[1];
        }
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        if (! $isPublic || $ip === '::1' || str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            throw new GhImageImportException(__('gh.import.errors.image_url_private'));
        }
    }
}

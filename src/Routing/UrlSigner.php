<?php

declare(strict_types=1);

namespace VtPhp\Routing;

use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Config\Repository;

/**
 * Tamper-proof, optionally expiring URLs, like Laravel's signed routes.
 * sign() appends `expires` and an HMAC-SHA256 `signature` keyed with
 * APP_KEY; hasValidSignature() checks both. The path and query are signed,
 * not the host, so links survive proxies and load balancers.
 */
final class UrlSigner
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function sign(string $url, ?\DateTimeInterface $expiresAt = null): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new \InvalidArgumentException("Cannot sign malformed URL [{$url}].");
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['signature']);

        if ($expiresAt !== null) {
            $query['expires'] = (string) $expiresAt->getTimestamp();
        }

        ksort($query);
        $path = $parts['path'] ?? '/';
        $query['signature'] = $this->signature($path, $query);

        $origin = isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';

        return $origin.$path.'?'.http_build_query($query);
    }

    public function hasValidSignature(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        $signature = $query['signature'] ?? null;

        if (!is_string($signature)) {
            return false;
        }

        unset($query['signature']);
        ksort($query);

        if (!hash_equals($this->signature($request->getUri()->getPath(), $query), $signature)) {
            return false;
        }

        $expires = $query['expires'] ?? null;

        return $expires === null || (is_string($expires) && ctype_digit($expires) && (int) $expires >= time());
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private function signature(string $path, array $query): string
    {
        return hash_hmac('sha256', $path.'?'.http_build_query($query), $this->key());
    }

    private function key(): string
    {
        $key = (string) $this->config->get('app.key', '');

        if ($key === '') {
            throw new \RuntimeException('No application key is set. Run `php forge key:generate`.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false || $decoded === '') {
                throw new \RuntimeException('APP_KEY is not valid base64. Run `php forge key:generate`.');
            }

            return $decoded;
        }

        return $key;
    }
}

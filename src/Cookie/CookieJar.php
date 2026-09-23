<?php

declare(strict_types=1);

namespace VtPhp\Cookie;

/**
 * Collects outgoing cookies queued during the request so a downstream
 * middleware (AddQueuedCookiesToResponse) can attach them to the response.
 */
final class CookieJar
{
    /** @var array<string, Cookie> */
    private array $queued = [];

    public function make(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'lax',
    ): Cookie {
        return new Cookie(
            name: $name,
            value: $value,
            expires: $minutes > 0 ? time() + ($minutes * 60) : 0,
            path: $path,
            domain: $domain,
            secure: $secure ?? (bool) config('session.secure', false),
            httpOnly: $httpOnly,
            sameSite: $sameSite,
        );
    }

    public function queue(Cookie $cookie): void
    {
        $this->queued[$cookie->name] = $cookie;
    }

    public function forget(string $name): void
    {
        $this->queued[$name] = new Cookie($name, '', expires: time() - 3600);
    }

    /**
     * @return array<string, Cookie>
     */
    public function queued(): array
    {
        return $this->queued;
    }
}

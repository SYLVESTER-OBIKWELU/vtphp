<?php

declare(strict_types=1);

namespace VtPhp\Cookie;

/**
 * An immutable value object describing a single outgoing Set-Cookie header.
 */
final class Cookie
{
    public function __construct(
        public readonly string $name,
        public readonly string $value = '',
        public readonly int $expires = 0,
        public readonly string $path = '/',
        public readonly ?string $domain = null,
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'lax',
    ) {
    }

    public function toHeaderValue(): string
    {
        $parts = [rawurlencode($this->name).'='.rawurlencode($this->value)];

        if ($this->expires !== 0) {
            $parts[] = 'Expires='.gmdate('D, d-M-Y H:i:s T', $this->expires);
            $parts[] = 'Max-Age='.max(0, $this->expires - time());
        }

        $parts[] = 'Path='.$this->path;

        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain='.$this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = 'SameSite='.ucfirst($this->sameSite);

        return implode('; ', $parts);
    }
}

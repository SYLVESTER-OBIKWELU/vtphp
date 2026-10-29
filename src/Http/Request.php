<?php

declare(strict_types=1);

namespace VtPhp\Http;

use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Validation\Validator;

class Request
{
    /** @var array<string, mixed> Input added or overridden via merge(). */
    private array $merged = [];

    final public function __construct(private ServerRequestInterface $psr)
    {
    }

    public static function fromPsr(ServerRequestInterface $psr): static
    {
        return new static($psr);
    }

    /**
     * Validate the input and return only the validated fields, throwing a
     * ValidationException (422) on failure — like Laravel's $request->validate().
     *
     * @param array<string, array<int, string>|string> $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        return Validator::make($this->all(), $rules)->validated();
    }

    /**
     * The authenticated user for the given (or default) guard.
     */
    public function user(?string $guard = null): ?object
    {
        return auth($guard)->user();
    }

    public function psr(): ServerRequestInterface
    {
        return $this->psr;
    }

    /**
     * The client's IP: resolved through trusted proxies by TrustProxies,
     * otherwise the direct peer (REMOTE_ADDR).
     */
    public function ip(): ?string
    {
        $ip = $this->psr->getAttribute('client_ip') ?? $this->psr->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    /**
     * Whether the request arrived over HTTPS (as seen through trusted proxies).
     */
    public function isSecure(): bool
    {
        return $this->psr->getUri()->getScheme() === 'https';
    }

    public function method(): string
    {
        return $this->psr->getMethod();
    }

    public function path(): string
    {
        return $this->psr->getUri()->getPath();
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->psr->getQueryParams()[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->psr->getQueryParams(), $this->parsedBody(), $this->merged);
    }

    /**
     * Add or override input values, e.g. to normalize them before validation.
     *
     * @param array<string, mixed> $input
     */
    public function merge(array $input): static
    {
        $this->merged = array_merge($this->merged, $input);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return $this->parsedBody();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $value = $this->psr->getHeaderLine($key);

        return $value !== '' ? $value : $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->psr->getCookieParams()[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->psr->getAttribute($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedBody(): array
    {
        $parsed = $this->psr->getParsedBody();

        if (is_array($parsed)) {
            return $parsed;
        }

        if (str_contains($this->psr->getHeaderLine('Content-Type'), 'application/json')) {
            $decoded = json_decode((string) $this->psr->getBody(), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

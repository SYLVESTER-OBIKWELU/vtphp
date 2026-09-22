<?php

declare(strict_types=1);

namespace VtPhp\Http;

use Psr\Http\Message\ServerRequestInterface;

final class Request
{
    private function __construct(private ServerRequestInterface $psr)
    {
    }

    public static function fromPsr(ServerRequestInterface $psr): self
    {
        return new self($psr);
    }

    public function psr(): ServerRequestInterface
    {
        return $this->psr;
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
        return array_merge($this->psr->getQueryParams(), $this->parsedBody());
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

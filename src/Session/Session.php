<?php

declare(strict_types=1);

namespace VtPhp\Session;

/**
 * A single request's session attribute bag, backed by a SessionStoreInterface.
 */
final class Session
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly SessionStoreInterface $store,
        private string $id,
        private array $attributes = [],
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Rotates the session id (keeping attributes), e.g. after login to
     * prevent session fixation.
     */
    public function regenerate(): string
    {
        $this->store->destroy($this->id);
        $this->id = bin2hex(random_bytes(20));

        return $this->id;
    }

    /**
     * Clears all attributes and rotates the session id, e.g. on logout.
     */
    public function invalidate(): void
    {
        $this->attributes = [];
        $this->regenerate();
    }

    public function save(): void
    {
        $this->store->write($this->id, $this->attributes);
    }
}

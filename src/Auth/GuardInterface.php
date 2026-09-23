<?php

declare(strict_types=1);

namespace VtPhp\Auth;

interface GuardInterface
{
    public function user(): ?object;

    public function id(): int|string|null;

    public function check(): bool;

    public function guest(): bool;

    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials): bool;

    public function login(object $user): void;

    public function logout(): void;
}

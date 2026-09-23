<?php

declare(strict_types=1);

namespace VtPhp\Auth;

/**
 * A generic user-lookup contract. Lives in the framework layer (src/) and
 * deliberately knows nothing about the app's concrete User model — the
 * app layer supplies a concrete implementation (e.g. App\Auth\EloquentUserProvider)
 * and binds it in a service provider, mirroring how App\Repositories\UserRepositoryInterface
 * itself is bound.
 */
interface UserProviderInterface
{
    public function retrieveById(int|string $id): ?object;

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?object;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(object $user, array $credentials): bool;
}

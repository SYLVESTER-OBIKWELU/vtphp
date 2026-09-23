<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use VtPhp\Auth\UserProviderInterface;

/**
 * Adapts the app's Eloquent-backed UserRepositoryInterface to the
 * framework-level, model-agnostic UserProviderInterface contract.
 */
final class EloquentUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function retrieveById(int|string $id): ?object
    {
        return $this->users->find((int) $id);
    }

    public function retrieveByCredentials(array $credentials): ?object
    {
        $email = (string) ($credentials['email'] ?? '');

        return $email !== '' ? $this->users->findByEmail($email) : null;
    }

    public function validateCredentials(object $user, array $credentials): bool
    {
        /** @var User $user */
        $password = (string) ($credentials['password'] ?? '');

        return $user->password !== null && password_verify($password, $user->password);
    }
}

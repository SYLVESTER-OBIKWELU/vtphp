<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Looks users up through any Eloquent model, configured in config/auth.php
 * like Laravel's `providers.users.model` — no app-side adapter needed.
 */
final class EloquentUserProvider implements UserProviderInterface
{
    /**
     * @param class-string<Model> $model
     */
    public function __construct(private readonly string $model)
    {
    }

    public function retrieveById(int|string $id): ?object
    {
        return ($this->model)::query()->find($id);
    }

    /**
     * Matches every credential except the password, e.g. ['email' => …].
     */
    public function retrieveByCredentials(array $credentials): ?object
    {
        $conditions = array_filter(
            $credentials,
            static fn (string $key): bool => !str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY,
        );

        if ($conditions === []) {
            return null;
        }

        $query = ($this->model)::query();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    public function validateCredentials(object $user, array $credentials): bool
    {
        $hash = $user instanceof Model ? $user->getAttribute('password') : null;

        return is_string($hash) && password_verify((string) ($credentials['password'] ?? ''), $hash);
    }
}

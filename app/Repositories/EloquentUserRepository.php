<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::query()->find($id);
    }

    /**
     * @return array<int, User>
     */
    public function all(): array
    {
        /** @var array<int, User> $users */
        $users = User::query()->orderBy('id')->get()->all();

        return $users;
    }

    public function create(string $name, string $email): User
    {
        return User::query()->create(['name' => $name, 'email' => $email]);
    }
}

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

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
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

    public function create(string $name, string $email, ?string $password = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password !== null ? password_hash($password, PASSWORD_BCRYPT) : null,
        ]);
    }

    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}

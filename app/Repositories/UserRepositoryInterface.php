<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return array<int, User>
     */
    public function all(): array;

    public function create(string $name, string $email, ?string $password = null): User;

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(User $user, array $attributes): User;

    public function delete(User $user): void;
}

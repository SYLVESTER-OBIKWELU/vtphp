<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;

    /**
     * @return array<int, User>
     */
    public function all(): array;

    public function create(string $name, string $email): User;
}

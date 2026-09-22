<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Demo repository so the skeleton runs out of the box with no database
 * configured. Replace with a Doctrine DBAL-backed repository for real use
 * (inject VtPhp\Database\DatabaseManager and query via ->connection()).
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users = [];

    private int $nextId = 2;

    public function __construct()
    {
        $this->users[1] = new User(1, 'Ada Lovelace', 'ada@example.com', new \DateTimeImmutable());
    }

    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function create(string $name, string $email): User
    {
        $user = new User($this->nextId, $name, $email, new \DateTimeImmutable());
        $this->users[$this->nextId] = $user;
        $this->nextId++;

        return $user;
    }
}

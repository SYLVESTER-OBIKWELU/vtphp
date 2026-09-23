<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Requests\CreateUserRequest;
use App\Requests\UpdateUserRequest;
use VtPhp\Exceptions\ConflictHttpException;
use VtPhp\Exceptions\NotFoundHttpException;

final class UserService
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function find(int $id): User
    {
        return $this->users->find($id) ?? throw new NotFoundHttpException('User not found.');
    }

    /**
     * @return array<int, User>
     */
    public function list(): array
    {
        return $this->users->all();
    }

    public function create(CreateUserRequest $request): User
    {
        if ($this->users->findByEmail($request->email) !== null) {
            throw new ConflictHttpException('A user with this email address already exists.');
        }

        return $this->users->create($request->name, $request->email, $request->password);
    }

    public function update(int $id, UpdateUserRequest $request): User
    {
        $user = $this->find($id);

        if ($request->email !== null && $request->email !== $user->email && $this->users->findByEmail($request->email) !== null) {
            throw new ConflictHttpException('A user with this email address already exists.');
        }

        return $this->users->update($user, $request->toAttributes());
    }

    public function delete(int $id): void
    {
        $this->users->delete($this->find($id));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Requests\CreateUserRequest;
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
        return $this->users->create($request->name, $request->email);
    }
}

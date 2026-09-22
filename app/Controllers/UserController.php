<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Requests\CreateUserRequest;
use App\Resources\UserResource;
use App\Services\UserService;
use VtPhp\Http\Request;
use VtPhp\Resources\ResourceCollection;
use VtPhp\Routing\Attributes\Route;

final class UserController
{
    public function __construct(private readonly UserService $service)
    {
    }

    #[Route(method: 'GET', path: '/users', name: 'users.index')]
    public function index(): ResourceCollection
    {
        return UserResource::collection($this->service->list());
    }

    #[Route(method: 'GET', path: '/users/{id}', name: 'users.show')]
    public function show(int $id): UserResource
    {
        return UserResource::make($this->service->find($id));
    }

    #[Route(method: 'POST', path: '/users', name: 'users.store')]
    public function store(Request $request): UserResource
    {
        $user = $this->service->create(CreateUserRequest::fromRequest($request));

        return UserResource::make($user)->status(201);
    }
}

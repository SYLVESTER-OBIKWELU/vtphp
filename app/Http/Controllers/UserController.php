<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use VtPhp\Auth\Access\AuthorizesRequests;
use VtPhp\Http\JsonResponse;
use VtPhp\Resources\ResourceCollection;

final class UserController
{
    use AuthorizesRequests;

    public function index(): ResourceCollection
    {
        return UserResource::collection(User::query()->orderBy('id')->get());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        return UserResource::make(User::query()->create($request->validated()))->status(201);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return UserResource::make($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user); // UserPolicy::delete()

        $user->delete();

        return response()->noContent();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Http\JsonResponse;
use VtPhp\Resources\ResourceCollection;

final class UserController
{
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
        if ((string) auth()->id() !== (string) $user->id) {
            throw new AuthorizationException('You may only delete your own account.');
        }

        $user->delete();

        return response()->noContent();
    }
}

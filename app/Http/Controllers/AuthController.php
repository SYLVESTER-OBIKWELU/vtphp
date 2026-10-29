<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;

final class AuthController
{
    public function login(LoginRequest $request): JsonResponse|UserResource
    {
        if (!auth()->attempt($request->validated())) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        return UserResource::make(auth()->user());
    }

    public function logout(): JsonResponse
    {
        // A token client logging out revokes the token it used; a browser
        // ends its session.
        if (auth('api')->check()) {
            auth('api')->logout();
        }

        auth('web')->logout();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}

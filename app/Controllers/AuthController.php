<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Requests\LoginRequest;
use App\Resources\UserResource;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Middleware\Authenticate;
use VtPhp\Routing\Attributes\Route;

final class AuthController
{
    #[Route(method: 'POST', path: '/login', name: 'auth.login')]
    public function login(Request $request): JsonResponse|UserResource
    {
        $data = LoginRequest::fromRequest($request);

        $authenticated = auth('web')->attempt([
            'email' => $data->email,
            'password' => $data->password,
        ]);

        if (!$authenticated) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        return UserResource::make(auth('web')->user());
    }

    #[Route(method: 'POST', path: '/logout', name: 'auth.logout')]
    public function logout(): JsonResponse
    {
        auth('web')->logout();

        return response()->json(['message' => 'Logged out.']);
    }

    #[Route(method: 'GET', path: '/me', name: 'auth.me', middleware: [Authenticate::class])]
    public function me(): UserResource
    {
        return UserResource::make(auth('web')->user());
    }
}

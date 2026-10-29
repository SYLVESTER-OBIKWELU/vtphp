<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateTokenRequest;
use App\Http\Resources\TokenResource;
use App\Models\User;
use VtPhp\Auth\PersonalAccessToken;
use VtPhp\Exceptions\NotFoundHttpException;
use VtPhp\Http\JsonResponse;
use VtPhp\Resources\ResourceCollection;

/**
 * API tokens for clients that can't use cookies (mobile apps, scripts,
 * other servers). They send the token as "Authorization: Bearer ...".
 */
final class TokenController
{
    /**
     * Exchange an email and password for a new token.
     */
    public function store(CreateTokenRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name: string, abilities?: array<mixed>} $data */
        $data = $request->validated();

        if (!auth('api')->attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();
        $abilities = isset($data['abilities']) ? array_values(array_filter($data['abilities'], 'is_string')) : ['*'];
        $token = $user->createToken($data['device_name'], $abilities);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'type' => 'Bearer',
                'abilities' => $abilities,
            ],
        ], 201);
    }

    /**
     * The signed-in user's tokens (without their secrets).
     */
    public function index(): ResourceCollection
    {
        /** @var User $user */
        $user = auth()->user();

        return TokenResource::collection($user->tokens()->orderBy('id')->get());
    }

    /**
     * Revoke one of your own tokens. Other users' tokens look like they
     * don't exist.
     */
    public function destroy(PersonalAccessToken $token): JsonResponse
    {
        if ((string) $token->user_id !== (string) auth()->id()) {
            throw new NotFoundHttpException('PersonalAccessToken not found.');
        }

        $token->delete();

        return response()->noContent();
    }
}

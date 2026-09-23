<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Requests\SendEmailVerificationRequest;
use App\Resources\UserResource;
use App\Services\EmailVerificationService;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Routing\Attributes\Route;

final class EmailVerificationController
{
    public function __construct(private readonly EmailVerificationService $service)
    {
    }

    #[Route(method: 'POST', path: '/email/verification-notification', name: 'verification.send')]
    public function send(Request $request): JsonResponse
    {
        $data = SendEmailVerificationRequest::fromRequest($request);
        $this->service->sendVerificationEmail($data->email);

        return response()->json(['message' => 'If that email address is registered and unverified, a verification link has been sent.']);
    }

    #[Route(method: 'GET', path: '/email/verify/{id}/{hash}', name: 'verification.verify')]
    public function verify(int $id, string $hash): UserResource
    {
        return UserResource::make($this->service->verify($id, $hash));
    }
}

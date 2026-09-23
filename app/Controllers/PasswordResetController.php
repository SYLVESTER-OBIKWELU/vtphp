<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Requests\ForgotPasswordRequest;
use App\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Routing\Attributes\Route;

final class PasswordResetController
{
    public function __construct(private readonly PasswordResetService $service)
    {
    }

    #[Route(method: 'POST', path: '/password/forgot', name: 'password.forgot')]
    public function forgot(Request $request): JsonResponse
    {
        $data = ForgotPasswordRequest::fromRequest($request);
        $this->service->forgot($data->email);

        return response()->json(['message' => 'If that email address is registered, a password reset link has been sent.']);
    }

    #[Route(method: 'POST', path: '/password/reset', name: 'password.reset')]
    public function reset(Request $request): JsonResponse
    {
        $data = ResetPasswordRequest::fromRequest($request);
        $this->service->reset($data->email, $data->token, $data->password);

        return response()->json(['message' => 'Your password has been reset.']);
    }
}

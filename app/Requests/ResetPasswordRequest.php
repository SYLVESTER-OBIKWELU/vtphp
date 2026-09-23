<?php

declare(strict_types=1);

namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class ResetPasswordRequest
{
    private function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        return new self($data['email'], $data['token'], $data['password']);
    }
}

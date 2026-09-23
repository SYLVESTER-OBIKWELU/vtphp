<?php

declare(strict_types=1);

namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class LoginRequest
{
    private function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ])->validate();

        return new self($data['email'], $data['password']);
    }
}

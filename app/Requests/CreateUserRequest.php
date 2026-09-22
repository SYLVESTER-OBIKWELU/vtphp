<?php

declare(strict_types=1);

namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class CreateUserRequest
{
    private function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
        ])->validate();

        return new self($data['name'], $data['email']);
    }
}

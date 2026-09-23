<?php

declare(strict_types=1);

namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class SendEmailVerificationRequest
{
    private function __construct(
        public readonly string $email,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        return new self($data['email']);
    }
}

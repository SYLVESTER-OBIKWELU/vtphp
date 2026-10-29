<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmail;
use VtPhp\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|max_bytes:72|confirmed',
        ];
    }
}

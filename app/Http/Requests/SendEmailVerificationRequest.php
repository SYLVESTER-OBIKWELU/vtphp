<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class SendEmailVerificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}

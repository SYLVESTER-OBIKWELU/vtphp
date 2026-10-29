<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = (string) $this->route('user');

        return [
            'name' => 'sometimes|string|max:100',
            'email' => "sometimes|email|unique:users,email,{$userId}",
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use VtPhp\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    /**
     * Users may only edit their own account.
     */
    public function authorize(): bool
    {
        return (string) auth()->id() === (string) $this->route('user');
    }

    public function rules(): array
    {
        $userId = (string) $this->route('user');

        return [
            'name' => 'sometimes|string|max:100',
            'email' => "sometimes|email|unique:users,email,{$userId}",
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmail;
use App\Models\User;
use VtPhp\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * Checked before validation: UserPolicy::update() decides.
     */
    public function authorize(): bool
    {
        $account = User::query()->find($this->route('user'));

        return $account !== null && gate()->allows('update', $account);
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

<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Lowercases and trims `email` before validation, so `unique:users,email`,
 * logins and lookups treat Ada@Example.com and ada@example.com as the same
 * address (emails are stored lowercased by App\Models\User).
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }
}

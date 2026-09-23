<?php

declare(strict_types=1);

namespace App\Requests;

use VtPhp\Http\Request;
use VtPhp\Validation\Validator;

final class UpdateUserRequest
{
    private function __construct(
        public readonly ?string $name,
        public readonly ?string $email,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
        ])->validate();

        return new self($data['name'] ?? null, $data['email'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

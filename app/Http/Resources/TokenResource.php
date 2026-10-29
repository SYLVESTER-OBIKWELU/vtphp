<?php

declare(strict_types=1);

namespace App\Http\Resources;

use VtPhp\Resources\JsonResource;

/**
 * A token's details. Never includes the secret, which is only shown once,
 * when the token is created.
 *
 * @property \VtPhp\Auth\PersonalAccessToken $resource
 */
final class TokenResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'abilities' => $this->resource->abilities ?? [],
            'last_used_at' => $this->resource->last_used_at?->toAtomString(),
            'expires_at' => $this->resource->expires_at?->toAtomString(),
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}

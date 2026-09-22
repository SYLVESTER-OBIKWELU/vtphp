<?php

declare(strict_types=1);

namespace App\Resources;

use VtPhp\Resources\JsonResource;

/**
 * @property \App\Models\User $resource
 */
final class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'created_at' => $this->resource->created_at?->toAtomString(),
        ];
    }
}

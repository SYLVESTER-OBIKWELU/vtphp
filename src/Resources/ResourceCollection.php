<?php

declare(strict_types=1);

namespace VtPhp\Resources;

use VtPhp\Http\JsonResponse;
use VtPhp\Pagination\LengthAwarePaginator;

final class ResourceCollection
{
    private int $status = 200;

    /**
     * @param iterable<mixed>|LengthAwarePaginator $resources
     * @param class-string<JsonResource> $resourceClass
     */
    public function __construct(private readonly iterable|LengthAwarePaginator $resources, private readonly string $resourceClass)
    {
    }

    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        $items = $this->resources instanceof LengthAwarePaginator ? $this->resources->items() : $this->resources;

        $data = [];

        foreach ($items as $item) {
            $data[] = ($this->resourceClass)::make($item)->toArray();
        }

        return $data;
    }

    public function toResponse(): JsonResponse
    {
        $payload = ['data' => $this->toArray()];

        if ($this->resources instanceof LengthAwarePaginator) {
            $payload['meta'] = $this->resources->meta();
            $payload['links'] = $this->resources->links();
        }

        return new JsonResponse($payload, $this->status);
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Resources;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as EloquentLengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use VtPhp\Http\JsonResponse;
use VtPhp\Pagination\LengthAwarePaginator;

/**
 * A list of resources. Given a paginator (Eloquent's paginate(),
 * simplePaginate() or cursorPaginate(), or VtPhp's LengthAwarePaginator) the
 * response also carries `meta` and `links` for moving between pages.
 */
final class ResourceCollection
{
    private int $status = 200;

    /**
     * @param iterable<mixed>|LengthAwarePaginator|Paginator<array-key, mixed>|CursorPaginator<array-key, mixed> $resources
     * @param class-string<JsonResource> $resourceClass
     */
    public function __construct(
        private readonly iterable|LengthAwarePaginator|Paginator|CursorPaginator $resources,
        private readonly string $resourceClass,
    ) {
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
        $items = match (true) {
            $this->resources instanceof LengthAwarePaginator,
            $this->resources instanceof Paginator,
            $this->resources instanceof CursorPaginator => $this->resources->items(),
            default => $this->resources,
        };

        $data = [];

        foreach ($items as $item) {
            $data[] = ($this->resourceClass)::make($item)->toArray();
        }

        return $data;
    }

    public function toResponse(): JsonResponse
    {
        $payload = ['data' => $this->toArray()];
        $pages = $this->pagination();

        if ($pages !== null) {
            $payload += $pages;
        }

        return new JsonResponse($payload, $this->status);
    }

    /**
     * @return array{meta: array<string, mixed>, links: array<string, ?string>}|null
     */
    private function pagination(): ?array
    {
        $pages = $this->resources;

        if ($pages instanceof LengthAwarePaginator) {
            return ['meta' => $pages->meta(), 'links' => $pages->links()];
        }

        if ($pages instanceof EloquentLengthAwarePaginator) {
            return [
                'meta' => [
                    'current_page' => $pages->currentPage(),
                    'per_page' => $pages->perPage(),
                    'total' => $pages->total(),
                    'last_page' => $pages->lastPage(),
                    'from' => $pages->firstItem(),
                    'to' => $pages->lastItem(),
                ],
                'links' => [
                    'first' => $pages->url(1),
                    'last' => $pages->url($pages->lastPage()),
                    'prev' => $pages->previousPageUrl(),
                    'next' => $pages->nextPageUrl(),
                ],
            ];
        }

        if ($pages instanceof Paginator) {
            // simplePaginate(): no total count, so no last page either.
            return [
                'meta' => [
                    'current_page' => $pages->currentPage(),
                    'per_page' => $pages->perPage(),
                    'from' => $pages->firstItem(),
                    'to' => $pages->lastItem(),
                ],
                'links' => [
                    'first' => $pages->url(1),
                    'prev' => $pages->previousPageUrl(),
                    'next' => $pages->nextPageUrl(),
                ],
            ];
        }

        if ($pages instanceof CursorPaginator) {
            return [
                'meta' => [
                    'per_page' => $pages->perPage(),
                    'prev_cursor' => $pages->previousCursor()?->encode(),
                    'next_cursor' => $pages->nextCursor()?->encode(),
                ],
                'links' => [
                    'prev' => $pages->previousPageUrl(),
                    'next' => $pages->nextPageUrl(),
                ],
            ];
        }

        return null;
    }
}

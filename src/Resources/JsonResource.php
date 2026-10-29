<?php

declare(strict_types=1);

namespace VtPhp\Resources;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use VtPhp\Http\JsonResponse;
use VtPhp\Pagination\LengthAwarePaginator;

/**
 * Subclasses must keep the single-argument constructor, since make() and
 * ResourceCollection instantiate them via `new static($resource)`.
 *
 * @phpstan-consistent-constructor
 */
abstract class JsonResource
{
    protected int $status = 200;

    /** @var array<string, string> */
    protected array $extraHeaders = [];

    public function __construct(protected mixed $resource)
    {
    }

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * @param iterable<mixed>|LengthAwarePaginator|Paginator<array-key, mixed>|CursorPaginator<array-key, mixed> $resources
     */
    public static function collection(iterable|LengthAwarePaginator|Paginator|CursorPaginator $resources): ResourceCollection
    {
        return new ResourceCollection($resources, static::class);
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->extraHeaders[$name] = $value;

        return $this;
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(['data' => $this->toArray()], $this->status, $this->extraHeaders);
    }
}

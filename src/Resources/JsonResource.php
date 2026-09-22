<?php

declare(strict_types=1);

namespace VtPhp\Resources;

use VtPhp\Http\JsonResponse;
use VtPhp\Pagination\LengthAwarePaginator;

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
     * @param iterable<mixed>|LengthAwarePaginator $resources
     */
    public static function collection(iterable|LengthAwarePaginator $resources): ResourceCollection
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

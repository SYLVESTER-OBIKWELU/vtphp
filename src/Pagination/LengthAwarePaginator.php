<?php

declare(strict_types=1);

namespace VtPhp\Pagination;

final class LengthAwarePaginator
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
        private readonly ?string $path = null,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function lastPage(): int
    {
        return (int) max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public function meta(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage(),
        ];
    }

    /**
     * @return array{first: string, last: string, prev: ?string, next: ?string}
     */
    public function links(): array
    {
        $path = $this->path ?? '';
        $make = fn (int $page): string => $path === '' ? '' : $path.'?page='.$page;

        return [
            'first' => $make(1),
            'last' => $make($this->lastPage()),
            'prev' => $this->currentPage > 1 ? $make($this->currentPage - 1) : null,
            'next' => $this->currentPage < $this->lastPage() ? $make($this->currentPage + 1) : null,
        ];
    }
}

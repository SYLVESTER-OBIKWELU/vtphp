<?php

declare(strict_types=1);

namespace VtPhp\Session;

interface SessionStoreInterface
{
    /**
     * @return array<string, mixed>
     */
    public function read(string $id): array;

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $id, array $data): void;

    public function destroy(string $id): void;
}

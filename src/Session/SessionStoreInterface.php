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

    /**
     * Delete sessions idle for longer than the given number of seconds.
     * Called occasionally (see `session.lottery`), not on every request.
     */
    public function gc(int $maxLifetimeSeconds): void;
}

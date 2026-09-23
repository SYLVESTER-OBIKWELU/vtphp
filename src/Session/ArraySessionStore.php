<?php

declare(strict_types=1);

namespace VtPhp\Session;

/**
 * A non-persistent, in-memory session store. Data only survives for the
 * lifetime of the current PHP process/request — intended for tests and CLI
 * usage, not for real multi-request session persistence.
 */
final class ArraySessionStore implements SessionStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    public function read(string $id): array
    {
        return $this->sessions[$id] ?? [];
    }

    public function write(string $id, array $data): void
    {
        $this->sessions[$id] = $data;
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }
}

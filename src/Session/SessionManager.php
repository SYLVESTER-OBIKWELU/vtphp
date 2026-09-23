<?php

declare(strict_types=1);

namespace VtPhp\Session;

use VtPhp\Config\Repository;

final class SessionManager
{
    private ?SessionStoreInterface $store = null;

    public function __construct(private readonly Repository $config)
    {
    }

    public function start(?string $id): Session
    {
        $store = $this->resolveStore();
        $sessionId = $this->isValidId($id) ? $id : $this->generateId();

        return new Session($store, $sessionId, $store->read($sessionId));
    }

    public function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function isValidId(?string $id): bool
    {
        return $id !== null && preg_match('/^[a-f0-9]{40}$/', $id) === 1;
    }

    private function resolveStore(): SessionStoreInterface
    {
        if ($this->store !== null) {
            return $this->store;
        }

        $driver = (string) $this->config->get('session.driver', 'file');
        $lifetime = (int) $this->config->get('session.lifetime', 120);

        return $this->store = match ($driver) {
            'file' => new FileSessionStore(
                (string) $this->config->get('session.files', storage_path('framework/sessions')),
                $lifetime,
            ),
            'array' => new ArraySessionStore(),
            default => throw new \InvalidArgumentException("Unsupported session driver [{$driver}]."),
        };
    }
}

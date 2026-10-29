<?php

declare(strict_types=1);

namespace VtPhp\Session;

use Illuminate\Database\Capsule\Manager as Capsule;
use VtPhp\Config\Repository;

final class SessionManager
{
    private ?SessionStoreInterface $store = null;

    public function __construct(private readonly Repository $config)
    {
    }

    public function start(?string $id): Session
    {
        $store = $this->store();
        $sessionId = $this->isValidId($id) ? $id : $this->generateId();

        return new Session($store, $sessionId, $store->read($sessionId));
    }

    public function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * Sweep expired sessions on a configurable share of requests
     * (`session.lottery`, default 2 in 100), like Laravel, so expired files
     * and rows don't accumulate without needing a cron job.
     */
    public function collectGarbageByLottery(): bool
    {
        $lottery = (array) $this->config->get('session.lottery', [2, 100]);
        [$chances, $outOf] = [(int) ($lottery[0] ?? 0), max(1, (int) ($lottery[1] ?? 100))];

        if ($chances <= 0 || random_int(1, $outOf) > $chances) {
            return false;
        }

        $this->store()->gc((int) $this->config->get('session.lifetime', 120) * 60);

        return true;
    }

    public function store(): SessionStoreInterface
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
            'database' => new DatabaseSessionStore(
                Capsule::connection($this->stringOrNull($this->config->get('session.connection'))),
                (string) $this->config->get('session.table', 'sessions'),
                $lifetime,
            ),
            'array' => new ArraySessionStore(),
            default => throw new \InvalidArgumentException("Unsupported session driver [{$driver}]."),
        };
    }

    private function isValidId(?string $id): bool
    {
        return $id !== null && preg_match('/^[a-f0-9]{40}$/', $id) === 1;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use Illuminate\Database\Connection;

/**
 * The `failed_jobs` table: jobs that used up all their tries, kept so you can
 * see what went wrong and retry them with `php forge queue:retry`.
 */
final class FailedJobs
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $table = 'failed_jobs',
    ) {
    }

    public function log(string $connection, string $queue, string $payload, \Throwable $exception): void
    {
        $this->db->table($this->table)->insert([
            'uuid' => (string) (Payload::parse($payload)['uuid'] ?? bin2hex(random_bytes(16))),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) $exception,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<object{id: int, uuid: string, connection: string, queue: string, payload: string, exception: string, failed_at: string}>
     */
    public function all(): array
    {
        /** @var list<object{id: int, uuid: string, connection: string, queue: string, payload: string, exception: string, failed_at: string}> */
        return $this->db->table($this->table)->orderBy('id')->get()->all();
    }

    /**
     * @return object{id: int, uuid: string, connection: string, queue: string, payload: string, exception: string, failed_at: string}|null
     */
    public function find(int|string $id): ?object
    {
        /** @var object{id: int, uuid: string, connection: string, queue: string, payload: string, exception: string, failed_at: string}|null */
        return $this->db->table($this->table)
            ->where(is_numeric($id) ? 'id' : 'uuid', $id)
            ->first();
    }

    public function forget(int|string $id): bool
    {
        return $this->db->table($this->table)->where(is_numeric($id) ? 'id' : 'uuid', $id)->delete() > 0;
    }

    public function flush(): int
    {
        return $this->db->table($this->table)->delete();
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Session;

use Illuminate\Database\ConnectionInterface;

/**
 * Stores sessions in a database table (see the create_sessions_table
 * migration), like Laravel's `database` session driver. Useful when several
 * app servers must share sessions.
 *
 * Payloads are serialized with `allowed_classes: false` on read (so a
 * tampered row can never instantiate objects) and base64-encoded so any
 * database text column can hold them. The logged-in user's id is copied to
 * `user_id`, so a user's sessions can be found or revoked with one query.
 */
final class DatabaseSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly int $lifetimeMinutes,
    ) {
    }

    public function read(string $id): array
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        if ($row === null) {
            return [];
        }

        if ((int) $row->last_activity < time() - ($this->lifetimeMinutes * 60)) {
            $this->destroy($id);

            return [];
        }

        $decoded = base64_decode((string) $row->payload, true);
        $data = $decoded === false ? false : @unserialize($decoded, ['allowed_classes' => false]);

        return is_array($data) ? $data : [];
    }

    public function write(string $id, array $data): void
    {
        $userId = $data['auth_user_id'] ?? null;

        $this->connection->table($this->table)->upsert(
            [[
                'id' => $id,
                'user_id' => is_int($userId) || (is_string($userId) && ctype_digit($userId)) ? (int) $userId : null,
                'payload' => base64_encode(serialize($data)),
                'last_activity' => time(),
            ]],
            ['id'],
            ['user_id', 'payload', 'last_activity'],
        );
    }

    public function destroy(string $id): void
    {
        $this->connection->table($this->table)->where('id', $id)->delete();
    }

    public function gc(int $maxLifetimeSeconds): void
    {
        $this->connection->table($this->table)->where('last_activity', '<', time() - $maxLifetimeSeconds)->delete();
    }
}

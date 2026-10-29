<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use VtPhp\Database\Migrations\Migration;
use VtPhp\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Let the cache adapter define its own table, so the columns always
        // match what it reads and writes on SQLite, MySQL and PostgreSQL.
        (new DoctrineDbalAdapter($this->db, '', 0, ['db_table' => $this->table()]))->createTable();
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('cache.stores.database.table', 'cache');
    }
};

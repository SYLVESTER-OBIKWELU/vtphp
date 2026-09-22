<?php

declare(strict_types=1);

namespace VtPhp\Database\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use VtPhp\Database\DatabaseManager;

final class Migrator
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly string $path,
        private readonly string $table = 'migrations',
    ) {
    }

    private function connection(): Connection
    {
        return $this->db->connection();
    }

    private function ensureTable(): void
    {
        $conn = $this->connection();

        if ($conn->createSchemaManager()->tablesExist([$this->table])) {
            return;
        }

        $schema = new Schema();
        $table = $schema->createTable($this->table);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('migration', 'string', ['length' => 255]);
        $table->addColumn('batch', 'integer');
        $table->setPrimaryKey(['id']);

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeStatement($sql);
        }
    }

    /**
     * @return array<int, string>
     */
    private function ran(): array
    {
        return $this->connection()->fetchFirstColumn("SELECT migration FROM {$this->table}");
    }

    /**
     * @return array<int, string>
     */
    private function files(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path.'/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    public function run(): array
    {
        $this->ensureTable();

        $ran = $this->ran();
        $batch = $this->nextBatch();
        $executed = [];

        foreach ($this->files() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (in_array($name, $ran, true)) {
                continue;
            }

            $this->resolve($file)->up();
            $this->connection()->insert($this->table, ['migration' => $name, 'batch' => $batch]);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * @return array<int, string>
     */
    public function rollback(): array
    {
        $this->ensureTable();

        $lastBatch = (int) $this->connection()->fetchOne("SELECT MAX(batch) FROM {$this->table}");

        if ($lastBatch === 0) {
            return [];
        }

        $names = $this->connection()->fetchFirstColumn(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id DESC",
            [$lastBatch],
        );

        $rolledBack = [];

        foreach ($names as $name) {
            $file = $this->path.'/'.$name.'.php';

            if (is_file($file)) {
                $this->resolve($file)->down();
            }

            $this->connection()->delete($this->table, ['migration' => $name]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * @return array<string, bool>
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ran();
        $status = [];

        foreach ($this->files() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $status[$name] = in_array($name, $ran, true);
        }

        return $status;
    }

    private function nextBatch(): int
    {
        return ((int) $this->connection()->fetchOne("SELECT MAX(batch) FROM {$this->table}")) + 1;
    }

    private function resolve(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration file [{$file}] must return a Migration instance.");
        }

        return $migration->setConnection($this->connection());
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Config\Repository;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\Migrations\Migrator;
use VtPhp\Tests\Support\TempDirectory;

final class DatabaseTest extends TestCase
{
    private string $tmp;

    private string $migrations;

    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-db');
        $this->migrations = $this->tmp.'/migrations';
        mkdir($this->migrations);

        $this->db = new DatabaseManager(new Repository(['database' => [
            'default' => 'sqlite',
            'connections' => ['sqlite' => ['driver' => 'pdo_sqlite', 'path' => $this->tmp.'/test.sqlite']],
        ]]));

        $this->writeMigration('2026_01_01_000001_create_alpha', 'alpha');
        $this->writeMigration('2026_01_01_000002_create_beta', 'beta');
    }

    protected function tearDown(): void
    {
        $this->db->connection()->close();
        TempDirectory::delete($this->tmp);
    }

    private function writeMigration(string $name, string $table): void
    {
        file_put_contents($this->migrations."/{$name}.php", <<<PHP
            <?php
            return new class extends \\VtPhp\\Database\\Migrations\\Migration {
                public function up(): void { \$this->db->executeStatement('CREATE TABLE {$table} (id INTEGER PRIMARY KEY)'); }
                public function down(): void { \$this->db->executeStatement('DROP TABLE {$table}'); }
            };
            PHP);
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->db, $this->migrations);
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = $this->db->connection()->createSchemaManager()->listTableNames();
        sort($tables);

        return array_values(array_diff($tables, ['migrations']));
    }

    public function test_run_applies_pending_migrations_in_filename_order(): void
    {
        $this->assertSame(['2026_01_01_000001_create_alpha', '2026_01_01_000002_create_beta'], $this->migrator()->run());
        $this->assertSame(['alpha', 'beta'], $this->tables());
        $this->assertSame([], $this->migrator()->run(), 'A second run is a no-op.');
    }

    public function test_status_reports_ran_and_pending(): void
    {
        $this->migrator()->run();
        $this->writeMigration('2026_01_01_000003_create_gamma', 'gamma');

        $this->assertSame([
            '2026_01_01_000001_create_alpha' => true,
            '2026_01_01_000002_create_beta' => true,
            '2026_01_01_000003_create_gamma' => false,
        ], $this->migrator()->status());
    }

    public function test_rollback_reverts_only_the_latest_batch_in_reverse_order(): void
    {
        $this->migrator()->run();
        $this->writeMigration('2026_01_01_000003_create_gamma', 'gamma');
        $this->migrator()->run();

        $this->assertSame(['2026_01_01_000003_create_gamma'], $this->migrator()->rollback());
        $this->assertSame(['alpha', 'beta'], $this->tables());

        $this->assertSame(['2026_01_01_000002_create_beta', '2026_01_01_000001_create_alpha'], $this->migrator()->rollback());
        $this->assertSame([], $this->tables());
        $this->assertSame([], $this->migrator()->rollback(), 'Nothing left to roll back.');
    }

    public function test_a_file_that_does_not_return_a_migration_is_rejected(): void
    {
        file_put_contents($this->migrations.'/2026_01_01_000009_broken.php', '<?php return 42;');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must return a Migration instance');

        $this->migrator()->run();
    }

    public function test_a_missing_migrations_directory_means_nothing_to_run(): void
    {
        $this->assertSame([], (new Migrator($this->db, $this->tmp.'/nope'))->run());
    }

    public function test_connections_are_memoized_and_unknown_ones_rejected(): void
    {
        $this->assertSame($this->db->connection(), $this->db->connection('sqlite'));

        $this->expectException(\InvalidArgumentException::class);
        $this->db->connection('oracle');
    }

    public function test_transaction_commits_and_returns_the_callback_result(): void
    {
        $this->migrator()->run();

        $result = $this->db->transaction(function ($conn): string {
            $conn->insert('alpha', ['id' => 1]);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(1, (int) $this->db->connection()->fetchOne('SELECT COUNT(*) FROM alpha'));
    }

    public function test_transaction_rolls_back_when_the_callback_throws(): void
    {
        $this->migrator()->run();

        try {
            $this->db->transaction(function ($conn): void {
                $conn->insert('alpha', ['id' => 1]);

                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, (int) $this->db->connection()->fetchOne('SELECT COUNT(*) FROM alpha'));
    }
}

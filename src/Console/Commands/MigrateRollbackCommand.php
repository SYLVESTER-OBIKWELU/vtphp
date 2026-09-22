<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\Migrations\Migrator;

#[AsCommand(name: 'migrate:rollback', description: 'Rollback the last database migration batch')]
final class MigrateRollbackCommand extends Command
{
    protected function handle(): int
    {
        $rolledBack = $this->migrator()->rollback();

        if ($rolledBack === []) {
            $this->line('Nothing to rollback.');

            return self::SUCCESS;
        }

        foreach ($rolledBack as $name) {
            $this->line("Rolled back: {$name}");
        }

        return self::SUCCESS;
    }

    private function migrator(): Migrator
    {
        $config = $this->app->make('config');

        return new Migrator(
            $this->app->make(DatabaseManager::class),
            (string) $config->get('database.migrations.path', $this->app->databasePath('migrations')),
            (string) $config->get('database.migrations.table', 'migrations'),
        );
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\Migrations\Migrator;

#[AsCommand(name: 'migrate', description: 'Run the database migrations')]
final class MigrateCommand extends Command
{
    protected function handle(): int
    {
        $ran = $this->migrator()->run();

        if ($ran === []) {
            $this->line('Nothing to migrate.');

            return self::SUCCESS;
        }

        foreach ($ran as $name) {
            $this->line("Migrated: {$name}");
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

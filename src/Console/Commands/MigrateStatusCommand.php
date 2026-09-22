<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Database\DatabaseManager;
use VtPhp\Database\Migrations\Migrator;

#[AsCommand(name: 'migrate:status', description: 'Show the status of each migration')]
final class MigrateStatusCommand extends Command
{
    protected function handle(): int
    {
        $status = $this->migrator()->status();

        if ($status === []) {
            $this->line('No migrations found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($status as $name => $ran) {
            $rows[] = [$name, $ran ? 'Ran' : 'Pending'];
        }

        $this->io->table(['Migration', 'Status'], $rows);

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

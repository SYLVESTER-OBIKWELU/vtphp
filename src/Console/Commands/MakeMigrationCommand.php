<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:migration', description: 'Create a new migration file')]
final class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The migration name, e.g. create_users_table');
    }

    protected function handle(): int
    {
        $name = (string) $this->input->getArgument('name');
        $fileName = date('Y_m_d_His')."_{$name}.php";
        $path = $this->app->databasePath("migrations/{$fileName}");

        $stub = (string) file_get_contents($this->app->basePath('stubs/migration.stub'));

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $stub);

        $this->info("Migration created: database/migrations/{$fileName}");

        return self::SUCCESS;
    }
}

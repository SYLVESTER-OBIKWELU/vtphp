<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:seeder', description: 'Create a new database seeder')]
final class MakeSeederCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The seeder name, e.g. UserSeeder');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $path = $this->app->databasePath("seeders/{$name}.php");

        if (is_file($path)) {
            $this->error("Seeder {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/seeder.stub'));
        $contents = str_replace('{{ class }}', $name, $stub);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Seeder created: database/seeders/{$name}.php");

        return self::SUCCESS;
    }
}

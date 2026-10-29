<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;

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

        return $this->generate('seeder.stub', "database/seeders/{$name}.php", ['class' => $name], "Seeder {$name}")
            ? self::SUCCESS
            : self::FAILURE;
    }
}

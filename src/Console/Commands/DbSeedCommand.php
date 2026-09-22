<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;

#[AsCommand(name: 'db:seed', description: 'Seed the database with sample records')]
final class DbSeedCommand extends Command
{
    protected function handle(): int
    {
        (new DatabaseSeeder())->run();

        $this->info('Database seeded successfully.');

        return self::SUCCESS;
    }
}

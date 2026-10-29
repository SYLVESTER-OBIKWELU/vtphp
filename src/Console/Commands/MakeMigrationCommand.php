<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

/**
 * Like Laravel, the table is inferred from the name when not given:
 *
 *     create_posts_table          Schema::create('posts', …)
 *     add_slug_to_posts_table     Schema::table('posts', …)
 *     anything_else               empty up()/down()
 */
#[AsCommand(name: 'make:migration', description: 'Create a new migration file')]
final class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The migration name, e.g. create_posts_table');
        $this->addOption('create', null, InputOption::VALUE_REQUIRED, 'The table to be created');
        $this->addOption('table', null, InputOption::VALUE_REQUIRED, 'The table to migrate');
    }

    protected function handle(): int
    {
        $name = Str::snake(trim((string) $this->input->getArgument('name')));

        if (glob($this->app->databasePath("migrations/*_{$name}.php")) !== []) {
            $this->error("A {$name} migration already exists.");

            return self::FAILURE;
        }

        $create = $this->input->getOption('create');
        $table = $this->input->getOption('table');

        if (!is_string($create) && !is_string($table)) {
            if (preg_match('/^create_(\w+)_table$/', $name, $match) === 1) {
                $create = $match[1];
            } elseif (preg_match('/_(?:to|from|in)_(\w+)_table$/', $name, $match) === 1) {
                $table = $match[1];
            }
        }

        [$stub, $replacements] = match (true) {
            is_string($create) => ['migration.create.stub', ['table' => $create]],
            is_string($table) => ['migration.update.stub', ['table' => $table]],
            default => ['migration.stub', []],
        };

        $file = date('Y_m_d_His')."_{$name}.php";

        return $this->generate($stub, "database/migrations/{$file}", $replacements, 'Migration')
            ? self::SUCCESS
            : self::FAILURE;
    }
}

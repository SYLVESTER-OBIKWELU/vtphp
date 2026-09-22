<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:model', description: 'Create a new model class')]
final class MakeModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The model name');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $path = $this->app->appPath("Models/{$name}.php");

        if (is_file($path)) {
            $this->error("Model {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/model.stub'));
        $contents = str_replace('{{ class }}', $name, $stub);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Model created: app/Models/{$name}.php");

        return self::SUCCESS;
    }
}

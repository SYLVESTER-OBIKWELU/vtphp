<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:middleware', description: 'Create a new PSR-15 middleware class')]
final class MakeMiddlewareCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The middleware name');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $path = $this->app->appPath("Middleware/{$name}.php");

        if (is_file($path)) {
            $this->error("Middleware {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/middleware.stub'));
        $contents = str_replace('{{ class }}', $name, $stub);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Middleware created: app/Middleware/{$name}.php");

        return self::SUCCESS;
    }
}

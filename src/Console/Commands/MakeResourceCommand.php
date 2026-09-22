<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:resource', description: 'Create a new API resource class')]
final class MakeResourceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The resource name');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));

        if (!str_ends_with($name, 'Resource')) {
            $name .= 'Resource';
        }

        $path = $this->app->appPath("Resources/{$name}.php");

        if (is_file($path)) {
            $this->error("Resource {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/resource.stub'));
        $contents = str_replace('{{ class }}', $name, $stub);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Resource created: app/Resources/{$name}.php");

        return self::SUCCESS;
    }
}

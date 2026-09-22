<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use VtPhp\Console\Command;
use VtPhp\Support\Str;

#[AsCommand(name: 'make:controller', description: 'Create a new controller class')]
final class MakeControllerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The controller name');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = $this->app->appPath("Controllers/{$name}.php");

        if (is_file($path)) {
            $this->error("Controller {$name} already exists.");

            return self::FAILURE;
        }

        $stub = (string) file_get_contents($this->app->basePath('stubs/controller.stub'));
        $contents = str_replace('{{ class }}', $name, $stub);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);

        $this->info("Controller created: app/Controllers/{$name}.php");

        return self::SUCCESS;
    }
}

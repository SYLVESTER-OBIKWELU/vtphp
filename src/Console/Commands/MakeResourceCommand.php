<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:resource', description: 'Create a new API resource class')]
final class MakeResourceCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The resource name');
        $this->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'The Eloquent model the resource wraps');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $name = str_ends_with($name, 'Resource') ? $name : $name.'Resource';
        $model = $this->input->getOption('model');

        $generated = is_string($model) && $model !== ''
            ? $this->generate('resource.model.stub', "app/Http/Resources/{$name}.php", ['class' => $name, 'model' => Str::studly($model)], "Resource {$name}")
            : $this->generate('resource.stub', "app/Http/Resources/{$name}.php", ['class' => $name], "Resource {$name}");

        return $generated ? self::SUCCESS : self::FAILURE;
    }
}

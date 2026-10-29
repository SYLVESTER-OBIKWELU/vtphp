<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:policy', description: 'Create a new authorization policy class')]
final class MakePolicyCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The policy name, e.g. NotePolicy');
        $this->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Add viewAny/view/create/update/delete methods for this model');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $name = str_ends_with($name, 'Policy') ? $name : $name.'Policy';
        $model = $this->input->getOption('model');

        $generated = is_string($model) && $model !== ''
            ? $this->generate('policy.model.stub', "app/Policies/{$name}.php", [
                'class' => $name,
                'model' => Str::studly($model),
                'variable' => Str::camel($model),
            ], "Policy {$name}")
            : $this->generate('policy.stub', "app/Policies/{$name}.php", ['class' => $name], "Policy {$name}");

        return $generated ? self::SUCCESS : self::FAILURE;
    }
}

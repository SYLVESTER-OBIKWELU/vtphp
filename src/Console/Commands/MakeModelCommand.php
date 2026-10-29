<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

/**
 * Laravel-style model generator. Flags combine like Laravel's (-mcr, -a):
 *
 *     forge make:model Post -a        model, migration, seeder, API resource
 *                                     controller, Store/Update requests, JSON resource
 *     forge make:model Post -mc       model, migration, empty controller
 *     forge make:model Post -mrR      model, migration, resource controller + requests
 */
#[AsCommand(name: 'make:model', description: 'Create a new Eloquent model class')]
final class MakeModelCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The model name');
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate a migration, seeder, resource controller, form requests and JSON resource');
        $this->addOption('migration', 'm', InputOption::VALUE_NONE, 'Create a new migration for the model');
        $this->addOption('seed', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model');
        $this->addOption('controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model');
        $this->addOption('resource', 'r', InputOption::VALUE_NONE, 'Make the controller an API resource controller bound to the model');
        $this->addOption('api', null, InputOption::VALUE_NONE, 'Alias of --resource (VtPhp controllers are API-only)');
        $this->addOption('requests', 'R', InputOption::VALUE_NONE, 'Create Store/Update form requests for the model');
        $this->addOption('json-resource', 'j', InputOption::VALUE_NONE, 'Create a JSON resource for the model');
    }

    protected function handle(): int
    {
        $model = Str::studly((string) $this->input->getArgument('name'));

        if (!$this->generate('model.stub', "app/Models/{$model}.php", ['class' => $model], "Model {$model}")) {
            return self::FAILURE;
        }

        $all = (bool) $this->input->getOption('all');
        $option = fn (string $name): bool => $all || (bool) $this->input->getOption($name);

        $resourceController = $option('resource') || (bool) $this->input->getOption('api');
        $requests = $option('requests');
        $jsonResource = $option('json-resource');

        if ($option('migration')) {
            $this->call('make:migration', ['name' => 'create_'.Str::snake(Str::pluralStudly($model)).'_table']);
        }

        if ($option('seed')) {
            $this->call('make:seeder', ['name' => "{$model}Seeder"]);
        }

        if ($option('controller') || $resourceController) {
            // A model-bound controller also creates the requests/resource it uses.
            $this->call('make:controller', array_filter([
                'name' => "{$model}Controller",
                '--model' => $resourceController ? $model : null,
                '--requests' => $resourceController && $requests,
                '--json-resource' => $resourceController && $jsonResource,
            ]));
        }

        if ($requests && !$resourceController) {
            $this->call('make:request', ['name' => "Store{$model}Request"]);
            $this->call('make:request', ['name' => "Update{$model}Request"]);
        }

        if ($jsonResource && !$resourceController) {
            $this->call('make:resource', ['name' => $model, '--model' => $model]);
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use VtPhp\Console\Command;

#[AsCommand(name: 'make:controller', description: 'Create a new controller class')]
final class MakeControllerCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The controller name');
        $this->addOption('api', null, InputOption::VALUE_NONE, 'Generate an API resource controller (index, store, show, update, destroy)');
        $this->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Generate a resource controller bound to the given model');
        $this->addOption('requests', 'R', InputOption::VALUE_NONE, 'Generate Store/Update form requests and type-hint them (with --model)');
        $this->addOption('json-resource', 'j', InputOption::VALUE_NONE, 'Generate a JSON resource and return it (with --model)');
    }

    protected function handle(): int
    {
        $name = Str::studly((string) $this->input->getArgument('name'));
        $name = str_ends_with($name, 'Controller') ? $name : $name.'Controller';
        $model = $this->input->getOption('model');

        if (is_string($model) && $model !== '') {
            return $this->modelController($name, Str::studly($model));
        }

        $generated = $this->input->getOption('api')
            ? $this->generate('controller.api.stub', "app/Http/Controllers/{$name}.php", [
                'class' => $name,
                'parameter' => Str::camel(Str::singular(Str::beforeLast($name, 'Controller'))),
            ], "Controller {$name}")
            : $this->generate('controller.stub', "app/Http/Controllers/{$name}.php", ['class' => $name], "Controller {$name}");

        return $generated ? self::SUCCESS : self::FAILURE;
    }

    private function modelController(string $name, string $model): int
    {
        $variable = Str::camel($model);
        $requests = (bool) $this->input->getOption('requests');
        $jsonResource = (bool) $this->input->getOption('json-resource');
        $imports = ["App\\Models\\{$model}", 'VtPhp\\Http\\JsonResponse'];

        if ($requests) {
            $storeRequest = "Store{$model}Request";
            $updateRequest = "Update{$model}Request";
            $imports[] = "App\\Http\\Requests\\{$storeRequest}";
            $imports[] = "App\\Http\\Requests\\{$updateRequest}";
            $storeData = $updateData = '$request->validated()';
        } else {
            $storeRequest = $updateRequest = 'Request';
            $imports[] = 'VtPhp\\Http\\Request';
            $storeData = $updateData = "\$request->validate([\n            //\n        ])";
        }

        if ($jsonResource) {
            $resource = "{$model}Resource";
            $imports[] = "App\\Http\\Resources\\{$resource}";
            $imports[] = 'VtPhp\\Resources\\ResourceCollection';
            $indexMethod = <<<PHP
                    public function index(): ResourceCollection
                    {
                        return {$resource}::collection({$model}::query()->get());
                    }
                PHP;
            $returnType = $storeReturnType = $resource;
            $wrap = "{$resource}::make(\${$variable})";
            $storeReturn = "{$wrap}->status(201)";
        } else {
            $imports[] = 'Illuminate\\Database\\Eloquent\\Collection';
            $indexMethod = <<<PHP
                    /**
                     * @return Collection<int, {$model}>
                     */
                    public function index(): Collection
                    {
                        return {$model}::query()->get();
                    }
                PHP;
            $returnType = $model;
            $storeReturnType = 'JsonResponse';
            $wrap = "\${$variable}";
            $storeReturn = "response()->json(\${$variable}, 201)";
        }

        sort($imports);

        $generated = $this->generate('controller.model.stub', "app/Http/Controllers/{$name}.php", [
            'class' => $name,
            'model' => $model,
            'variable' => $variable,
            'imports' => implode("\n", array_map(static fn (string $class): string => "use {$class};", $imports)),
            'indexMethod' => $indexMethod,
            'storeRequest' => $storeRequest,
            'updateRequest' => $updateRequest,
            'storeData' => $storeData,
            'updateData' => $updateData,
            'returnType' => $returnType,
            'storeReturnType' => $storeReturnType,
            'storeReturn' => $storeReturn,
            'wrap' => $wrap,
        ], "Controller {$name}");

        if (!$generated) {
            return self::FAILURE;
        }

        if ($requests) {
            foreach (["Store{$model}Request", "Update{$model}Request"] as $request) {
                if (!$this->exists("app/Http/Requests/{$request}.php")) {
                    $this->call('make:request', ['name' => $request]);
                }
            }
        }

        if ($jsonResource && !$this->exists("app/Http/Resources/{$model}Resource.php")) {
            $this->call('make:resource', ['name' => $model, '--model' => $model]);
        }

        $this->line(sprintf(
            'Register its routes in routes/api.php: <info>$router->apiResource(\'%s\', %s::class);</info>',
            Str::plural(Str::kebab($model)),
            $name,
        ));

        return self::SUCCESS;
    }
}

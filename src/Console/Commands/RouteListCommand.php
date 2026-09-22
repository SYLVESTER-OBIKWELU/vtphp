<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\Routing\Router;

#[AsCommand(name: 'route:list', description: 'List all registered routes')]
final class RouteListCommand extends Command
{
    protected function handle(): int
    {
        $router = $this->app->make(Router::class);
        $rows = [];

        foreach ($router->routes() as $name => $route) {
            $action = $route->getDefault('_action');

            $actionLabel = match (true) {
                is_array($action) => (is_string($action[0]) ? $action[0] : $action[0]::class).'@'.$action[1],
                is_string($action) => $action,
                default => 'Closure',
            };

            $rows[] = [implode('|', $route->getMethods()), $route->getPath(), $name, $actionLabel];
        }

        $this->io->table(['Method', 'URI', 'Name', 'Action'], $rows);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Bootstrap;

use VtPhp\Config\Repository;
use VtPhp\Foundation\Application;

final class LoadConfiguration
{
    public function bootstrap(Application $app): void
    {
        $items = [];
        $configPath = $app->configPath();

        if (is_dir($configPath)) {
            foreach (glob($configPath.'/*.php') ?: [] as $file) {
                $items[pathinfo($file, PATHINFO_FILENAME)] = require $file;
            }
        }

        $repository = new Repository($items);

        $app->instance(Repository::class, $repository);
        $app->alias('config', Repository::class);
    }
}

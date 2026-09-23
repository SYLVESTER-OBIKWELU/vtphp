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

        // Pin PHP's default timezone to the configured app timezone so that
        // native date/time functions and Carbon's date-casting (which falls
        // back to date_default_timezone_get() when parsing naive datetime
        // strings from the database) stay consistent with the UTC-based
        // now()/database writes throughout the framework. Without this, the
        // ambient system timezone (e.g. Europe/Berlin) would be used to
        // reinterpret UTC-stored timestamps, producing multi-hour drift.
        date_default_timezone_set((string) $repository->get('app.timezone', 'UTC'));
    }
}

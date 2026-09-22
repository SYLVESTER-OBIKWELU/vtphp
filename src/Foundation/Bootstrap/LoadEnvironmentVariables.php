<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Bootstrap;

use Symfony\Component\Dotenv\Dotenv;
use VtPhp\Foundation\Application;

final class LoadEnvironmentVariables
{
    public function bootstrap(Application $app): void
    {
        $envFile = $app->basePath('.env');

        if (is_file($envFile)) {
            (new Dotenv())->usePutenv()->load($envFile);
        }
    }
}

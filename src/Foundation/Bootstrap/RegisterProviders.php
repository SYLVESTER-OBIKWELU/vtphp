<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Bootstrap;

use VtPhp\Foundation\Application;

final class RegisterProviders
{
    public function bootstrap(Application $app): void
    {
        $providers = $app->make('config')->get('app.providers', []);

        foreach ($providers as $provider) {
            $app->register($provider);
        }
    }
}

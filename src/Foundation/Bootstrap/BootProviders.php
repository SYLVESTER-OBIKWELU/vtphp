<?php

declare(strict_types=1);

namespace VtPhp\Foundation\Bootstrap;

use VtPhp\Foundation\Application;

final class BootProviders
{
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}

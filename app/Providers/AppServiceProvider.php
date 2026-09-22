<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\InMemoryUserRepository;
use App\Repositories\UserRepositoryInterface;
use VtPhp\Foundation\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepositoryInterface::class, InMemoryUserRepository::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\EloquentUserProvider;
use App\Repositories\EloquentUserRepository;
use App\Repositories\UserRepositoryInterface;
use VtPhp\Auth\UserProviderInterface;
use VtPhp\Foundation\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->singleton(UserProviderInterface::class, EloquentUserProvider::class);
    }
}

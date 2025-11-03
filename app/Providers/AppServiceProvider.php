<?php

namespace App\Providers;

use Core\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register application bindings
        // Example:
        // $this->app->bind('mailer', function() {
        //     return new Mailer(config('mail'));
        // });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Bootstrap application services
        // Example: Set default timezone
        date_default_timezone_set(config('app.timezone', 'UTC'));
    }
}

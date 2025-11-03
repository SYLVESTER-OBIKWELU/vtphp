<?php

namespace Core;

abstract class ServiceProvider
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    abstract public function register();

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}

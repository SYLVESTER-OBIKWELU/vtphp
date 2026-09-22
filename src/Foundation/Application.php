<?php

declare(strict_types=1);

namespace VtPhp\Foundation;

use Psr\Container\ContainerInterface;
use VtPhp\Container\Container;

class Application extends Container
{
    private static ?self $instance = null;

    protected string $basePath;

    /** @var array<class-string<ServiceProvider>, ServiceProvider> */
    protected array $serviceProviders = [];

    protected bool $booted = false;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\\/');

        self::$instance = $this;

        $this->instance(self::class, $this);
        $this->instance(ContainerInterface::class, $this);
        $this->alias('app', self::class);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The application has not been bootstrapped yet.');
        }

        return self::$instance;
    }

    public static function bootstrap(string $basePath): self
    {
        $app = new self($basePath);

        (new Bootstrap\LoadEnvironmentVariables())->bootstrap($app);
        (new Bootstrap\LoadConfiguration())->bootstrap($app);

        $app->register(CoreServiceProvider::class);

        (new Bootstrap\RegisterProviders())->bootstrap($app);
        (new Bootstrap\BootProviders())->bootstrap($app);

        return $app;
    }

    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath.'/config', $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath.'/storage', $path);
    }

    public function databasePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath.'/database', $path);
    }

    public function appPath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath.'/app', $path);
    }

    private function joinPaths(string $base, string $path): string
    {
        return $path === '' ? $base : $base.'/'.ltrim($path, '\\/');
    }

    public function register(string $providerClass): ServiceProvider
    {
        if (isset($this->serviceProviders[$providerClass])) {
            return $this->serviceProviders[$providerClass];
        }

        /** @var ServiceProvider $provider */
        $provider = new $providerClass($this);
        $provider->register();

        $this->serviceProviders[$providerClass] = $provider;

        if ($this->booted) {
            $provider->boot();
        }

        return $provider;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    public function environment(): string
    {
        return (string) $this->make('config')->get('app.env', 'production');
    }

    public function isDebug(): bool
    {
        return (bool) $this->make('config')->get('app.debug', false);
    }
}

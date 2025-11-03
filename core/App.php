<?php

namespace Core;

use Core\Router;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ExceptionHandler;

class App
{
    protected static $instance;
    protected $router;
    protected $db;
    protected $config = [];
    protected $bindings = [];
    protected $providers = [];
    protected $loadedProviders = [];
    protected $bootedProviders = [];

    public function __construct()
    {
        self::$instance = $this;
        $this->loadConfig();
        $this->registerErrorHandlers();
        $this->router = new Router();
        
        // Make router globally accessible
        $GLOBALS['router'] = $this->router;
        
        $this->db = Database::getInstance($this->config['database']);
        $this->registerServiceProviders();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function loadConfig()
    {
        $configPath = dirname(__DIR__) . '/config';
        if (is_dir($configPath)) {
            foreach (glob($configPath . '/*.php') as $file) {
                $key = basename($file, '.php');
                $this->config[$key] = require $file;
            }
        }
    }

    protected function registerErrorHandlers()
    {
        $handler = new ExceptionHandler();
        set_exception_handler([$handler, 'handle']);
        set_error_handler([$handler, 'handleError']);
        register_shutdown_function([$handler, 'handleShutdown']);
    }

    public function run()
    {
        $request = new Request();
        $response = new Response();
        
        try {
            $result = $this->router->dispatch($request);
            $response->send($result);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function router()
    {
        return $this->router;
    }

    public function loadRoutes($routeFile)
    {
        $router = $this->router;
        require $routeFile;
    }

    protected function registerServiceProviders()
    {
        if (isset($this->config['app']['providers'])) {
            foreach ($this->config['app']['providers'] as $provider) {
                $this->register($provider);
            }
        }
    }

    public function register($provider)
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        $this->loadedProviders[] = $provider;

        return $provider;
    }

    public function boot()
    {
        foreach ($this->loadedProviders as $provider) {
            if (!in_array($provider, $this->bootedProviders)) {
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                }
                $this->bootedProviders[] = $provider;
            }
        }
    }

    public function db()
    {
        return $this->db;
    }

    public function config($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function bind($key, $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function resolve($key)
    {
        if (isset($this->bindings[$key])) {
            return call_user_func($this->bindings[$key]);
        }
        return null;
    }
}

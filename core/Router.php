<?php

namespace Core;

class Router
{
    protected $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];
    
    protected $middlewares = [];
    protected $groupStack = [];
    protected $currentGroup = null;
    protected $namedRoutes = [];
    protected $lastRoute = null;

    public function getRoutes()
    {
        return $this->routes;
    }

    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    public function get($uri, $action)
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post($uri, $action)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put($uri, $action)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch($uri, $action)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete($uri, $action)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function resource($uri, $controller)
    {
        $this->get($uri, [$controller, 'index']);
        $this->get($uri . '/create', [$controller, 'create']);
        $this->post($uri, [$controller, 'store']);
        $this->get($uri . '/{id}', [$controller, 'show']);
        $this->get($uri . '/{id}/edit', [$controller, 'edit']);
        $this->put($uri . '/{id}', [$controller, 'update']);
        $this->delete($uri . '/{id}', [$controller, 'destroy']);
    }

    public function apiResource($uri, $controller)
    {
        $this->get($uri, [$controller, 'index']);
        $this->post($uri, [$controller, 'store']);
        $this->get($uri . '/{id}', [$controller, 'show']);
        $this->put($uri . '/{id}', [$controller, 'update']);
        $this->delete($uri . '/{id}', [$controller, 'destroy']);
    }

    public function group($attributes, $callback)
    {
        $this->groupStack[] = $attributes;
        call_user_func($callback, $this);
        array_pop($this->groupStack);
    }

    protected function addRoute($method, $uri, $action)
    {
        $uri = $this->applyGroupPrefix($uri);
        $middleware = $this->gatherMiddleware();

        $route = [
            'uri' => trim($uri, '/'),
            'action' => $action,
            'middleware' => $middleware,
            'name' => null,
            'where' => [],
            'method' => $method
        ];

        $this->routes[$method][$route['uri']] = $route;
        $this->lastRoute = &$this->routes[$method][$route['uri']];
        
        return $this;
    }

    /**
     * Set name for the last registered route
     */
    public function name($name)
    {
        if ($this->lastRoute !== null) {
            $this->lastRoute['name'] = $name;
            $this->namedRoutes[$name] = $this->lastRoute;
        }
        return $this;
    }

    /**
     * Add middleware to the last registered route
     */
    public function middleware($middleware)
    {
        if ($this->lastRoute !== null) {
            $middleware = is_array($middleware) ? $middleware : [$middleware];
            $this->lastRoute['middleware'] = array_merge(
                $this->lastRoute['middleware'],
                $middleware
            );
        }
        return $this;
    }

    /**
     * Add where constraints to the last registered route
     */
    public function where($param, $pattern = null)
    {
        if ($this->lastRoute !== null) {
            if (is_array($param)) {
                $this->lastRoute['where'] = array_merge($this->lastRoute['where'], $param);
            } else {
                $this->lastRoute['where'][$param] = $pattern;
            }
        }
        return $this;
    }

    /**
     * Set prefix for route group
     */
    public function prefix($prefix)
    {
        if (!empty($this->groupStack)) {
            $lastGroup = &$this->groupStack[count($this->groupStack) - 1];
            $lastGroup['prefix'] = $prefix;
        }
        return $this;
    }

    /**
     * Generate URL for named route
     */
    public function route($name, $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        $route = $this->namedRoutes[$name];
        $uri = $route['uri'];

        // Replace parameters
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Check if route exists
     */
    public function has($name)
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get current route name
     */
    public function currentRouteName()
    {
        if ($this->lastRoute && isset($this->lastRoute['name'])) {
            return $this->lastRoute['name'];
        }
        return null;
    }

    protected function applyGroupPrefix($uri)
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix . '/' . trim($uri, '/');
    }

    protected function gatherMiddleware()
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array)$group['middleware']);
            }
        }
        return $middleware;
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();
        $uri = trim($request->uri(), '/');

        if (!isset($this->routes[$method])) {
            throw new \Exception("Method not allowed", 405);
        }

        foreach ($this->routes[$method] as $route) {
            // Try matching with constraints first
            $params = isset($route['where']) && !empty($route['where']) 
                ? $this->matchRouteWithConstraints($route, $uri)
                : $this->matchRoute($route['uri'], $uri);
            
            if ($params !== false) {
                $request->setParams($params);
                $this->lastRoute = $route;
                
                // Apply middleware
                foreach ($route['middleware'] as $middleware) {
                    if (class_exists($middleware)) {
                        $middlewareInstance = new $middleware();
                        $middlewareInstance->handle($request);
                    }
                }

                return $this->callAction($route['action'], $request);
            }
        }

        throw new \Exception("Route not found: {$method} /{$uri}", 404);
    }

    protected function matchRoute($routeUri, $requestUri)
    {
        $routePattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $routeUri);
        $routePattern = '#^' . $routePattern . '$#';

        if (preg_match($routePattern, $requestUri, $matches)) {
            array_shift($matches);
            
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $paramNames);
            $params = [];
            
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
            
            return $params;
        }

        return false;
    }

    /**
     * Match route with where constraints
     */
    protected function matchRouteWithConstraints($route, $requestUri)
    {
        $routeUri = $route['uri'];
        $where = $route['where'] ?? [];

        // Build pattern with constraints
        $pattern = $routeUri;
        foreach ($where as $param => $constraint) {
            $pattern = str_replace('{' . $param . '}', '(' . $constraint . ')', $pattern);
        }

        // Replace remaining parameters
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches);
            
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $paramNames);
            $params = [];
            
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
            
            return $params;
        }

        return false;
    }

    protected function callAction($action, $request)
    {
        if (is_callable($action)) {
            return call_user_func($action, $request);
        }

        if (is_array($action)) {
            [$controller, $method] = $action;
            
            if (is_string($controller)) {
                $controller = new $controller();
            }

            if (!method_exists($controller, $method)) {
                throw new \Exception("Method {$method} not found in controller");
            }

            return $controller->$method($request);
        }

        throw new \Exception("Invalid route action");
    }
}

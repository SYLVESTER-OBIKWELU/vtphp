<?php

namespace Core;

class Request
{
    protected $params = [];
    protected $queryParams = [];
    protected $postData = [];
    protected $files = [];
    protected $headers = [];
    protected $method;
    protected $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->queryParams = $_GET;
        $this->postData = $_POST;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();

        // Parse JSON body if content type is JSON
        if ($this->isJson()) {
            $json = json_decode(file_get_contents('php://input'), true);
            $this->postData = $json ?: [];
        }

        // Handle PUT/PATCH/DELETE method override
        if (isset($this->postData['_method'])) {
            $this->method = strtoupper($this->postData['_method']);
        }
    }

    public function method()
    {
        return $this->method;
    }

    public function uri()
    {
        return $this->uri;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function params($key = null, $default = null)
    {
        if ($key === null) {
            return $this->params;
        }
        return $this->params[$key] ?? $default;
    }

    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return array_merge($this->queryParams, $this->postData, $this->params);
        }
        
        return $this->postData[$key] 
            ?? $this->queryParams[$key] 
            ?? $this->params[$key] 
            ?? $default;
    }

    public function all()
    {
        return array_merge($this->queryParams, $this->postData, $this->params);
    }

    public function only(...$keys)
    {
        $result = [];
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }
        
        return $result;
    }

    public function except(...$keys)
    {
        $all = $this->all();
        
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        
        return $all;
    }

    public function has($key)
    {
        return isset($this->all()[$key]);
    }

    public function file($key)
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile($key)
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function header($key, $default = null)
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$key] ?? $default;
    }

    public function headers()
    {
        return $this->headers;
    }

    public function isJson()
    {
        return strpos($this->header('CONTENT_TYPE', ''), 'application/json') !== false;
    }

    public function wantsJson()
    {
        return $this->isJson() || strpos($this->header('ACCEPT', ''), 'application/json') !== false;
    }

    public function ip()
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    protected function parseHeaders()
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }
        }
        
        return $headers;
    }

    public function validate(array $rules)
    {
        $validator = new Validator();
        return $validator->validate($this->all(), $rules);
    }
}

<?php

namespace Core;

abstract class Controller
{
    protected $request;
    protected $middleware = [];

    public function __construct()
    {
        $this->request = new Request();
    }

    protected function view($view, $data = [])
    {
        return View::render($view, $data);
    }

    protected function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    protected function redirect($url, $status = 302)
    {
        header("Location: {$url}", true, $status);
        exit;
    }

    protected function validate($data, $rules)
    {
        $validator = new Validator();
        return $validator->validate($data, $rules);
    }

    protected function abort($code, $message = '')
    {
        http_response_code($code);
        throw new \Exception($message ?: "Error {$code}", $code);
    }

    public function middleware($middleware)
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function getMiddleware()
    {
        return $this->middleware;
    }
}

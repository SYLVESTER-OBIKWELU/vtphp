<?php

namespace Core;

abstract class Middleware
{
    abstract public function handle(Request $request);

    protected function next()
    {
        return true;
    }

    protected function abort($code = 403, $message = 'Forbidden')
    {
        http_response_code($code);
        throw new \Exception($message, $code);
    }
}

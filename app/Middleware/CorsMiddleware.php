<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Request;

class CorsMiddleware extends Middleware
{
    public function handle(Request $request)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        if ($request->method() === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return $this->next();
    }
}

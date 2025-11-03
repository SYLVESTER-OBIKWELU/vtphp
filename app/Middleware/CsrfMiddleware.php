<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Request;

class CsrfMiddleware extends Middleware
{
    public function handle(Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        // Verify CSRF token for state-changing requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
            
            if (!$token || $token !== $_SESSION['_token']) {
                $this->abort(419, 'CSRF token mismatch');
            }
        }

        return $this->next();
    }
}

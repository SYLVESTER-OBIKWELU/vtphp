<?php

namespace App\Middleware;

use Core\Middleware;
use Core\Request;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request)
    {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            if ($request->wantsJson()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            
            header('Location: /login');
            exit;
        }

        return $this->next();
    }
}

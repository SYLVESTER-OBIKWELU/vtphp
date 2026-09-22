<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param array<int, string> $allowedMethods
     */
    public function __construct(array $allowedMethods, string $message = 'Method not allowed.')
    {
        parent::__construct(405, $message, 'METHOD_NOT_ALLOWED', ['allowed_methods' => $allowedMethods]);
    }
}

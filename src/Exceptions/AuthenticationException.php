<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'Unauthenticated.')
    {
        parent::__construct(401, $message, 'UNAUTHENTICATED');
    }
}

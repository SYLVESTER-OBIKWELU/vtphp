<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'You need to log in to do this.')
    {
        parent::__construct(401, $message, 'UNAUTHENTICATED');
    }
}

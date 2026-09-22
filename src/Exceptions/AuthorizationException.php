<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'This action is unauthorized.')
    {
        parent::__construct(403, $message, 'FORBIDDEN');
    }
}

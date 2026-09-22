<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class TooManyRequestsHttpException extends HttpException
{
    public function __construct(string $message = 'Too many requests.')
    {
        parent::__construct(429, $message, 'TOO_MANY_REQUESTS');
    }
}

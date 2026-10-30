<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class ConflictHttpException extends HttpException
{
    public function __construct(string $message = 'This clashes with the current state of the data. Reload and try again.')
    {
        parent::__construct(409, $message, 'CONFLICT');
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class ConflictHttpException extends HttpException
{
    public function __construct(string $message = 'Conflict.')
    {
        parent::__construct(409, $message, 'CONFLICT');
    }
}

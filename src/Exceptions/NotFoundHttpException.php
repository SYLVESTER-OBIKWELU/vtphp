<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Not found.')
    {
        parent::__construct(404, $message, 'NOT_FOUND');
    }
}

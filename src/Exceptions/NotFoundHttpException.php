<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'What you asked for could not be found.')
    {
        parent::__construct(404, $message, 'NOT_FOUND');
    }
}

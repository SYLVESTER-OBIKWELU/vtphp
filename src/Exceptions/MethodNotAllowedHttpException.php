<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param array<int, string> $allowedMethods
     */
    public function __construct(array $allowedMethods, string $message = '')
    {
        parent::__construct(
            405,
            $message !== '' ? $message : 'This URL does not accept that HTTP method. Use '.implode(' or ', $allowedMethods).'.',
            'METHOD_NOT_ALLOWED',
            ['allowed_methods' => $allowedMethods],
            headers: ['Allow' => implode(', ', $allowedMethods)],
        );
    }
}

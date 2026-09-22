<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct(422, $message, 'VALIDATION_ERROR');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

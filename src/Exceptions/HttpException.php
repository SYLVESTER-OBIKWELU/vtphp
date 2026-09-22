<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly string $errorCode = 'HTTP_ERROR',
        private readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

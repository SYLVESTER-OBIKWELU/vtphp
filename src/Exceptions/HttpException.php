<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers Added to the rendered error response (e.g. Retry-After).
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly string $errorCode = 'HTTP_ERROR',
        private readonly array $context = [],
        ?\Throwable $previous = null,
        private readonly array $headers = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
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

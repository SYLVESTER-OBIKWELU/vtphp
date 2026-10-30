<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

/**
 * An error with an HTTP status. An empty message or the generic
 * 'HTTP_ERROR' code is filled in from HttpStatus, so
 * `new HttpException(503)` already reads "SERVICE_UNAVAILABLE: The service
 * is temporarily unavailable...".
 */
class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context Extra fields added to the `error` object.
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
        parent::__construct($message !== '' ? $message : HttpStatus::message($statusCode), 0, $previous);
    }

    /**
     * The exception for a status, as thrown by abort(): 401, 403, 404 and 409
     * get their specific classes so `catch (NotFoundHttpException)` works.
     *
     * @param array<string, string> $headers
     */
    public static function forStatus(int $status, string $message = '', array $headers = []): self
    {
        if ($headers === []) {
            $specific = match ($status) {
                401 => new AuthenticationException($message !== '' ? $message : HttpStatus::message(401)),
                403 => new AuthorizationException($message !== '' ? $message : HttpStatus::message(403)),
                404 => new NotFoundHttpException($message !== '' ? $message : HttpStatus::message(404)),
                409 => new ConflictHttpException($message !== '' ? $message : HttpStatus::message(409)),
                default => null,
            };

            if ($specific !== null) {
                return $specific;
            }
        }

        return new self($status, $message, HttpStatus::code($status), headers: $headers);
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
        return $this->errorCode === 'HTTP_ERROR' ? HttpStatus::code($this->statusCode) : $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

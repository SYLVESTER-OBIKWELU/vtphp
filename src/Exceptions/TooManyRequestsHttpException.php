<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class TooManyRequestsHttpException extends HttpException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $message = 'You are sending requests too quickly. Wait a moment and try again.', ?int $retryAfter = null, array $headers = [])
    {
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        parent::__construct(429, $message, 'TOO_MANY_REQUESTS', $retryAfter !== null ? ['retry_after' => $retryAfter] : [], headers: $headers);
    }
}

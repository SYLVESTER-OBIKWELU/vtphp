<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

/**
 * The error code, title and default message VtPhp uses for each HTTP error
 * status, so every error reads the same whether VtPhp or your code sent it.
 */
final class HttpStatus
{
    /** @var array<int, array{0: string, 1: string, 2: string}> status => [code, title, message] */
    private const ERRORS = [
        400 => ['BAD_REQUEST', 'Bad request', 'The request could not be understood. Check its format and try again.'],
        401 => ['UNAUTHENTICATED', 'Not logged in', 'You need to log in to do this.'],
        402 => ['PAYMENT_REQUIRED', 'Payment required', 'Payment is required to do this.'],
        403 => ['FORBIDDEN', 'Not allowed', 'You are not allowed to do this.'],
        404 => ['NOT_FOUND', 'Not found', 'What you asked for could not be found.'],
        405 => ['METHOD_NOT_ALLOWED', 'Method not allowed', 'This URL does not accept that HTTP method.'],
        406 => ['NOT_ACCEPTABLE', 'Not acceptable', 'This response is not available in a format you accept.'],
        408 => ['REQUEST_TIMEOUT', 'Request timeout', 'The request took too long to arrive. Try again.'],
        409 => ['CONFLICT', 'Conflict', 'This clashes with the current state of the data. Reload and try again.'],
        410 => ['GONE', 'Gone', 'This no longer exists.'],
        411 => ['LENGTH_REQUIRED', 'Length required', 'Send a Content-Length header with the request.'],
        412 => ['PRECONDITION_FAILED', 'Precondition failed', 'The data changed since you last fetched it.'],
        413 => ['PAYLOAD_TOO_LARGE', 'Too large', 'The request body is too large.'],
        414 => ['URI_TOO_LONG', 'URL too long', 'The URL is too long.'],
        415 => ['UNSUPPORTED_MEDIA_TYPE', 'Unsupported format', 'Send the body in a supported format, such as JSON.'],
        422 => ['VALIDATION_ERROR', 'Invalid input', 'The given data was invalid.'],
        423 => ['LOCKED', 'Locked', 'This is locked and cannot be changed right now.'],
        425 => ['TOO_EARLY', 'Too early', 'Try the request again later.'],
        428 => ['PRECONDITION_REQUIRED', 'Precondition required', 'This request must be conditional (send If-Match).'],
        429 => ['TOO_MANY_REQUESTS', 'Too many requests', 'You are sending requests too quickly. Wait a moment and try again.'],
        431 => ['HEADERS_TOO_LARGE', 'Headers too large', 'The request headers are too large.'],
        451 => ['UNAVAILABLE_FOR_LEGAL_REASONS', 'Unavailable', 'This is unavailable for legal reasons.'],
        500 => ['INTERNAL_SERVER_ERROR', 'Server error', 'Something went wrong on our side. Please try again later.'],
        501 => ['NOT_IMPLEMENTED', 'Not implemented', 'This is not supported yet.'],
        502 => ['BAD_GATEWAY', 'Bad gateway', 'A service we depend on sent an invalid response. Please try again later.'],
        503 => ['SERVICE_UNAVAILABLE', 'Unavailable', 'The service is temporarily unavailable. Please try again later.'],
        504 => ['GATEWAY_TIMEOUT', 'Gateway timeout', 'A service we depend on took too long to respond. Please try again later.'],
    ];

    public static function code(int $status): string
    {
        return self::ERRORS[$status][0] ?? ($status >= 500 ? 'SERVER_ERROR' : 'CLIENT_ERROR');
    }

    public static function title(int $status): string
    {
        return self::ERRORS[$status][1] ?? ($status >= 500 ? 'Server error' : 'Request error');
    }

    public static function message(int $status): string
    {
        return self::ERRORS[$status][2] ?? ($status >= 500 ? self::ERRORS[500][2] : 'The request could not be completed.');
    }

    public static function isError(int $status): bool
    {
        return $status >= 400 && $status <= 599;
    }
}

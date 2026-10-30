<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

final class ValidationException extends HttpException
{
    /**
     * Without a message, the first error is used, e.g. "The email field is
     * required. (and 2 more errors)", so the reason shows even where only
     * the message is displayed.
     *
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(private readonly array $errors, string $message = '')
    {
        parent::__construct(422, $message !== '' ? $message : self::summarize($errors), 'VALIDATION_ERROR');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private static function summarize(array $errors): string
    {
        $messages = array_merge(...array_values(array_map('array_values', $errors)));
        $first = $messages[0] ?? null;

        if (!is_string($first)) {
            return HttpStatus::message(422);
        }

        $others = count($messages) - 1;

        return match (true) {
            $others === 0 => $first,
            $others === 1 => "{$first} (and 1 more error)",
            default => "{$first} (and {$others} more errors)",
        };
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use VtPhp\Http\JsonResponse;

final class ExceptionHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function render(\Throwable $e, ?ServerRequestInterface $request = null): JsonResponse
    {
        [$status, $code, $extra] = match (true) {
            $e instanceof ValidationException => [422, $e->getErrorCode(), ['errors' => $e->getErrors()]],
            $e instanceof HttpException => [$e->getStatusCode(), $e->getErrorCode(), $e->getContext()],
            default => [500, 'INTERNAL_SERVER_ERROR', []],
        };

        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'request_id' => $request?->getAttribute('request_id'),
            ]);
        }

        $payload = [
            'success' => false,
            'error' => array_merge([
                'code' => $code,
                'message' => $status >= 500 && !$this->debug ? 'Internal server error.' : $e->getMessage(),
            ], $extra),
        ];

        if ($this->debug) {
            $payload['error']['exception'] = $e::class;
            $payload['error']['trace'] = explode("\n", $e->getTraceAsString());
        }

        return new JsonResponse($payload, $status);
    }
}

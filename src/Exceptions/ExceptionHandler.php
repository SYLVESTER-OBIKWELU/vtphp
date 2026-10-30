<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use VtPhp\Http\JsonResponse;

/**
 * Turns any exception into an error response:
 *
 * - API clients get JSON: {"success": false, "error": {"code", "message", ...}}.
 * - A browser opening the URL directly gets a readable HTML page instead.
 * - Unexpected exceptions become a 500 whose details are logged, never shown
 *   (unless APP_DEBUG=true).
 * - In debug mode, errors also say where they were thrown, 500s include a
 *   stack trace, and a 404 suggests the URL you probably meant.
 */
final class ExceptionHandler
{
    private const MAX_TRACE_FRAMES = 30;

    /**
     * @param string $basePath Stripped from file paths in debug output.
     * @param (\Closure(ServerRequestInterface): ?string)|null $notFoundHint A tip for a 404 (debug mode only).
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
        private readonly string $basePath = '',
        private readonly ?\Closure $notFoundHint = null,
    ) {
    }

    public function render(\Throwable $e, ?ServerRequestInterface $request = null): ResponseInterface
    {
        $original = $e;

        // `User::query()->findOrFail($id)` → 404, without leaking the model's FQCN.
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException((new \ReflectionClass($e->getModel()))->getShortName().' not found.');
        }

        $http = $e instanceof HttpException ? $e : null;
        $status = $http !== null && HttpStatus::isError($http->getStatusCode()) ? $http->getStatusCode() : 500;
        $requestId = $request?->getAttribute('request_id');
        $requestId = is_string($requestId) && $requestId !== '' ? $requestId : null;

        if ($status >= 500) {
            $this->logger->error($original->getMessage(), array_filter([
                'exception' => $original::class,
                'file' => $original->getFile().':'.$original->getLine(),
                'request_id' => $requestId,
                'url' => $request !== null ? $request->getMethod().' '.$request->getUri()->getPath() : null,
            ]));
        }

        $error = [
            'code' => $http?->getErrorCode() ?? HttpStatus::code(500),
            // Messages of exceptions you didn't mean to show (bugs, database
            // errors) can contain secrets, so they're replaced unless debugging.
            'message' => $http !== null || $this->debug ? $e->getMessage() : HttpStatus::message(500),
        ];

        if ($e instanceof ValidationException) {
            $error['errors'] = $e->getErrors();
        } elseif ($http !== null) {
            $error += $http->getContext();
        }

        if ($requestId !== null) {
            $error['request_id'] = $requestId;
        }

        if ($this->debug) {
            $hint = $e instanceof RouteNotFoundException && $request !== null && $this->notFoundHint !== null
                ? ($this->notFoundHint)($request)
                : null;

            if ($hint !== null) {
                $error['hint'] = $hint;
            }

            $error['debug'] = $this->debugDetails($original, withTrace: $status >= 500);
        }

        $headers = $http?->getHeaders() ?? [];

        if ($request !== null && self::wantsHtml($request)) {
            return ErrorPage::render($status, $error, $headers);
        }

        return new JsonResponse(['success' => false, 'error' => $error], $status, $headers);
    }

    /**
     * A browser navigating to the URL (Accept: text/html), rather than code
     * calling the API (fetch, axios, curl, mobile apps).
     */
    public static function wantsHtml(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));

        return str_contains($accept, 'text/html')
            && !str_contains($accept, 'application/json')
            && !$request->hasHeader('X-Requested-With');
    }

    /**
     * @return array{exception: string, location: string, trace?: list<string>}
     */
    private function debugDetails(\Throwable $e, bool $withTrace): array
    {
        $details = [
            'exception' => $e::class,
            'location' => $this->relative($e->getFile()).':'.$e->getLine(),
        ];

        if ($withTrace) {
            $details['trace'] = [];

            foreach (array_slice($e->getTrace(), 0, self::MAX_TRACE_FRAMES) as $i => $frame) {
                $where = isset($frame['file']) ? $this->relative($frame['file']).':'.($frame['line'] ?? '?') : '[internal]';
                $call = ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()';
                $details['trace'][] = "#{$i} {$where} {$call}";
            }
        }

        return $details;
    }

    private function relative(string $file): string
    {
        $base = rtrim(str_replace('\\', '/', (realpath($this->basePath) ?: $this->basePath)), '/');
        $file = str_replace('\\', '/', $file);

        return $base !== '' && str_starts_with($file, $base.'/') ? substr($file, strlen($base) + 1) : $file;
    }
}

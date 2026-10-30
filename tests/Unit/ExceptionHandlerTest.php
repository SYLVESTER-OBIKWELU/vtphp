<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VtPhp\Exceptions\AuthenticationException;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Exceptions\ConflictHttpException;
use VtPhp\Exceptions\ExceptionHandler;
use VtPhp\Exceptions\HttpException;
use VtPhp\Exceptions\MethodNotAllowedHttpException;
use VtPhp\Exceptions\NotFoundHttpException;
use VtPhp\Exceptions\TooManyRequestsHttpException;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Tests\Support\TestLogger;

final class ExceptionHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: \Throwable, 1: int, 2: string}>
     */
    public static function exceptions(): iterable
    {
        yield 'validation' => [new ValidationException(['email' => ['Bad.']]), 422, 'VALIDATION_ERROR'];
        yield 'authentication' => [new AuthenticationException(), 401, 'UNAUTHENTICATED'];
        yield 'authorization' => [new AuthorizationException(), 403, 'FORBIDDEN'];
        yield 'not found' => [new NotFoundHttpException(), 404, 'NOT_FOUND'];
        yield 'method not allowed' => [new MethodNotAllowedHttpException(['GET']), 405, 'METHOD_NOT_ALLOWED'];
        yield 'conflict' => [new ConflictHttpException(), 409, 'CONFLICT'];
        yield 'too many requests' => [new TooManyRequestsHttpException(), 429, 'TOO_MANY_REQUESTS'];
        yield 'custom http' => [new HttpException(418, 'Short and stout.', 'TEAPOT'), 418, 'TEAPOT'];
        yield 'unexpected' => [new \RuntimeException('boom'), 500, 'INTERNAL_SERVER_ERROR'];
    }

    #[DataProvider('exceptions')]
    public function test_exceptions_map_to_status_and_error_code(\Throwable $e, int $status, string $code): void
    {
        $response = (new ExceptionHandler(new TestLogger(), false))->render($e);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame($code, $body['error']['code']);
        $this->assertArrayNotHasKey('trace', $body['error']);
    }

    public function test_validation_errors_and_http_context_are_included(): void
    {
        $handler = new ExceptionHandler(new TestLogger(), false);

        $validation = json_decode((string) $handler->render(new ValidationException(['email' => ['Bad.']]))->getBody(), true);
        $this->assertSame(['email' => ['Bad.']], $validation['error']['errors']);

        $notAllowed = json_decode((string) $handler->render(new MethodNotAllowedHttpException(['GET', 'POST']))->getBody(), true);
        $this->assertSame(['GET', 'POST'], $notAllowed['error']['allowed_methods']);
    }

    public function test_production_mode_hides_internal_error_details(): void
    {
        $body = json_decode((string) (new ExceptionHandler(new TestLogger(), false))->render(new \RuntimeException('DB password is hunter2'))->getBody(), true);

        $this->assertSame('Something went wrong on our side. Please try again later.', $body['error']['message']);
        $this->assertArrayNotHasKey('debug', $body['error']);
        $this->assertArrayNotHasKey('exception', $body['error']);
        $this->assertStringNotContainsString('hunter2', json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function test_client_error_messages_are_shown_in_production(): void
    {
        $body = json_decode((string) (new ExceptionHandler(new TestLogger(), false))->render(new NotFoundHttpException('User not found.'))->getBody(), true);

        $this->assertSame('User not found.', $body['error']['message']);
    }

    public function test_debug_mode_exposes_message_class_and_trace(): void
    {
        $body = json_decode((string) (new ExceptionHandler(new TestLogger(), true))->render(new \LogicException('details'))->getBody(), true);

        $this->assertSame('details', $body['error']['message']);
        $this->assertSame(\LogicException::class, $body['error']['debug']['exception']);
        $this->assertMatchesRegularExpression('/ExceptionHandlerTest\.php:\d+$/', $body['error']['debug']['location']);
        $this->assertIsArray($body['error']['debug']['trace'], 'Server errors include a trace.');
    }

    public function test_server_errors_are_logged_with_the_request_id(): void
    {
        $logger = new TestLogger();
        $request = (new Psr17Factory())->createServerRequest('GET', '/')->withAttribute('request_id', 'req-123');

        (new ExceptionHandler($logger, false))->render(new \RuntimeException('boom'), $request);

        $errors = $logger->atLevel('error');
        $this->assertCount(1, $errors);
        $this->assertSame('boom', $errors[0]['message']);
        $this->assertSame('req-123', $errors[0]['context']['request_id']);
        $this->assertSame(\RuntimeException::class, $errors[0]['context']['exception']);
    }

    public function test_model_not_found_becomes_a_404_without_leaking_the_class_name(): void
    {
        $e = (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\App\Models\User::class, [5]);
        $response = (new ExceptionHandler(new TestLogger(), false))->render($e);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('NOT_FOUND', $body['error']['code']);
        $this->assertSame('User not found.', $body['error']['message']);
    }

    public function test_client_errors_are_not_logged(): void
    {
        $logger = new TestLogger();

        (new ExceptionHandler($logger, false))->render(new NotFoundHttpException());

        $this->assertSame([], $logger->records);
    }
}

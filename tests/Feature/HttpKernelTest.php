<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use VtPhp\Foundation\Application;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;

final class HttpKernelTest extends FeatureTestCase
{
    public function test_health_check(): void
    {
        $response = $this->request('GET', '/health');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => 'ok', 'version' => '0.1.0'], $this->body($response));
    }

    public function test_every_response_gets_a_request_id(): void
    {
        $generated = $this->request('GET', '/health')->getHeaderLine('X-Request-ID');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $generated);

        $this->assertNotSame($generated, $this->request('GET', '/health')->getHeaderLine('X-Request-ID'));
    }

    public function test_an_incoming_request_id_is_echoed_back(): void
    {
        $this->assertSame('trace-abc', $this->request('GET', '/health', null, ['X-Request-ID' => 'trace-abc'])->getHeaderLine('X-Request-ID'));
    }

    public function test_a_hardened_session_cookie_is_issued(): void
    {
        $header = $this->request('GET', '/health')->getHeaderLine('Set-Cookie');

        $this->assertMatchesRegularExpression('/^vtphp_session=[a-f0-9]{40}; /', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Path=/', $header);
        // Expiry is fixed when the cookie is queued and Max-Age computed when the
        // header is written, so a second boundary in between yields 7199.
        $this->assertMatchesRegularExpression('/Max-Age=(7199|7200)\b/', $header);
    }

    public function test_the_session_id_is_stable_across_requests(): void
    {
        $this->request('GET', '/health');
        $first = $this->cookies['vtphp_session'];
        $this->request('GET', '/health');

        $this->assertSame($first, $this->cookies['vtphp_session']);
    }

    public function test_unknown_routes_return_a_json_404(): void
    {
        $response = $this->request('GET', '/nope');

        $this->assertErrorCode($response, 404, 'NOT_FOUND');
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertArrayNotHasKey('trace', $this->body($response)['error'], 'No traces outside debug mode.');
    }

    public function test_wrong_methods_return_a_json_405_with_allowed_methods(): void
    {
        $response = $this->request('POST', '/api/v1/users/1');

        $this->assertErrorCode($response, 405, 'METHOD_NOT_ALLOWED');
        $this->assertSame(['GET', 'PUT', 'PATCH', 'DELETE'], $this->body($response)['error']['allowed_methods']);
    }

    public function test_unexpected_exceptions_become_a_logged_opaque_500(): void
    {
        $response = $this->request('GET', '/boom', null, ['X-Request-ID' => 'req-500'], function (Application $app): void {
            $app->make(Router::class)->get('/boom', fn () => throw new \RuntimeException('secret internals'));
        });

        $this->assertErrorCode($response, 500, 'INTERNAL_SERVER_ERROR');
        $this->assertSame('Internal server error.', $this->body($response)['error']['message']);
        $this->assertStringNotContainsString('secret internals', (string) $response->getBody());

        $errors = $this->logs->atLevel('error');
        $this->assertCount(1, $errors);
        $this->assertSame('secret internals', $errors[0]['message']);
    }

    public function test_debug_mode_adds_exception_details(): void
    {
        $this->setEnvironment(['APP_DEBUG' => 'true']);

        $error = $this->body($this->request('GET', '/nope'))['error'];

        $this->assertSame('VtPhp\\Exceptions\\NotFoundHttpException', $error['exception']);
        $this->assertNotEmpty($error['trace']);
    }

    public function test_a_malformed_json_body_is_treated_as_empty_input(): void
    {
        $response = $this->request('POST', '/api/v1/login', null, ['Content-Type' => 'application/json']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
    }

    public function test_error_responses_keep_the_request_id_and_session_cookie(): void
    {
        $notFound = $this->request('GET', '/nope', null, ['X-Request-ID' => 'trace-404']);
        $this->assertSame('trace-404', $notFound->getHeaderLine('X-Request-ID'));
        $this->assertMatchesRegularExpression('/^vtphp_session=[a-f0-9]{40}; /', $notFound->getHeaderLine('Set-Cookie'));

        $generated = $this->request('GET', '/nope')->getHeaderLine('X-Request-ID');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $generated);
    }

    public function test_a_logged_500_carries_the_same_request_id_as_the_response(): void
    {
        $response = $this->request('GET', '/boom', null, [], function (Application $app): void {
            $app->make(Router::class)->get('/boom', fn () => throw new \RuntimeException('boom'));
        });

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($response->getHeaderLine('X-Request-ID'), $this->logs->atLevel('error')[0]['context']['request_id']);
    }

    public function test_the_session_is_saved_even_when_the_request_fails(): void
    {
        $this->request('GET', '/remember', null, [], function (Application $app): void {
            $app->make(Router::class)->get('/remember', function (): never {
                session()->put('wizard_step', 2);

                throw new \VtPhp\Exceptions\ConflictHttpException('Try again.');
            });
        });

        $file = $this->tmp.'/sessions/'.$this->cookies['vtphp_session'].'.sess';
        $this->assertFileExists($file);
        $this->assertSame(2, unserialize((string) file_get_contents($file), ['allowed_classes' => false])['wizard_step'] ?? null);
    }
}

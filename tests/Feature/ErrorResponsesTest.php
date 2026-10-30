<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Exceptions\HttpException;
use VtPhp\Exceptions\NotFoundHttpException;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Foundation\Application;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;

final class ErrorResponsesTest extends FeatureTestCase
{
    private const BROWSER = ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];

    private function debug(): void
    {
        $this->setEnvironment(['APP_DEBUG' => 'true']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function throwing(\Closure $throw, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/throw', null, $headers, function (Application $app) use ($throw): void {
            $app->make(Router::class)->get('/throw', $throw);
        });
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function statuses(): iterable
    {
        yield 'bad request' => [400, 'BAD_REQUEST'];
        yield 'gone' => [410, 'GONE'];
        yield 'too large' => [413, 'PAYLOAD_TOO_LARGE'];
        yield 'unsupported' => [415, 'UNSUPPORTED_MEDIA_TYPE'];
        yield 'locked' => [423, 'LOCKED'];
        yield 'unavailable' => [503, 'SERVICE_UNAVAILABLE'];
        yield 'unknown client error' => [499, 'CLIENT_ERROR'];
    }

    #[DataProvider('statuses')]
    public function test_every_status_gets_a_code_and_a_readable_message(int $status, string $code): void
    {
        $response = $this->throwing(fn () => abort($status));
        $error = $this->body($response)['error'];

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($code, $error['code']);
        $this->assertNotSame('', $error['message']);
    }

    public function test_abort_uses_the_specific_exception_classes_and_keeps_messages_and_headers(): void
    {
        $this->assertInstanceOf(NotFoundHttpException::class, HttpException::forStatus(404));
        $this->assertInstanceOf(AuthorizationException::class, HttpException::forStatus(403, 'Not yours.'));

        $response = $this->throwing(fn () => abort(503, 'Down for maintenance.', ['Retry-After' => '600']));
        $this->assertSame('Down for maintenance.', $this->body($response)['error']['message'], 'A deliberate 5xx shows its message.');
        $this->assertSame('600', $response->getHeaderLine('Retry-After'));

        $this->assertSame(403, $this->throwing(fn () => abort_unless(false, 403))->getStatusCode());
        $this->assertSame(423, $this->throwing(fn () => abort_if(true, 423, 'Locked.'))->getStatusCode());
        $this->assertSame(200, $this->throwing(function (): array {
            abort_if(false, 423);

            return ['ok' => true];
        })->getStatusCode());
    }

    public function test_validation_messages_name_the_first_problem(): void
    {
        $this->assertSame('The email field is required.', (new ValidationException(['email' => ['The email field is required.']]))->getMessage());
        $this->assertSame('A (and 2 more errors)', (new ValidationException(['a' => ['A', 'B'], 'c' => ['C']]))->getMessage());

        $error = $this->body($this->request('POST', '/api/v1/login', []))['error'];
        $this->assertSame('The email field is required. (and 1 more error)', $error['message']);
    }

    public function test_405_sends_an_allow_header(): void
    {
        $response = $this->request('POST', '/api/v1/users/1');

        $this->assertSame('GET, PUT, PATCH, DELETE', $response->getHeaderLine('Allow'));
        $this->assertSame('This URL does not accept that HTTP method. Use GET or PUT or PATCH or DELETE.', $this->body($response)['error']['message']);
    }

    public function test_browsers_get_an_html_page_and_api_clients_get_json(): void
    {
        $response = $this->request('GET', '/nope', null, self::BROWSER);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        $this->assertStringContainsString('<p class="status">404</p>', $html);
        $this->assertStringContainsString('No endpoint matches GET /nope.', $html);
        $this->assertStringContainsString('Request ID:', $html);
        $this->assertStringNotContainsString('<script', $html);

        preg_match('/<style nonce="([^"]+)"/', $html, $nonce);
        $this->assertStringContainsString("style-src 'nonce-{$nonce[1]}'", $response->getHeaderLine('Content-Security-Policy'));

        foreach ([['Accept' => 'application/json'], ['Accept' => '*/*'], self::BROWSER + ['X-Requested-With' => 'XMLHttpRequest'], []] as $headers) {
            $this->assertSame('application/json', $this->request('GET', '/nope', null, $headers)->getHeaderLine('Content-Type'));
        }
    }

    public function test_the_html_page_escapes_and_lists_validation_errors(): void
    {
        $response = $this->throwing(fn () => throw new ValidationException(['name' => ['<b>Bad</b> name']]), self::BROWSER);
        $html = (string) $response->getBody();

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('<code>name</code> &lt;b&gt;Bad&lt;/b&gt; name', $html);
        $this->assertStringNotContainsString('<b>Bad</b>', $html);
    }

    public function test_server_errors_hide_details_unless_debugging(): void
    {
        $html = (string) $this->throwing(fn () => throw new \RuntimeException('secret'), self::BROWSER)->getBody();
        $this->assertStringContainsString('Something went wrong on our side', $html);
        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringNotContainsString('Debug details', $html);

        $this->debug();
        $response = $this->throwing(fn () => throw new \RuntimeException('secret'));
        $debug = $this->body($response)['error']['debug'];
        $this->assertSame('secret', $this->body($response)['error']['message']);
        $this->assertSame(\RuntimeException::class, $debug['exception']);
        $this->assertStringStartsWith('tests/Feature/ErrorResponsesTest.php:', $debug['location']);
        $this->assertStringStartsWith('#0 ', $debug['trace'][0]);
        $this->assertStringContainsString('Debug details', (string) $this->throwing(fn () => throw new \RuntimeException('x'), self::BROWSER)->getBody());
    }

    public function test_404_hints_in_debug_mode(): void
    {
        $this->debug();
        $hint = fn (string $method, string $path): ?string => $this->body($this->request($method, $path))['error']['hint'] ?? null;

        $this->assertSame('The API docs are switched off. Set SWAGGER_ENABLED=true in .env, then open /api-docs.', $hint('GET', '/api-doc'));
        $this->assertSame('The API docs are switched off. Set SWAGGER_ENABLED=true in .env, then open /api-docs.', $hint('GET', '/api-docs'));
        $this->assertSame('Did you mean GET /api/v1/users?', $hint('GET', '/api/v1/user'));
        $this->assertSame('Did you mean GET /api/v1/users/{user}?', $hint('GET', '/api/v1/userz/5'));
        $this->assertSame('Did you mean POST /api/v1/login?', $hint('POST', '/api/v1/logn'));
        $this->createUser();
        $this->login();
        $missing = $this->body($this->request('GET', '/api/v1/users/999999'))['error'];
        $this->assertSame('User not found.', $missing['message']);
        $this->assertArrayNotHasKey('hint', $missing, 'A missing record is not a typo.');

        $this->setEnvironment(['SWAGGER_ENABLED' => 'true']);
        $this->assertSame('Did you mean GET /api-docs?', $hint('GET', '/api-doc'));
    }

    public function test_no_hints_outside_debug_mode(): void
    {
        $this->assertArrayNotHasKey('hint', $this->body($this->request('GET', '/api-doc'))['error']);
    }
}

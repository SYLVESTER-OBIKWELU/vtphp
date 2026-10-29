<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use VtPhp\Foundation\Http\Kernel;
use VtPhp\Tests\Support\FeatureTestCase;

final class CsrfTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('ada@example.com', 'password123');
        $this->login('ada@example.com', 'password123');
    }

    /**
     * Sends exactly what a malicious page could make the browser send:
     * no custom headers, a "simple" content type, the victim's cookie.
     *
     * @param array<string, string> $headers
     */
    private function forged(string $method, string $path, ?string $contentType, array $headers = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, 'http://localhost'.$path, ['REMOTE_ADDR' => '203.0.113.10'])
            ->withCookieParams($this->cookies)
            ->withHeader('Origin', 'https://evil.example');

        if ($contentType !== null) {
            $request = $request->withHeader('Content-Type', $contentType)->withParsedBody([]);
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->freshApp()->make(Kernel::class)->handle($request);
    }

    public function test_cross_site_form_and_bodyless_posts_are_rejected(): void
    {
        foreach (['application/x-www-form-urlencoded', 'multipart/form-data; boundary=x', 'text/plain', null] as $type) {
            $response = $this->forged('POST', '/api/v1/logout', $type);

            $this->assertErrorCode($response, 403, 'CSRF_REJECTED');
            $this->assertNotSame('', $response->getHeaderLine('X-Request-ID'), 'Rejections still get the usual headers.');
        }

        $this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode(), 'The victim is still logged in.');
    }

    public function test_json_or_x_requested_with_is_accepted(): void
    {
        $this->assertSame(200, $this->forged('GET', '/api/v1/me', null)->getStatusCode(), 'Safe methods are never blocked.');
        $this->assertSame(200, $this->forged('POST', '/api/v1/logout', 'application/vnd.api+json')->getStatusCode(), '+json types count as JSON.');

        $this->login('ada@example.com', 'password123');
        $this->assertSame(200, $this->forged('POST', '/api/v1/logout', null, ['X-Requested-With' => 'XMLHttpRequest'])->getStatusCode());
    }

    public function test_requests_without_the_session_cookie_are_unaffected(): void
    {
        $this->cookies = [];

        // e.g. a server-to-server client or a first login from an HTML form.
        $this->assertNotSame(403, $this->forged('POST', '/api/v1/login', 'application/x-www-form-urlencoded')->getStatusCode());
    }

    public function test_excepted_paths_and_disabling(): void
    {
        $config = static fn (array $csrf) => function (\VtPhp\Foundation\Application $app) use ($csrf): void {
            $app->make('config')->set('http.csrf', $csrf);
        };

        $factory = new Psr17Factory();
        foreach ([['enabled' => true, 'except' => ['api/v1/log*']], ['enabled' => false, 'except' => []]] as $csrf) {
            $app = $this->freshApp();
            $config($csrf)($app);
            $request = $factory->createServerRequest('POST', 'http://localhost/api/v1/logout', ['REMOTE_ADDR' => '203.0.113.10'])
                ->withCookieParams($this->cookies)
                ->withHeader('Content-Type', 'text/plain');

            $this->assertSame(200, $app->make(Kernel::class)->handle($request)->getStatusCode(), json_encode($csrf, JSON_THROW_ON_ERROR));
        }
    }
}

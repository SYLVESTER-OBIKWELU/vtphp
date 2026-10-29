<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use VtPhp\Tests\Support\FeatureTestCase;

final class CorsTest extends FeatureTestCase
{
    private const FRONTEND = ['Origin' => 'http://localhost:3000'];

    public function test_cors_is_off_by_default(): void
    {
        $response = $this->request('GET', '/api/v1/me', null, self::FRONTEND);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_enabling_it_in_env_allows_the_listed_origins(): void
    {
        $this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => ' http://localhost:3000 , https://*.example.com ']);

        $this->assertSame('http://localhost:3000', $this->request('GET', '/api/v1/me', null, self::FRONTEND)->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('https://admin.example.com', $this->request('GET', '/api/v1/me', null, ['Origin' => 'https://admin.example.com'])->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertFalse($this->request('GET', '/api/v1/me', null, ['Origin' => 'https://evil.test'])->hasHeader('Access-Control-Allow-Origin'));
    }

    public function test_error_responses_carry_cors_headers_so_the_frontend_can_read_them(): void
    {
        $this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000']);

        $unauthenticated = $this->request('GET', '/api/v1/me', null, self::FRONTEND);
        $this->assertErrorCode($unauthenticated, 401, 'UNAUTHENTICATED');
        $this->assertSame('http://localhost:3000', $unauthenticated->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('X-Request-ID', $unauthenticated->getHeaderLine('Access-Control-Expose-Headers'));

        $notFound = $this->request('GET', '/api/v1/nope', null, self::FRONTEND);
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertSame('http://localhost:3000', $notFound->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_a_preflight_is_answered_without_routing_or_a_session(): void
    {
        $this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000']);

        // No OPTIONS route exists; without CORS handling this would be a 405.
        $response = $this->request('OPTIONS', '/api/v1/users/1', null, self::FRONTEND + ['Access-Control-Request-Method' => 'PATCH']);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('PATCH', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertFalse($response->hasHeader('Set-Cookie'), 'Preflights must not create sessions.');
        $this->assertNotSame('', $response->getHeaderLine('X-Request-ID'));
    }

    public function test_credentials_let_a_frontend_use_the_session_cookie(): void
    {
        $this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000', 'CORS_SUPPORTS_CREDENTIALS' => 'true']);
        $this->createUser('ada@example.com', 'password123');

        $login = $this->request('POST', '/api/v1/login', ['email' => 'ada@example.com', 'password' => 'password123'], self::FRONTEND);

        $this->assertSame(200, $login->getStatusCode());
        $this->assertSame('true', $login->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame(200, $this->request('GET', '/api/v1/me', null, self::FRONTEND)->getStatusCode());
    }

    public function test_paths_outside_the_policy_are_untouched(): void
    {
        $this->setEnvironment(['CORS_ENABLED' => 'true', 'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000']);

        $this->assertFalse($this->request('GET', '/health', null, self::FRONTEND)->hasHeader('Access-Control-Allow-Origin'));
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Psr\Http\Message\ResponseInterface;
use VtPhp\Tests\Support\FeatureTestCase;

final class RateLimitingTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Count in the shared database store, as in production.
        $this->setEnvironment(['RATE_LIMIT_STORE' => 'database', 'RATE_LIMIT_AUTH' => '5', 'RATE_LIMIT_API' => '60']);
        $this->createUser('ada@example.com', 'password123');
        $this->cookies = [];
    }

    private function attemptLogin(string $email, string $password = 'wrong-password', string $ip = '203.0.113.10'): ResponseInterface
    {
        return $this->request('POST', '/api/v1/login', ['email' => $email, 'password' => $password], server: ['REMOTE_ADDR' => $ip]);
    }

    public function test_login_is_throttled_after_five_attempts_per_ip_and_email(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $response = $this->attemptLogin('ada@example.com');
            $this->assertSame(401, $response->getStatusCode(), "attempt {$i}");
            $this->assertSame((string) (5 - $i), $response->getHeaderLine('X-RateLimit-Remaining'), 'Signing up did not use up login attempts.');
        }

        $blocked = $this->attemptLogin('ada@example.com', 'password123');

        $this->assertErrorCode($blocked, 429, 'TOO_MANY_REQUESTS');
        $this->assertSame('5', $blocked->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('0', $blocked->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertGreaterThan(0, (int) $blocked->getHeaderLine('Retry-After'));
        $this->assertSame((int) $blocked->getHeaderLine('Retry-After'), $this->body($blocked)['error']['retry_after']);
        $this->assertSame(401, $this->request('GET', '/api/v1/me')->getStatusCode(), 'The right password, sent while locked, did not log in.');
    }

    public function test_the_limit_is_per_email_and_per_ip(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->attemptLogin('ada@example.com');
        }

        $this->assertSame(429, $this->attemptLogin('ADA@example.com')->getStatusCode(), 'Email case does not bypass it.');
        $this->assertSame(401, $this->attemptLogin('grace@example.com')->getStatusCode(), 'Other accounts are unaffected.');
        $this->assertSame(200, $this->attemptLogin('ada@example.com', 'password123', '198.51.100.7')->getStatusCode(), 'An attacker elsewhere cannot lock the real user out.');
    }

    public function test_password_reset_emails_are_throttled(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->assertSame(200, $this->request('POST', '/api/v1/password/forgot', ['email' => 'ada@example.com'])->getStatusCode());
        }

        $this->assertSame(429, $this->request('POST', '/api/v1/password/forgot', ['email' => 'ada@example.com'])->getStatusCode());
        $this->assertCount(5, $this->logs->mails(), 'The sixth request sends no email.');
    }

    public function test_every_api_route_has_a_general_limit(): void
    {
        $this->setEnvironment(['RATE_LIMIT_API' => '3']);
        $fresh = ['REMOTE_ADDR' => '198.51.100.20'];

        for ($i = 0; $i < 3; ++$i) {
            $this->assertSame(401, $this->request('GET', '/api/v1/me', server: $fresh)->getStatusCode());
        }

        $this->assertErrorCode($this->request('GET', '/api/v1/me', server: $fresh), 429, 'TOO_MANY_REQUESTS');
        $this->assertSame(200, $this->request('GET', '/health', server: $fresh)->getStatusCode(), 'Routes outside /api are not limited.');
    }

    public function test_signed_in_users_are_limited_per_user_not_per_shared_ip(): void
    {
        $this->createUser('grace@example.com', 'password123');
        $this->setEnvironment(['RATE_LIMIT_API' => '3']);
        $office = ['REMOTE_ADDR' => '198.51.100.30']; // both users share one office IP

        $this->request('POST', '/api/v1/login', ['email' => 'ada@example.com', 'password' => 'password123'], server: $office);
        for ($i = 0; $i < 3; ++$i) {
            $this->assertSame(200, $this->request('GET', '/api/v1/me', server: $office)->getStatusCode());
        }
        $this->assertSame(429, $this->request('GET', '/api/v1/me', server: $office)->getStatusCode(), "Ada's own bucket is spent.");

        $this->cookies = [];
        $this->request('POST', '/api/v1/login', ['email' => 'grace@example.com', 'password' => 'password123'], server: $office);
        $this->assertSame(200, $this->request('GET', '/api/v1/me', server: $office)->getStatusCode(), 'Grace has her own bucket on the same IP.');
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\PasswordResetToken;
use PHPUnit\Framework\Attributes\Group;
use VtPhp\Tests\Support\FeatureTestCase;

final class PasswordResetTest extends FeatureTestCase
{
    private const GENERIC = 'If that email address is registered, a password reset link has been sent.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('ada@example.com', 'old-password');
        $this->cookies = [];
    }

    private function requestReset(string $email = 'ada@example.com'): string
    {
        $response = $this->request('POST', '/api/v1/password/forgot', ['email' => $email]);
        $this->assertSame(['message' => self::GENERIC], $this->body($response));

        $mails = $this->logs->mails();
        $this->assertNotEmpty($mails, 'No reset email was sent.');
        preg_match('/token=([a-f0-9]{64})/', end($mails)['body'], $match);

        return $match[1] ?? $this->fail('No token in the reset email.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reset(string $token, string $password = 'new-password', string $email = 'ada@example.com'): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('POST', '/api/v1/password/reset', [
            'email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    public function test_unknown_emails_get_the_same_response_and_no_mail(): void
    {
        $response = $this->request('POST', '/api/v1/password/forgot', ['email' => 'nobody@example.com']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => self::GENERIC], $this->body($response));
        $this->assertSame([], $this->logs->mails());
    }

    public function test_the_reset_email_is_sent_and_only_a_hash_is_stored(): void
    {
        $token = $this->requestReset();

        $mail = $this->logs->mails()[0];
        $this->assertSame(['ada@example.com'], $mail['to']);
        $this->assertSame('Reset your password', $mail['subject']);
        $this->assertStringContainsString('Hi Ada Lovelace,', $mail['body']);

        $record = PasswordResetToken::query()->find('ada@example.com');
        $this->assertNotSame($token, $record->token);
        $this->assertSame(hash('sha256', $token), $record->token);
    }

    public function test_a_valid_token_resets_the_password_once(): void
    {
        $token = $this->requestReset();

        $this->assertSame(['message' => 'Your password has been reset.'], $this->body($this->reset($token)));
        $this->assertSame(401, $this->login('ada@example.com', 'old-password')->getStatusCode());
        $this->assertSame(200, $this->login('ada@example.com', 'new-password')->getStatusCode());
        $this->assertNull(PasswordResetToken::query()->find('ada@example.com'));

        $reuse = $this->reset($token, 'third-password');
        $this->assertErrorCode($reuse, 422, 'VALIDATION_ERROR');
        $this->assertSame(['This password reset token is invalid.'], $this->body($reuse)['error']['errors']['token']);
    }

    public function test_a_wrong_token_or_wrong_email_is_rejected(): void
    {
        $token = $this->requestReset();

        $this->assertErrorCode($this->reset(str_repeat('a', 64)), 422, 'VALIDATION_ERROR');
        $this->assertErrorCode($this->reset($token, email: 'grace@example.com'), 422, 'VALIDATION_ERROR');
        $this->assertSame(200, $this->login('ada@example.com', 'old-password')->getStatusCode());
    }

    public function test_an_expired_token_is_rejected_and_discarded(): void
    {
        $token = $this->requestReset();
        PasswordResetToken::query()->where('email', 'ada@example.com')->update(['created_at' => now()->modify('-61 minutes')]);

        $response = $this->reset($token);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['This password reset token has expired.'], $this->body($response)['error']['errors']['token']);
        $this->assertNull(PasswordResetToken::query()->find('ada@example.com'));
    }

    public function test_requesting_again_invalidates_the_previous_token(): void
    {
        $first = $this->requestReset();
        $second = $this->requestReset();

        $this->assertNotSame($first, $second);
        $this->assertErrorCode($this->reset($first), 422, 'VALIDATION_ERROR');
        $this->assertSame(200, $this->reset($second)->getStatusCode());
    }

    public function test_reset_input_is_validated(): void
    {
        $response = $this->request('POST', '/api/v1/password/reset', ['email' => 'ada@example.com', 'password' => 'short', 'password_confirmation' => 'other']);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertSame(['token', 'password'], array_keys($this->body($response)['error']['errors']));
    }

    #[Group('defect')]
    public function test_the_emailed_reset_link_can_be_opened(): void
    {
        // The email links to /api/v1/password/reset?token=…, a POST-only route,
        // so clicking it in a mail client yields 405.
        $this->requestReset();
        preg_match('/href="([^"]+)"/', $this->logs->mails()[0]['body'], $match);
        $url = html_entity_decode($match[1]);

        $this->assertNotSame(405, $this->request('GET', (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->getStatusCode());
    }

    public function test_resetting_the_password_ends_existing_sessions(): void
    {
        $this->login('ada@example.com', 'old-password');
        $stolenSession = $this->cookies;
        $this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode());

        $this->cookies = [];
        $this->reset($this->requestReset());

        $this->cookies = $stolenSession;
        $this->assertSame(401, $this->request('GET', '/api/v1/me')->getStatusCode(), 'An attacker’s session survived the reset.');

        $this->cookies = [];
        $this->login('ada@example.com', 'new-password');
        $this->assertSame(200, $this->request('GET', '/api/v1/me')->getStatusCode(), 'New logins work normally.');
    }
}

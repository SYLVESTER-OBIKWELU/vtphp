<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use VtPhp\Routing\UrlSigner;
use VtPhp\Tests\Support\FeatureTestCase;

final class EmailVerificationTest extends FeatureTestCase
{
    /** @var array<string, mixed> */
    private array $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser('grace@example.com', name: 'Grace Hopper');
        $this->login('grace@example.com', 'password123');
    }

    private function sendLink(string $email = 'grace@example.com'): ?string
    {
        $before = count($this->logs->mails());
        $response = $this->request('POST', '/api/v1/email/verification-notification', ['email' => $email]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('If that email address is registered and unverified, a verification link has been sent.', $this->body($response)['message']);

        $mails = $this->logs->mails();
        if (count($mails) === $before) {
            return null;
        }

        preg_match('/href="([^"]+)"/', end($mails)['body'], $match);
        $url = html_entity_decode($match[1]);

        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    private function signedPath(string $path, ?\DateTimeInterface $expires = null): string
    {
        return $this->app->make(UrlSigner::class)->sign($path, $expires);
    }

    public function test_the_emailed_link_verifies_the_address(): void
    {
        $path = $this->sendLink();

        $this->assertNotNull($path);
        $this->assertSame('Verify your email address', $this->logs->mails()[0]['subject']);
        $this->assertStringStartsWith("/api/v1/email/verify/{$this->user['id']}/", $path);
        $this->assertMatchesRegularExpression('/[?&]expires=\d+&signature=[a-f0-9]{64}$/', $path);

        $verified = $this->request('GET', $path);
        $this->assertSame(200, $verified->getStatusCode());
        $this->assertTrue($this->body($verified)['data']['email_verified']);
        $this->assertTrue($this->body($this->request('GET', "/api/v1/users/{$this->user['id']}"))['data']['email_verified']);

        $this->assertSame(200, $this->request('GET', $path)->getStatusCode(), 'Verifying twice is harmless.');
    }

    public function test_no_mail_for_unknown_or_already_verified_addresses(): void
    {
        $this->assertNull($this->sendLink('nobody@example.com'));

        $this->request('GET', (string) $this->sendLink());
        $this->assertNull($this->sendLink(), 'An already-verified address must not get another link.');
    }

    public function test_a_wrong_hash_or_unknown_user_is_rejected(): void
    {
        $this->assertErrorCode($this->request('GET', $this->signedPath("/api/v1/email/verify/{$this->user['id']}/".str_repeat('0', 40))), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->request('GET', '/api/v1/email/verify/999/'.str_repeat('0', 40)), 404, 'NOT_FOUND');
        $this->assertFalse($this->body($this->request('GET', "/api/v1/users/{$this->user['id']}"))['data']['email_verified']);
    }

    public function test_a_verification_link_cannot_be_forged_from_the_email_alone(): void
    {
        $forged = "/api/v1/email/verify/{$this->user['id']}/".sha1('grace@example.com');

        $this->assertErrorCode($this->request('GET', $forged), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->request('GET', $forged.'?signature='.str_repeat('a', 64)), 403, 'FORBIDDEN');
        $this->assertFalse($this->body($this->request('GET', "/api/v1/users/{$this->user['id']}"))['data']['email_verified']);
    }

    public function test_a_tampered_or_expired_link_is_rejected(): void
    {
        $link = (string) $this->sendLink();

        $extended = (string) preg_replace('/expires=\d+/', 'expires='.(time() + 86400 * 365), $link);
        $this->assertErrorCode($this->request('GET', $extended), 403, 'FORBIDDEN');

        $other = $this->createUser('ada@example.com');
        $retargeted = str_replace("/verify/{$this->user['id']}/", "/verify/{$other['id']}/", $link);
        $this->assertErrorCode($this->request('GET', $retargeted), 403, 'FORBIDDEN');

        $expired = $this->signedPath("/api/v1/email/verify/{$this->user['id']}/".sha1('grace@example.com'), now()->modify('-1 minute'));
        $this->assertErrorCode($this->request('GET', $expired), 403, 'FORBIDDEN');

        $this->assertSame(200, $this->request('GET', $link)->getStatusCode(), 'The genuine link still works.');
    }

    #[Group('defect')]
    public function test_changing_the_email_requires_verifying_it_again(): void
    {
        $this->request('GET', (string) $this->sendLink());

        $updated = $this->body($this->request('PATCH', "/api/v1/users/{$this->user['id']}", ['email' => 'unproven@example.com']))['data'];

        $this->assertFalse($updated['email_verified'], 'The new, unproven address inherited verified status.');
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use VtPhp\Mail\Mailable;
use VtPhp\Mail\Mailer;
use VtPhp\Tests\Support\FeatureTestCase;

final class MailTest extends FeatureTestCase
{
    private function user(string $name = 'Ada Lovelace'): User
    {
        return User::query()->create(['name' => $name, 'email' => 'ada@example.com', 'password' => null]);
    }

    public function test_the_log_driver_records_a_rendered_email(): void
    {
        $this->app->make(Mailer::class)->send(new WelcomeEmail($this->user()));

        $mails = $this->logs->mails();
        $this->assertCount(1, $mails);
        $this->assertSame(['ada@example.com'], $mails[0]['to']);
        $this->assertSame('Welcome to VtPhp!', $mails[0]['subject']);
        $this->assertStringContainsString('<h1>Welcome, Ada Lovelace!</h1>', $mails[0]['body']);
    }

    public function test_user_content_is_escaped_in_emails(): void
    {
        $this->app->make(Mailer::class)->send(new WelcomeEmail($this->user('<script>x</script>')));

        $body = $this->logs->mails()[0]['body'];
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_a_mailable_collects_multiple_recipients(): void
    {
        $mailable = new class () extends Mailable {
            public function build(): void
            {
                $this->subject('Digest')->view('emails.welcome', ['user' => (object) ['name' => 'Team', 'email' => 'x']]);
            }
        };

        $this->app->make(Mailer::class)->send($mailable->to('a@example.com')->to('b@example.com'));

        $this->assertSame(['a@example.com', 'b@example.com'], $this->logs->mails()[0]['to']);
    }

    public function test_the_smtp_driver_builds_a_real_transport(): void
    {
        // Port 1 on localhost refuses connections immediately, proving the SMTP
        // DSN is built and symfony/mailer actually attempts delivery.
        $config = $this->app->make('config');
        $config->set('mail.default', 'smtp');
        $config->set('mail.mailers.smtp.host', '127.0.0.1');
        $config->set('mail.mailers.smtp.port', 1);

        $this->expectException(TransportExceptionInterface::class);

        $this->app->make(Mailer::class)->send(new WelcomeEmail($this->user()));
    }

    public function test_try_send_logs_failures_instead_of_throwing(): void
    {
        $config = $this->app->make('config');
        $config->set('mail.default', 'smtp');
        $config->set('mail.mailers.smtp.port', 1);

        $this->assertFalse($this->app->make(Mailer::class)->trySend(new WelcomeEmail($this->user())));

        $errors = $this->logs->atLevel('error');
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('Mail could not be sent:', $errors[0]['message']);
        $this->assertSame(WelcomeEmail::class, $errors[0]['context']['mailable']);
    }

    public function test_a_mail_outage_does_not_reveal_registered_emails(): void
    {
        $this->setEnvironment(['MAIL_MAILER' => 'smtp', 'MAIL_PORT' => '1']);
        $this->user(); // ada@example.com

        foreach (['/api/v1/password/forgot', '/api/v1/email/verification-notification'] as $endpoint) {
            $registered = $this->request('POST', $endpoint, ['email' => 'ada@example.com']);
            $unregistered = $this->request('POST', $endpoint, ['email' => 'nobody@example.com']);

            $this->assertSame(200, $registered->getStatusCode(), $endpoint);
            $this->assertSame($this->body($unregistered), $this->body($registered), "{$endpoint}: identical replies");
        }

        $this->assertCount(2, $this->logs->atLevel('error'), 'Both failures were logged for operators.');
    }

    public function test_the_view_helper_renders_templates(): void
    {
        $html = view('emails.welcome', ['user' => (object) ['name' => 'Grace', 'email' => 'grace@example.com']]);

        $this->assertStringContainsString('Welcome, Grace!', $html);
    }
}

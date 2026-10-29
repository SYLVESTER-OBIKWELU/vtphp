<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use VtPhp\Config\Repository;
use VtPhp\Mail\Mailer;
use VtPhp\Tests\Support\TestLogger;

final class MailerTransportTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function settings(): iterable
    {
        $base = ['host' => 'mail.example.com', 'port' => 587, 'username' => null, 'password' => null];

        yield 'no auth, opportunistic TLS' => [$base + ['encryption' => null], 'smtp://mail.example.com:587'];
        yield 'credentials are url-encoded' => [['username' => 'me@example.com', 'password' => 'p@ss:word/1'] + $base + ['encryption' => ''], 'smtp://me%40example.com:p%40ss%3Aword%2F1@mail.example.com:587'];
        yield 'ssl = implicit TLS' => [['port' => 465, 'encryption' => 'ssl'] + $base, 'smtps://mail.example.com:465'];
        yield 'smtps alias' => [['port' => 465, 'encryption' => 'SMTPS'] + $base, 'smtps://mail.example.com:465'];
        yield 'tls = STARTTLS required' => [$base + ['encryption' => 'tls'], 'smtp://mail.example.com:587?require_tls=true'];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('settings')]
    public function test_the_dsn_honours_encryption_and_credentials(array $settings, string $expected): void
    {
        $this->assertSame($expected, Mailer::dsn($settings));
    }

    public function test_the_transport_applies_tls_options_and_the_timeout(): void
    {
        $mailer = new Mailer(new Repository([]), new TestLogger());

        $required = $mailer->transport(['host' => 'mail.example.com', 'port' => 587, 'encryption' => 'tls', 'timeout' => 4]);
        $this->assertInstanceOf(EsmtpTransport::class, $required);
        $this->assertTrue($required->isTlsRequired());
        $stream = $required->getStream();
        $this->assertInstanceOf(SocketStream::class, $stream);
        $this->assertSame(4.0, $stream->getTimeout());

        $implicit = $mailer->transport(['host' => 'mail.example.com', 'port' => 465, 'encryption' => 'ssl']);
        $this->assertInstanceOf(EsmtpTransport::class, $implicit);
        $implicitStream = $implicit->getStream();
        $this->assertInstanceOf(SocketStream::class, $implicitStream);
        $this->assertTrue($implicitStream->isTLS());
        $this->assertSame(10.0, $implicitStream->getTimeout(), 'Defaults to 10 s, not PHP\'s 60 s.');
    }
}

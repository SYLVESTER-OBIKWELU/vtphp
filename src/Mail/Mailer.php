<?php

declare(strict_types=1);

namespace VtPhp\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use VtPhp\Config\Repository;

/**
 * Thin wrapper over symfony/mailer. The "log" driver writes the rendered
 * email to the application log instead of sending it (useful for local dev).
 */
final class Mailer
{
    public function __construct(
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(Mailable $mailable): void
    {
        $mailable->build();

        $mailerName = (string) $this->config->get('mail.default');
        $settings = (array) $this->config->get("mail.mailers.{$mailerName}", []);

        if (($settings['transport'] ?? 'log') === 'log') {
            $this->logger->info('Mail sent (log driver)', [
                'to' => $mailable->recipients(),
                'subject' => $mailable->getSubject(),
                'body' => $mailable->render(),
            ]);

            return;
        }

        $email = (new Email())
            ->from($this->fromAddress())
            ->to(...$mailable->recipients())
            ->subject($mailable->getSubject())
            ->html($mailable->render());

        (new SymfonyMailer($this->transport($settings)))->send($email);
    }

    /**
     * @param array<string, mixed> $settings A `mail.mailers.*` entry.
     */
    public function transport(array $settings): TransportInterface
    {
        $transport = Transport::fromDsn(self::dsn($settings));

        // Without this a hung SMTP server holds the request for PHP's
        // default_socket_timeout (60 s).
        if ($transport instanceof SmtpTransport && ($stream = $transport->getStream()) instanceof SocketStream) {
            $stream->setTimeout((float) ($settings['timeout'] ?? 10));
        }

        return $transport;
    }

    /**
     * Send, but log and swallow delivery failures instead of throwing, for
     * mail the request shouldn't fail over (e.g. a password reset link: an
     * error there would also reveal that the address is registered).
     */
    public function trySend(Mailable $mailable): bool
    {
        try {
            $this->send($mailable);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Mail could not be sent: '.$e->getMessage(), [
                'exception' => $e::class,
                'mailable' => $mailable::class,
                'subject' => $mailable->getSubject(),
            ]);

            return false;
        }
    }

    private function fromAddress(): string
    {
        $address = (string) $this->config->get('mail.from.address');
        $name = (string) $this->config->get('mail.from.name');

        return $name !== '' ? "{$name} <{$address}>" : $address;
    }

    /**
     * MAIL_ENCRYPTION: "ssl"/"smtps" = implicit TLS (usually port 465);
     * "tls"/"starttls" = STARTTLS required; empty = STARTTLS whenever the
     * server offers it.
     *
     * @param array<string, mixed> $settings
     */
    public static function dsn(array $settings): string
    {
        $user = (string) ($settings['username'] ?? '');
        $pass = (string) ($settings['password'] ?? '');
        $host = (string) ($settings['host'] ?? '127.0.0.1');
        $port = (int) ($settings['port'] ?? 25);
        $encryption = strtolower((string) ($settings['encryption'] ?? ''));

        $scheme = in_array($encryption, ['ssl', 'smtps'], true) ? 'smtps' : 'smtp';
        $auth = $user !== '' ? rawurlencode($user).':'.rawurlencode($pass).'@' : '';
        $query = in_array($encryption, ['tls', 'starttls'], true) ? '?require_tls=true' : '';

        return "{$scheme}://{$auth}{$host}:{$port}{$query}";
    }
}

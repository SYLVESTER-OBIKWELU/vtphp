<?php

declare(strict_types=1);

namespace VtPhp\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
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

        $transport = Transport::fromDsn($this->dsn($settings));
        (new SymfonyMailer($transport))->send($email);
    }

    private function fromAddress(): string
    {
        $address = (string) $this->config->get('mail.from.address');
        $name = (string) $this->config->get('mail.from.name');

        return $name !== '' ? "{$name} <{$address}>" : $address;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function dsn(array $settings): string
    {
        $user = rawurlencode((string) ($settings['username'] ?? ''));
        $pass = rawurlencode((string) ($settings['password'] ?? ''));
        $host = (string) ($settings['host'] ?? '127.0.0.1');
        $port = (string) ($settings['port'] ?? 25);

        return "smtp://{$user}:{$pass}@{$host}:{$port}";
    }
}

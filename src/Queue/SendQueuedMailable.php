<?php

declare(strict_types=1);

namespace VtPhp\Queue;

use Psr\Log\LoggerInterface;
use VtPhp\Mail\Mailable;
use VtPhp\Mail\Mailer;

/**
 * The job behind Mailer::queue(): sends one email in the background, with a
 * few retries in case the mail server is briefly unavailable.
 */
final class SendQueuedMailable extends Job
{
    public ?int $tries = 3;

    /** @var list<int> */
    public int|array $backoff = [10, 60];

    public function __construct(public readonly Mailable $mailable)
    {
    }

    public function handle(Mailer $mailer): void
    {
        $mailer->send($this->mailable);
    }

    public function failed(\Throwable $exception): void
    {
        app(LoggerInterface::class)->error('Queued mail could not be sent: '.$exception->getMessage(), [
            'mailable' => $this->mailable::class,
            'subject' => $this->mailable->getSubject(),
        ]);
    }

    public function displayName(): string
    {
        return $this->mailable::class.' (mail)';
    }
}

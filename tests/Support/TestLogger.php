<?php

declare(strict_types=1);

namespace VtPhp\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * In-memory PSR-3 logger. Bound over LoggerInterface in feature tests so
 * mail sent via the `log` driver (reset tokens, verification links) and
 * logged exceptions can be asserted on.
 */
final class TestLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{to: array<int, string>, subject: string, body: string}>
     */
    public function mails(): array
    {
        $mails = [];

        foreach ($this->records as $record) {
            if ($record['message'] === 'Mail sent (log driver)') {
                $mails[] = $record['context'];
            }
        }

        return $mails;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function atLevel(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $r): bool => $r['level'] === $level));
    }
}

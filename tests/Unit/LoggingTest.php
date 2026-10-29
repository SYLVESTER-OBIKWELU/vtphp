<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use VtPhp\Config\Repository;
use VtPhp\Logging\LogManager;
use VtPhp\Tests\Support\TempDirectory;

final class LoggingTest extends TestCase
{
    private string $tmp;

    /** @var list<LoggerInterface> */
    private array $opened = [];

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-log');
    }

    protected function tearDown(): void
    {
        foreach ($this->opened as $logger) {
            if ($logger instanceof Logger) {
                $logger->close();
            }
        }

        TempDirectory::delete($this->tmp);
    }

    private function manager(): LogManager
    {
        return new LogManager(new Repository(['logging' => [
            'default' => 'single',
            'channels' => [
                'single' => ['driver' => 'single', 'path' => $this->tmp.'/app.log', 'level' => 'debug'],
                'quiet' => ['driver' => 'single', 'path' => $this->tmp.'/quiet.log', 'level' => 'warning'],
                'stack' => ['driver' => 'stack', 'channels' => ['single', 'quiet']],
                'stderr' => ['driver' => 'stderr', 'level' => 'debug'],
            ],
        ]]));
    }

    private function channel(LogManager $manager, ?string $name = null): LoggerInterface
    {
        return $this->opened[] = $manager->channel($name);
    }

    private function read(string $file): string
    {
        return (string) @file_get_contents($this->tmp.'/'.$file);
    }

    public function test_the_default_channel_writes_to_its_file(): void
    {
        $this->channel($this->manager())->info('User signed up', ['id' => 5]);

        $line = $this->read('app.log');
        $this->assertStringContainsString('single.INFO: User signed up', $line);
        $this->assertStringContainsString('{"id":5}', $line);
    }

    public function test_messages_below_the_channel_level_are_dropped(): void
    {
        $quiet = $this->channel($this->manager(), 'quiet');
        $quiet->info('noise');
        $quiet->warning('disk almost full');

        $this->assertStringNotContainsString('noise', $this->read('quiet.log'));
        $this->assertStringContainsString('disk almost full', $this->read('quiet.log'));
    }

    public function test_a_stack_fans_out_to_each_channel_respecting_levels(): void
    {
        $stack = $this->channel($this->manager(), 'stack');
        $stack->debug('debug detail');
        $stack->error('payment failed');

        $this->assertStringContainsString('debug detail', $this->read('app.log'));
        $this->assertStringContainsString('payment failed', $this->read('app.log'));
        $this->assertStringNotContainsString('debug detail', $this->read('quiet.log'));
        $this->assertStringContainsString('payment failed', $this->read('quiet.log'));
    }

    public function test_channels_are_memoized(): void
    {
        $manager = $this->manager();

        $this->assertSame($this->channel($manager, 'single'), $this->channel($manager));
        $this->assertInstanceOf(LoggerInterface::class, $this->channel($manager, 'stderr'));
    }

    public function test_an_unknown_channel_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager()->channel('slack');
    }
}

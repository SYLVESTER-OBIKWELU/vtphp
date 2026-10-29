<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Config\Repository;
use VtPhp\Routing\UrlSigner;

final class UrlSignerTest extends TestCase
{
    private function signer(string $key = 'base64:a2V5a2V5a2V5a2V5a2V5a2V5a2V5a2V5a2V5a2U='): UrlSigner
    {
        return new UrlSigner(new Repository(['app' => ['key' => $key]]));
    }

    private function requestFor(string $url): ServerRequestInterface
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (new Psr17Factory())->createServerRequest('GET', $url)->withQueryParams($query);
    }

    public function test_a_signed_url_validates_and_keeps_its_query(): void
    {
        $url = $this->signer()->sign('https://api.example.com/files/9?download=1');

        $this->assertStringStartsWith('https://api.example.com/files/9?download=1&signature=', $url);
        $this->assertTrue($this->signer()->hasValidSignature($this->requestFor($url)));
    }

    public function test_the_host_is_not_part_of_the_signature(): void
    {
        $url = $this->signer()->sign('https://public.example.com/files/9');

        $this->assertTrue($this->signer()->hasValidSignature($this->requestFor(str_replace('public.example.com', '10.0.0.5:8080', $url))));
    }

    public function test_tampering_with_path_or_query_invalidates_it(): void
    {
        $url = $this->signer()->sign('/files/9?download=1');

        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor(str_replace('/files/9', '/files/10', $url))));
        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor(str_replace('download=1', 'download=0', $url))));
        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor($url.'&extra=1')));
        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor('/files/9?download=1')), 'Unsigned URLs are rejected.');
    }

    public function test_a_different_key_rejects_the_signature(): void
    {
        $url = $this->signer()->sign('/files/9');

        $this->assertFalse($this->signer('base64:'.base64_encode(str_repeat('z', 32)))->hasValidSignature($this->requestFor($url)));
    }

    public function test_expiry_is_signed_and_enforced(): void
    {
        $valid = $this->signer()->sign('/files/9', new \DateTimeImmutable('+5 minutes'));
        $expired = $this->signer()->sign('/files/9', new \DateTimeImmutable('-1 second'));

        $this->assertMatchesRegularExpression('/expires=\d+&signature=/', $valid);
        $this->assertTrue($this->signer()->hasValidSignature($this->requestFor($valid)));
        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor($expired)));
        $this->assertFalse($this->signer()->hasValidSignature($this->requestFor((string) preg_replace('/expires=\d+/', 'expires='.(time() + 9999), $valid))));
    }

    public function test_signing_requires_an_application_key(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key:generate');

        $this->signer('')->sign('/files/9');
    }
}

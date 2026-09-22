<?php

declare(strict_types=1);

namespace VtPhp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * A fluent, immutable-underneath JSON response. Wraps a PSR-7 response so it
 * can be returned anywhere a ResponseInterface is expected.
 */
final class JsonResponse implements ResponseInterface
{
    private ResponseInterface $response;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $factory = new Psr17Factory();
        $hasBody = $data !== null;
        $body = $hasBody ? json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $response = $factory->createResponse($status)->withBody($factory->createStream($body));

        if ($hasBody) {
            $response = $response->withHeader('Content-Type', 'application/json');
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $this->response = $response;
    }

    private function with(ResponseInterface $response): self
    {
        $clone = clone $this;
        $clone->response = $response;

        return $clone;
    }

    public function status(int $code): self
    {
        return $this->with($this->response->withStatus($code));
    }

    public function header(string $name, string $value): self
    {
        return $this->with($this->response->withHeader($name, $value));
    }

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        return $this->with($this->response->withProtocolVersion($version));
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        return $this->with($this->response->withHeader($name, $value));
    }

    public function withAddedHeader(string $name, $value): static
    {
        return $this->with($this->response->withAddedHeader($name, $value));
    }

    public function withoutHeader(string $name): static
    {
        return $this->with($this->response->withoutHeader($name));
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        return $this->with($this->response->withBody($body));
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        return $this->with($this->response->withStatus($code, $reasonPhrase));
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }
}

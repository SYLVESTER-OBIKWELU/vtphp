<?php

declare(strict_types=1);

namespace VtPhp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class ResponseFactory
{
    /**
     * @param array<string, string> $headers
     */
    public function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    public function noContent(int $status = 204): JsonResponse
    {
        return new JsonResponse(null, $status);
    }

    /**
     * @param array<string, string> $headers
     */
    public function make(string $content = '', int $status = 200, array $headers = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($status)->withBody($factory->createStream($content));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->make('', $status, ['Location' => $url]);
    }
}

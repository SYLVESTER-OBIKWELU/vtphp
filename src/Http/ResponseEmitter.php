<?php

declare(strict_types=1);

namespace VtPhp\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            header(sprintf(
                'HTTP/%s %s %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
            ));

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false);
                }
            }
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Http\ResponseEmitter;
use VtPhp\Http\ResponseFactory;

final class HttpMessagesTest extends TestCase
{
    public function test_json_response_encodes_data_with_a_json_content_type(): void
    {
        $response = new JsonResponse(['name' => 'Ada'], 201, ['X-Custom' => 'yes']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('yes', $response->getHeaderLine('X-Custom'));
        $this->assertSame('{"name":"Ada"}', (string) $response->getBody());
    }

    public function test_json_response_keeps_slashes_and_unicode_readable(): void
    {
        $body = (string) (new JsonResponse(['url' => 'https://example.com/a', 'name' => 'Zoë']))->getBody();

        $this->assertSame('{"url":"https://example.com/a","name":"Zoë"}', $body);
    }

    public function test_a_null_payload_has_an_empty_body_and_no_content_type(): void
    {
        $response = new JsonResponse(null, 204);

        $this->assertSame('', (string) $response->getBody());
        $this->assertFalse($response->hasHeader('Content-Type'));
    }

    public function test_fluent_modifiers_return_new_instances(): void
    {
        $original = new JsonResponse(['ok' => true]);
        $modified = $original->status(202)->header('X-Trace', 'abc');

        $this->assertNotSame($original, $modified);
        $this->assertSame(200, $original->getStatusCode());
        $this->assertFalse($original->hasHeader('X-Trace'));
        $this->assertSame(202, $modified->getStatusCode());
        $this->assertSame('abc', $modified->getHeaderLine('X-Trace'));
        $this->assertInstanceOf(JsonResponse::class, $original->withStatus(500));
        $this->assertInstanceOf(JsonResponse::class, $original->withAddedHeader('Set-Cookie', 'a=b'));
    }

    public function test_invalid_utf8_raises_instead_of_emitting_a_broken_body(): void
    {
        $this->expectException(\JsonException::class);

        new JsonResponse(['bad' => "\xB1\x31"]);
    }

    private function request(string $uri = '/', string $method = 'GET'): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest($method, 'http://localhost'.$uri);
    }

    public function test_request_reads_query_json_body_headers_and_cookies(): void
    {
        $factory = new Psr17Factory();
        $psr = $this->request('/search?page=2&q=old', 'POST')
            ->withQueryParams(['page' => '2', 'q' => 'old'])
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Authorization', 'Bearer abc.def')
            ->withCookieParams(['theme' => 'dark'])
            ->withAttribute('id', 9)
            ->withBody($factory->createStream('{"q":"new","tags":["a"]}'));

        $request = Request::fromPsr($psr);

        $this->assertSame('POST', $request->method());
        $this->assertSame('/search', $request->path());
        $this->assertSame('2', $request->query('page'));
        $this->assertSame('new', $request->input('q'), 'Body values override query values.');
        $this->assertSame(['a'], $request->input('tags'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
        $this->assertTrue($request->has('page'));
        $this->assertFalse($request->has('nope'));
        $this->assertSame(['q' => 'new', 'tags' => ['a']], $request->json());
        $this->assertSame('abc.def', $request->bearerToken());
        $this->assertSame('dark', $request->cookie('theme'));
        $this->assertNull($request->cookie('missing'));
        $this->assertSame('none', $request->header('X-Missing', 'none'));
        $this->assertSame(9, $request->route('id'));
        $this->assertSame($psr, $request->psr());
    }

    public function test_request_prefers_an_already_parsed_form_body(): void
    {
        $request = Request::fromPsr($this->request('/', 'POST')->withParsedBody(['name' => 'Ada']));

        $this->assertSame('Ada', $request->input('name'));
    }

    public function test_request_treats_malformed_json_as_an_empty_body(): void
    {
        $psr = $this->request('/', 'POST')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new Psr17Factory())->createStream('{not json'));

        $this->assertSame([], Request::fromPsr($psr)->json());
    }

    public function test_bearer_token_is_null_without_a_bearer_header(): void
    {
        $this->assertNull(Request::fromPsr($this->request())->bearerToken());
        $this->assertNull(Request::fromPsr($this->request()->withHeader('Authorization', 'Basic xyz'))->bearerToken());
    }

    public function test_response_factory_helpers(): void
    {
        $factory = new ResponseFactory();

        $this->assertSame('{"a":1}', (string) $factory->json(['a' => 1])->getBody());
        $this->assertSame(204, $factory->noContent()->getStatusCode());

        $made = $factory->make('plain', 202, ['Content-Type' => 'text/plain']);
        $this->assertSame(202, $made->getStatusCode());
        $this->assertSame('plain', (string) $made->getBody());
        $this->assertSame('text/plain', $made->getHeaderLine('Content-Type'));

        $redirect = $factory->redirect('/login');
        $this->assertSame(302, $redirect->getStatusCode());
        $this->assertSame('/login', $redirect->getHeaderLine('Location'));
    }

    public function test_emitter_streams_the_whole_body(): void
    {
        $payload = str_repeat('abcdefghij', 2000); // Larger than the 8 KiB read chunk.
        $response = (new ResponseFactory())->make($payload);
        $response->getBody()->read(10); // Leave the stream mid-way; emit() must rewind.

        ob_start();
        (new ResponseEmitter())->emit($response);
        $output = (string) ob_get_clean();

        $this->assertSame($payload, $output);
    }
}

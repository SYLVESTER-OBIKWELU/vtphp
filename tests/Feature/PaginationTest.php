<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Http\Request;
use VtPhp\Tests\Support\FeatureTestCase;

final class PaginationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (range(1, 25) as $i) {
            $this->createUser("user{$i}@example.com", 'password123', "User {$i}");
        }

        $this->login('user1@example.com');
    }

    private function psrRequest(string $uri): ServerRequestInterface
    {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return (new ServerRequest('GET', $uri))->withQueryParams($query);
    }

    /**
     * Boots an app (which points the paginator at it) handling this request.
     */
    private function onRequest(string $uri): void
    {
        $this->freshApp()->instance(ServerRequestInterface::class, $this->psrRequest($uri));
    }

    public function test_the_users_list_is_paginated(): void
    {
        $body = $this->body($this->request('GET', '/api/v1/users?page=2&per_page=10'));

        $this->assertSame(['data', 'meta', 'links'], array_keys($body));
        $this->assertSame('user11@example.com', $body['data'][0]['email']);
        $this->assertCount(10, $body['data']);
        $this->assertSame(['current_page' => 2, 'per_page' => 10, 'total' => 25, 'last_page' => 3, 'from' => 11, 'to' => 20], $body['meta']);
        $this->assertSame([
            'first' => '/api/v1/users?page=1',
            'last' => '/api/v1/users?page=3',
            'prev' => '/api/v1/users?page=1',
            'next' => '/api/v1/users?page=3',
        ], $body['links']);
    }

    public function test_defaults_and_bad_values(): void
    {
        $first = $this->body($this->request('GET', '/api/v1/users'));
        $this->assertSame(1, $first['meta']['current_page']);
        $this->assertSame(15, $first['meta']['per_page']);
        $this->assertNull($first['links']['prev']);

        foreach (['page=0', 'page=-3', 'page=abc', 'page[]=2'] as $query) {
            $this->assertSame(1, $this->body($this->request('GET', "/api/v1/users?{$query}"))['meta']['current_page'], $query);
        }

        $this->assertSame(100, $this->body($this->request('GET', '/api/v1/users?per_page=100000'))['meta']['per_page'], 'per_page is capped.');
        $this->assertSame(1, $this->body($this->request('GET', '/api/v1/users?per_page=0'))['meta']['per_page']);

        $past = $this->body($this->request('GET', '/api/v1/users?page=9'));
        $this->assertSame([], $past['data'], 'A page past the end is empty, not an error.');
        $this->assertNull($past['links']['next']);
    }

    public function test_links_use_the_path_not_the_host_header(): void
    {
        $body = $this->body($this->request('GET', '/api/v1/users?per_page=10', null, ['Host' => 'evil.example']));

        $this->assertSame('/api/v1/users?page=2', $body['links']['next']);
    }

    public function test_simple_and_cursor_pagination_in_resources(): void
    {
        $this->onRequest('/people?page=2&sort=name');

        $simple = UserResource::collection(User::query()->orderBy('id')->simplePaginate(10))->toResponse();
        $body = json_decode((string) $simple->getBody(), true);
        $this->assertSame(['current_page' => 2, 'per_page' => 10, 'from' => 11, 'to' => 20], $body['meta']);
        $this->assertSame(['first', 'prev', 'next'], array_keys($body['links']));

        $kept = User::query()->orderBy('id')->paginate(10)->withQueryString();
        $this->assertSame('/people?sort=name&page=3', $kept->nextPageUrl(), 'withQueryString() keeps the other filters.');

        $cursor = json_decode((string) UserResource::collection(User::query()->orderBy('id')->cursorPaginate(10))->toResponse()->getBody(), true);
        $this->assertCount(10, $cursor['data']);
        $this->assertNull($cursor['meta']['prev_cursor']);
        $this->assertIsString($cursor['meta']['next_cursor']);

        $this->onRequest('/people?cursor='.$cursor['meta']['next_cursor']);
        $next = json_decode((string) UserResource::collection(User::query()->orderBy('id')->cursorPaginate(10))->toResponse()->getBody(), true);
        $this->assertSame('user11@example.com', $next['data'][0]['email']);
    }

    public function test_request_per_page(): void
    {
        $perPage = fn (string $query): int => Request::fromPsr($this->psrRequest("/x?{$query}"))->perPage(20, 50);

        $this->assertSame(20, $perPage(''));
        $this->assertSame(30, $perPage('per_page=30'));
        $this->assertSame(50, $perPage('per_page=999'));
        $this->assertSame(20, $perPage('per_page=lots'));
    }
}

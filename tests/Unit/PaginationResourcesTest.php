<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Pagination\LengthAwarePaginator;
use VtPhp\Resources\JsonResource;
use VtPhp\Resources\ResourceCollection;

final class ArticleResource extends JsonResource
{
    public function toArray(): array
    {
        return ['title' => strtoupper($this->resource['title'])];
    }
}

final class PaginationResourcesTest extends TestCase
{
    public function test_paginator_meta_and_last_page(): void
    {
        $paginator = new LengthAwarePaginator(['a', 'b'], 45, 10, 2);

        $this->assertSame(['a', 'b'], $paginator->items());
        $this->assertSame(45, $paginator->total());
        $this->assertSame(5, $paginator->lastPage());
        $this->assertSame(['current_page' => 2, 'per_page' => 10, 'total' => 45, 'last_page' => 5], $paginator->meta());
    }

    public function test_last_page_is_never_below_one(): void
    {
        $this->assertSame(1, (new LengthAwarePaginator([], 0, 10, 1))->lastPage());
        // A zero page size is clamped to 1 rather than dividing by zero.
        $this->assertSame(5, (new LengthAwarePaginator([], 5, 0, 1))->lastPage());
    }

    public function test_links_on_a_middle_first_and_last_page(): void
    {
        $middle = (new LengthAwarePaginator([], 45, 10, 3, '/api/v1/articles'))->links();
        $this->assertSame([
            'first' => '/api/v1/articles?page=1',
            'last' => '/api/v1/articles?page=5',
            'prev' => '/api/v1/articles?page=2',
            'next' => '/api/v1/articles?page=4',
        ], $middle);

        $this->assertNull((new LengthAwarePaginator([], 45, 10, 1, '/a'))->links()['prev']);
        $this->assertNull((new LengthAwarePaginator([], 45, 10, 5, '/a'))->links()['next']);
    }

    public function test_a_resource_wraps_data_with_status_and_headers(): void
    {
        $response = ArticleResource::make(['title' => 'hello'])->status(201)->header('Location', '/articles/1')->toResponse();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/articles/1', $response->getHeaderLine('Location'));
        $this->assertSame('{"data":{"title":"HELLO"}}', (string) $response->getBody());
    }

    public function test_a_collection_transforms_every_item(): void
    {
        $collection = ArticleResource::collection([['title' => 'a'], ['title' => 'b']]);

        $this->assertInstanceOf(ResourceCollection::class, $collection);
        $this->assertSame([['title' => 'A'], ['title' => 'B']], $collection->toArray());
        $this->assertSame(206, $collection->status(206)->toResponse()->getStatusCode());
    }

    public function test_a_collection_accepts_any_iterable(): void
    {
        $generator = (static function (): \Generator {
            yield ['title' => 'x'];
        })();

        $this->assertSame([['title' => 'X']], ArticleResource::collection($generator)->toArray());
    }

    public function test_a_paginated_collection_includes_meta_and_links(): void
    {
        $paginator = new LengthAwarePaginator([['title' => 'a']], 21, 10, 1, '/articles');
        $body = json_decode((string) ArticleResource::collection($paginator)->toResponse()->getBody(), true);

        $this->assertSame([['title' => 'A']], $body['data']);
        $this->assertSame(3, $body['meta']['last_page']);
        $this->assertSame('/articles?page=2', $body['links']['next']);
    }
}

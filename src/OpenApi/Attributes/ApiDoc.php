<?php

declare(strict_types=1);

namespace VtPhp\OpenApi\Attributes;

/**
 * Optional extra details for the API docs. Everything is read automatically
 * (summary from the doc comment, tags from route groups, the body from the
 * form request), so use this only to override or add:
 *
 *     #[ApiDoc(summary: 'Ship an order', tags: ['Orders'], deprecated: true)]
 *     public function ship(Order $order): OrderResource
 *
 * On a controller class it applies to every method; `hidden: true` leaves
 * the route(s) out of the docs.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class ApiDoc
{
    /**
     * @param list<string>|null $tags
     * @param array<int, string>|null $responses Extra responses, status => description.
     */
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public ?array $tags = null,
        public bool $deprecated = false,
        public bool $hidden = false,
        public ?array $responses = null,
    ) {
    }
}

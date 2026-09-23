<?php

declare(strict_types=1);

namespace VtPhp\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Route
{
    /**
     * @param array<int, class-string> $middleware
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $name = null,
        public array $middleware = [],
    ) {
    }
}

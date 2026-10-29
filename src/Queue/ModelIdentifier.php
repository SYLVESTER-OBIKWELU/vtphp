<?php

declare(strict_types=1);

namespace VtPhp\Queue;

/**
 * What SerializesModels stores in place of a model (or a collection of them).
 */
final readonly class ModelIdentifier
{
    public function __construct(
        public string $class,
        public mixed $id,
        public bool $collection = false,
    ) {
    }
}

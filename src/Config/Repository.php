<?php

declare(strict_types=1);

namespace VtPhp\Config;

final class Repository
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $items = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $items[$segment] = $value;

                break;
            }

            if (!isset($items[$segment]) || !is_array($items[$segment])) {
                $items[$segment] = [];
            }

            $items = &$items[$segment];
        }
    }

    public function has(string $key): bool
    {
        $missing = "\0__missing__\0";

        return $this->get($key, $missing) !== $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Support;

final class Arr
{
    /**
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, array-key> $keys
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<int, array-key> $keys
     * @return array<array-key, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}

<?php

declare(strict_types=1);

namespace VtPhp\Support;

final class Str
{
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);

        return str_replace(' ', '', $value);
    }

    public static function snake(string $value): string
    {
        $value = (string) preg_replace('/\s+/u', '', $value);
        $value = (string) preg_replace('/(.)(?=[A-Z])/u', '$1_', $value);

        return mb_strtolower($value);
    }

    public static function random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}

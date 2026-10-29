<?php

declare(strict_types=1);

namespace VtPhp\Support;

use VtPhp\Config\Repository;

/**
 * The raw bytes of APP_KEY ("base64:..." is decoded), for HMAC signing.
 */
final class AppKey
{
    public static function bytes(Repository $config): string
    {
        $key = (string) $config->get('app.key', '');

        if ($key === '') {
            throw new \RuntimeException('No application key is set. Run `php forge key:generate`.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false || $decoded === '') {
                throw new \RuntimeException('APP_KEY is not valid base64. Run `php forge key:generate`.');
            }

            return $decoded;
        }

        return $key;
    }
}

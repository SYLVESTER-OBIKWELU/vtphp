<?php

namespace Core;

class Cache
{
    protected static $cachePath;
    protected static $defaultTtl = 3600; // 1 hour

    public static function setCachePath($path)
    {
        self::$cachePath = $path;
    }

    protected static function getCachePath()
    {
        if (!self::$cachePath) {
            self::$cachePath = dirname(__DIR__) . '/storage/cache/data';
        }

        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }

        return self::$cachePath;
    }

    protected static function getFilePath($key)
    {
        return self::getCachePath() . '/' . md5($key) . '.cache';
    }

    public static function has($key)
    {
        $file = self::getFilePath($key);
        
        if (!file_exists($file)) {
            return false;
        }

        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] && time() > $data['expires']) {
            self::forget($key);
            return false;
        }

        return true;
    }

    public static function get($key, $default = null)
    {
        if (!self::has($key)) {
            return $default;
        }

        $file = self::getFilePath($key);
        $data = unserialize(file_get_contents($file));

        return $data['value'];
    }

    public static function put($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? self::$defaultTtl;
        $file = self::getFilePath($key);

        $data = [
            'value' => $value,
            'expires' => $ttl ? time() + $ttl : null,
        ];

        file_put_contents($file, serialize($data), LOCK_EX);

        return true;
    }

    public static function forever($key, $value)
    {
        return self::put($key, $value, null);
    }

    public static function remember($key, $ttl, callable $callback)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::put($key, $value, $ttl);

        return $value;
    }

    public static function rememberForever($key, callable $callback)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::forever($key, $value);

        return $value;
    }

    public static function forget($key)
    {
        $file = self::getFilePath($key);
        
        if (file_exists($file)) {
            unlink($file);
            return true;
        }

        return false;
    }

    public static function flush()
    {
        $files = glob(self::getCachePath() . '/*.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public static function increment($key, $value = 1)
    {
        $current = self::get($key, 0);
        $new = $current + $value;
        self::put($key, $new);

        return $new;
    }

    public static function decrement($key, $value = 1)
    {
        return self::increment($key, -$value);
    }

    public static function pull($key, $default = null)
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /**
     * Get Redis instance for advanced usage
     */
    public static function getRedis()
    {
        static $redis = null;

        if ($redis === null) {
            if (!class_exists('Predis\Client')) {
                throw new \Exception("Predis not installed. Run: composer require predis/predis");
            }

            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => env('REDIS_HOST', '127.0.0.1'),
                'port'   => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD'),
                'database' => env('REDIS_DB', 0),
            ]);
        }

        return $redis;
    }
}

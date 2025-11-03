<?php

namespace Core;

class Cookie
{
    protected static $default_options = [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    public static function set($name, $value, $minutes = null, $options = [])
    {
        $options = array_merge(self::$default_options, $options);
        
        if ($minutes !== null) {
            $options['expires'] = time() + ($minutes * 60);
        }

        setcookie($name, $value, $options);
    }

    public static function get($name, $default = null)
    {
        return $_COOKIE[$name] ?? $default;
    }

    public static function has($name)
    {
        return isset($_COOKIE[$name]);
    }

    public static function forget($name)
    {
        if (self::has($name)) {
            self::set($name, '', -3600);
            unset($_COOKIE[$name]);
        }
    }

    public static function forever($name, $value, $options = [])
    {
        // Set for 5 years
        return self::set($name, $value, 2628000, $options);
    }

    public static function all()
    {
        return $_COOKIE;
    }
}

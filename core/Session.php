<?php

namespace Core;

class Session
{
    protected static $started = false;

    public static function start()
    {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
        }
    }

    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function push($key, $value)
    {
        self::start();
        
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $_SESSION[$key][] = $value;
    }

    public static function forget($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flush()
    {
        self::start();
        $_SESSION = [];
    }

    public static function regenerate($destroy = false)
    {
        self::start();
        session_regenerate_id($destroy);
    }

    public static function destroy()
    {
        self::start();
        session_destroy();
        self::$started = false;
    }

    public static function flash($key, $value = null)
    {
        if ($value === null) {
            // Get and remove flash data
            $data = self::get('_flash.' . $key);
            self::forget('_flash.' . $key);
            return $data;
        }

        // Set flash data
        self::put('_flash.' . $key, $value);
    }

    public static function reflash()
    {
        self::start();
        
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, '_flash.') === 0) {
                $newKey = str_replace('_flash.', '_flash_old.', $key);
                $_SESSION[$newKey] = $value;
            }
        }
    }

    public static function keep($keys = [])
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        foreach ($keys as $key) {
            self::flash($key, self::get('_flash.' . $key));
        }
    }

    public static function all()
    {
        self::start();
        return $_SESSION;
    }

    public static function token()
    {
        self::start();
        
        if (!self::has('_token')) {
            self::put('_token', bin2hex(random_bytes(32)));
        }

        return self::get('_token');
    }

    public static function regenerateToken()
    {
        self::start();
        self::put('_token', bin2hex(random_bytes(32)));
        return self::get('_token');
    }
}

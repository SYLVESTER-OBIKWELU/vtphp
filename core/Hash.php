<?php

namespace Core;

class Hash
{
    public static function make($value, $options = [])
    {
        $cost = $options['rounds'] ?? 10;

        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);
    }

    public static function check($value, $hashedValue)
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    public static function needsRehash($hashedValue, $options = [])
    {
        $cost = $options['rounds'] ?? 10;

        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);
    }

    public static function info($hashedValue)
    {
        return password_get_info($hashedValue);
    }
}

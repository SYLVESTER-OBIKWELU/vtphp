<?php

namespace Core;

class Event
{
    protected static $listeners = [];

    public static function listen($event, $listener)
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = $listener;
    }

    public static function dispatch($event, $payload = [])
    {
        $eventName = is_string($event) ? $event : get_class($event);
        $eventData = is_string($event) ? $payload : $event;

        if (!isset(self::$listeners[$eventName])) {
            return [];
        }

        $responses = [];

        foreach (self::$listeners[$eventName] as $listener) {
            if (is_callable($listener)) {
                $responses[] = $listener($eventData, $payload);
            } elseif (is_string($listener) && class_exists($listener)) {
                $instance = new $listener();
                if (method_exists($instance, 'handle')) {
                    $responses[] = $instance->handle($eventData, $payload);
                }
            }
        }

        return $responses;
    }

    public static function forget($event)
    {
        unset(self::$listeners[$event]);
    }

    public static function flush()
    {
        self::$listeners = [];
    }

    public static function hasListeners($event)
    {
        return isset(self::$listeners[$event]) && count(self::$listeners[$event]) > 0;
    }

    public static function getListeners($event = null)
    {
        if ($event === null) {
            return self::$listeners;
        }

        return self::$listeners[$event] ?? [];
    }
}

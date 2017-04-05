<?php

namespace Wazly\ASEC;

trait Singleton
{
    private static $instance;

    private function __construct()
    {
        $this->boot();
    }

    public static function getInstance()
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }

        $class = get_called_class();
        self::$instance = new $class;
        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return isset(self::$instance);
    }

    public static function clearInstance(): void
    {
        self::$instance = null;
    }
}

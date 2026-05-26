<?php

namespace App\Support;

class MockOfficerData
{
    private static array $data = [];

    public static function load(): array
    {
        if (empty(static::$data)) {
            static::$data = require database_path('mock/officer-data.php');
        }

        return static::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::load()[$key] ?? $default;
    }
}

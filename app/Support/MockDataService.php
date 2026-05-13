<?php

namespace App\Support;

class MockDataService
{
    private static array $data = [];

    public static function load(): array
    {
        if (empty(static::$data)) {
            static::$data = require database_path('mock/visa_applications.php');
        }

        return static::$data;
    }

    public static function stats(): array
    {
        return static::load()['stats'];
    }

    public static function overTime(): array
    {
        return static::load()['applications_over_time'];
    }

    public static function byVisaType(): array
    {
        return static::load()['by_visa_type'];
    }

    public static function applications(): array
    {
        return static::load()['applications'];
    }

    public static function recentApplications(int $limit = 5): array
    {
        return array_slice(static::applications(), 0, $limit);
    }
}

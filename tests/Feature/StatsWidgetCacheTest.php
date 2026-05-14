<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverviewWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatsWidgetCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_are_cached_after_first_call(): void
    {
        $widget = new StatsOverviewWidget;

        $this->callProtected($widget, 'getStats');

        $this->assertTrue(Cache::has('dashboard.stats'));
    }

    public function test_second_call_does_not_hit_database(): void
    {
        $widget = new StatsOverviewWidget;

        // Prime the cache
        $this->callProtected($widget, 'getStats');

        DB::connection()->enableQueryLog();

        // Second call should be served from cache
        $this->callProtected($widget, 'getStats');

        $queries = DB::connection()->getQueryLog();
        $this->assertEmpty($queries, 'Expected no DB queries on second stats call (cache miss detected)');
    }

    private function callProtected(object $object, string $method): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object);
    }
}

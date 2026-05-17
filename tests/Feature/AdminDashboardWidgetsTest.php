<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Reporting\Models\DailyApplicationMetrics;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Cache::flush();
    }

    public function test_stats_overview_widget_shows_submitted_total_from_daily_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $type = VisaType::factory()->create();
        DailyApplicationMetrics::create([
            'date' => today()->subDay(),
            'visa_type_id' => $type->ulid,
            'submitted_count' => 57,
            'approved_count' => 10,
            'rejected_count' => 3,
            'pending_count' => 44,
            'avg_processing_days' => 5.0,
        ]);

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSee('57');
    }

    public function test_stats_overview_widget_renders_with_no_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_stats_overview_widget_does_not_query_visa_applications_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $queries = [];
        \DB::listen(fn ($q) => $queries[] = $q->sql);

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class);

        $tablesQueried = collect($queries)
            ->filter(fn ($sql) => str_contains(strtolower($sql), 'visa_applications'))
            ->values();

        $this->assertCount(0, $tablesQueried, 'StatsOverviewWidget must not query visa_applications; found: '.implode(', ', $tablesQueried->toArray()));
    }
}

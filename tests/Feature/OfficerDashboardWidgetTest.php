<?php

namespace Tests\Feature;

use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use App\Filament\Officer\Widgets\OfficerStatsWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
        Cache::flush();
    }

    public function test_officer_stats_widget_shows_own_metrics(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        OfficerPerformanceMetrics::create([
            'date' => today(),
            'officer_id' => $officer->id,
            'reviewed_count' => 8,
            'approved_count' => 6,
            'rejected_count' => 2,
            'info_requested_count' => 1,
            'avg_review_hours' => 2.5,
        ]);

        Livewire::actingAs($officer)
            ->test(OfficerStatsWidget::class)
            ->assertSee('8')
            ->assertSee('6')
            ->assertSee('2');
    }

    public function test_officer_stats_widget_does_not_show_other_officer_metrics(): void
    {
        $officerA = User::factory()->create();
        $officerA->assignRole('case_officer');

        $officerB = User::factory()->create();
        $officerB->assignRole('case_officer');

        OfficerPerformanceMetrics::create([
            'date' => today(),
            'officer_id' => $officerB->id,
            'reviewed_count' => 99,
            'approved_count' => 80,
            'rejected_count' => 19,
            'info_requested_count' => 5,
            'avg_review_hours' => 4.0,
        ]);

        Livewire::actingAs($officerA)
            ->test(OfficerStatsWidget::class)
            ->assertDontSee('99');
    }

    public function test_officer_stats_widget_renders_with_no_data(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(OfficerStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('0');
    }
}

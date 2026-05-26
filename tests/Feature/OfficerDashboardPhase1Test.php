<?php

namespace Tests\Feature;

use App\Filament\Officer\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerDashboardPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    public function test_officer_dashboard_renders_sidebar_placeholder(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->assertSee('Sidebar');
    }

    public function test_officer_dashboard_renders_main_placeholder(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->assertSee('Main');
    }

    public function test_officer_dashboard_columns_returns_12_column_grid(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $instance = Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->instance();

        $this->assertEquals(['default' => 1, 'md' => 12], $instance->getColumns());
    }

    public function test_officer_dashboard_widgets_returns_empty_array(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $instance = Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->instance();

        $this->assertEmpty($instance->getWidgets());
    }
}

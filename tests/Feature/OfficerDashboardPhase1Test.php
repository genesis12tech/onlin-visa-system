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

    public function test_officer_dashboard_renders_successfully(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->assertSuccessful();
    }

    public function test_officer_dashboard_columns_returns_4_column_grid(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $instance = Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->instance();

        $this->assertEquals(['default' => 1, 'md' => 4], $instance->getColumns());
    }

    public function test_officer_dashboard_registers_all_five_widgets(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $instance = Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->instance();

        $this->assertCount(5, $instance->getWidgets());
    }
}

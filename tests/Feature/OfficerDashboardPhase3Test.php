<?php

namespace Tests\Feature;

use App\Filament\Officer\Pages\Dashboard;
use App\Filament\Officer\Widgets\DashboardHeader;
use App\Filament\Officer\Widgets\PriorityQueueTable;
use App\Filament\Officer\Widgets\StatsOverview;
use App\Filament\Officer\Widgets\TeamWorkload;
use App\Filament\Officer\Widgets\TodaysActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerDashboardPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    // ── DashboardHeader ────────────────────────────

    public function test_dashboard_header_widget_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(DashboardHeader::class)
            ->assertSuccessful();
    }

    public function test_dashboard_header_shows_officer_name(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(DashboardHeader::class)
            ->assertSee('Priya Mehta');
    }

    public function test_dashboard_header_shows_pending_count(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(DashboardHeader::class)
            ->assertSee('12');
    }

    // ── StatsOverview ──────────────────────────────

    public function test_stats_overview_widget_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(StatsOverview::class)
            ->assertSuccessful();
    }

    public function test_stats_overview_shows_assigned_to_me_value(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(StatsOverview::class)
            ->assertSee('Assigned to Me');
    }

    public function test_stats_overview_shows_pending_queue_label(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(StatsOverview::class)
            ->assertSee('Pending Queue');
    }

    // ── PriorityQueueTable ─────────────────────────

    public function test_priority_queue_table_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(PriorityQueueTable::class)
            ->assertSuccessful();
    }

    public function test_priority_queue_table_shows_mock_applicant_names(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(PriorityQueueTable::class)
            ->assertSee('Arjun Mehta');
    }

    public function test_priority_queue_table_shows_mock_references(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(PriorityQueueTable::class)
            ->assertSee('VA-2024-A1F3K2');
    }

    // ── TeamWorkload ───────────────────────────────

    public function test_team_workload_widget_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(TeamWorkload::class)
            ->assertSuccessful();
    }

    public function test_team_workload_shows_member_names(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(TeamWorkload::class)
            ->assertSee('Priya Mehta')
            ->assertSee('Rahul Sharma');
    }

    // ── TodaysActivity ─────────────────────────────

    public function test_todays_activity_widget_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(TodaysActivity::class)
            ->assertSuccessful();
    }

    public function test_todays_activity_shows_event_descriptions(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(TodaysActivity::class)
            ->assertSee('Lucas Müller')
            ->assertSee('2h ago');
    }

    // ── Dashboard page widget registration ─────────

    public function test_dashboard_page_registers_all_required_widgets(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $widgets = Livewire::actingAs($officer)
            ->test(Dashboard::class)
            ->instance()
            ->getWidgets();

        $this->assertContains(DashboardHeader::class, $widgets);
        $this->assertContains(StatsOverview::class, $widgets);
        $this->assertContains(PriorityQueueTable::class, $widgets);
        $this->assertContains(TeamWorkload::class, $widgets);
        $this->assertContains(TodaysActivity::class, $widgets);
    }
}

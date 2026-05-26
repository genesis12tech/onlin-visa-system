<?php

namespace Tests\Feature;

use App\Domain\Applications\Queries\PendingQueueCount;
use App\Filament\Officer\Pages\AllApplications;
use App\Filament\Officer\Pages\DocumentReview;
use App\Filament\Officer\Pages\MyAssigned;
use App\Filament\Officer\Pages\OfficerPerformance;
use App\Filament\Officer\Pages\Reports;
use App\Filament\Officer\Pages\ReviewQueue;
use App\Filament\Officer\Pages\SearchPage;
use App\Models\User;
use App\Support\MockOfficerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerDashboardPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    // ── MockOfficerData ────────────────────────────

    public function test_mock_officer_data_loads_officer_name(): void
    {
        $data = MockOfficerData::load();

        $this->assertEquals('Priya Mehta', $data['officer']['name']);
    }

    public function test_mock_officer_data_get_returns_pending_queue_value(): void
    {
        $stats = MockOfficerData::get('stats');

        $this->assertEquals(12, $stats['pending_queue']['value']);
    }

    // ── PendingQueueCount ──────────────────────────

    public function test_pending_queue_count_returns_12(): void
    {
        $count = app(PendingQueueCount::class)->count();

        $this->assertEquals(12, $count);
    }

    // ── ReviewQueue page ───────────────────────────

    public function test_review_queue_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(ReviewQueue::class)
            ->assertSuccessful();
    }

    public function test_review_queue_navigation_badge_is_12(): void
    {
        $this->assertEquals('12', ReviewQueue::getNavigationBadge());
    }

    public function test_review_queue_is_in_workspace_navigation_group(): void
    {
        $this->assertEquals('Workspace', ReviewQueue::getNavigationGroup());
    }

    // ── MyAssigned page ────────────────────────────

    public function test_my_assigned_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(MyAssigned::class)
            ->assertSuccessful();
    }

    public function test_my_assigned_navigation_badge_is_8(): void
    {
        $this->assertEquals('8', MyAssigned::getNavigationBadge());
    }

    // ── AllApplications page ───────────────────────

    public function test_all_applications_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(AllApplications::class)
            ->assertSuccessful();
    }

    // ── DocumentReview page ────────────────────────

    public function test_document_review_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(DocumentReview::class)
            ->assertSuccessful();
    }

    public function test_document_review_navigation_badge_is_5(): void
    {
        $this->assertEquals('5', DocumentReview::getNavigationBadge());
    }

    public function test_document_review_is_in_tools_navigation_group(): void
    {
        $this->assertEquals('Tools', DocumentReview::getNavigationGroup());
    }

    // ── SearchPage ─────────────────────────────────

    public function test_search_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(SearchPage::class)
            ->assertSuccessful();
    }

    // ── Reports page ───────────────────────────────

    public function test_reports_page_renders_for_officer(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        Livewire::actingAs($officer)
            ->test(Reports::class)
            ->assertSuccessful();
    }

    // ── OfficerPerformance group assignment ────────

    public function test_officer_performance_is_in_reporting_navigation_group(): void
    {
        $this->assertEquals('Reporting', OfficerPerformance::getNavigationGroup());
    }
}

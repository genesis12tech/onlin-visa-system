<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use App\Filament\Officer\Pages\OfficerPerformance;
use App\Filament\Officer\Pages\SlaBreaches;
use App\Filament\Officer\Pages\TeamQueue;
use App\Filament\Officer\Widgets\SlaAlertWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['case_officer', 'senior_officer', 'document_verifier', 'finance_officer', 'admin', 'super_admin', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ── TeamQueue ──────────────────────────────────

    public function test_team_queue_is_accessible_to_senior_officer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('senior_officer');

        Livewire::actingAs($user)
            ->test(TeamQueue::class)
            ->assertSuccessful();
    }

    public function test_team_queue_is_accessible_to_admin(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(TeamQueue::class)
            ->assertSuccessful();
    }

    public function test_team_queue_returns_403_for_case_officer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('case_officer');

        $this->actingAs($user)
            ->get('/officer/team-queue')
            ->assertForbidden();
    }

    public function test_team_queue_shows_all_applications_regardless_of_assignment(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $unassigned = VisaApplication::factory()->create([
            'assigned_officer_id' => null,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $assigned = VisaApplication::factory()->create([
            'assigned_officer_id' => $officer->id,
            'status' => ApplicationStatus::UnderReview,
        ]);

        Livewire::actingAs($senior)
            ->test(TeamQueue::class)
            ->assertCanSeeTableRecords([$unassigned, $assigned]);
    }

    // ── SlaBreaches ────────────────────────────────

    public function test_sla_breaches_is_accessible_to_senior_officer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('senior_officer');

        Livewire::actingAs($user)
            ->test(SlaBreaches::class)
            ->assertSuccessful();
    }

    public function test_sla_breaches_returns_403_for_case_officer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('case_officer');

        $this->actingAs($user)
            ->get('/officer/sla-breaches')
            ->assertForbidden();
    }

    public function test_sla_breaches_shows_only_breached_applications(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $visaType = VisaType::factory()->create(['processing_days' => 10]);

        $breached = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(20),
            'decision_at' => null,
        ]);

        $onTime = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(3),
            'decision_at' => null,
        ]);

        Livewire::actingAs($senior)
            ->test(SlaBreaches::class)
            ->assertCanSeeTableRecords([$breached])
            ->assertCanNotSeeTableRecords([$onTime]);
    }

    public function test_sla_breaches_get_breach_count_returns_correct_total(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $visaType = VisaType::factory()->create(['processing_days' => 5]);

        VisaApplication::factory()->count(3)->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(20),
            'decision_at' => null,
        ]);

        $component = Livewire::actingAs($senior)->test(SlaBreaches::class);

        $this->assertEquals(3, $component->instance()->getBreachCount());
    }

    // ── OfficerPerformance ─────────────────────────

    public function test_officer_performance_is_accessible_to_case_officer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('case_officer');

        Livewire::actingAs($user)
            ->test(OfficerPerformance::class)
            ->assertSuccessful();
    }

    public function test_officer_performance_shows_own_metrics_only(): void
    {
        $officerA = User::factory()->create(['email_verified_at' => now()]);
        $officerA->assignRole('case_officer');

        $officerB = User::factory()->create(['email_verified_at' => now()]);
        $officerB->assignRole('case_officer');

        $metricsA = OfficerPerformanceMetrics::create([
            'date' => today(),
            'officer_id' => $officerA->id,
            'reviewed_count' => 7,
            'approved_count' => 5,
            'rejected_count' => 2,
            'info_requested_count' => 0,
            'avg_review_hours' => 1.5,
        ]);

        $metricsB = OfficerPerformanceMetrics::create([
            'date' => today(),
            'officer_id' => $officerB->id,
            'reviewed_count' => 99,
            'approved_count' => 80,
            'rejected_count' => 19,
            'info_requested_count' => 0,
            'avg_review_hours' => 3.0,
        ]);

        Livewire::actingAs($officerA)
            ->test(OfficerPerformance::class)
            ->assertCanSeeTableRecords([$metricsA])
            ->assertCanNotSeeTableRecords([$metricsB]);
    }

    public function test_officer_performance_summary_sums_last_30_days_only(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        OfficerPerformanceMetrics::create([
            'date' => today()->subDays(5),
            'officer_id' => $officer->id,
            'reviewed_count' => 4,
            'approved_count' => 3,
            'rejected_count' => 1,
            'info_requested_count' => 1,
            'avg_review_hours' => 2.0,
        ]);

        OfficerPerformanceMetrics::create([
            'date' => today()->subDays(10),
            'officer_id' => $officer->id,
            'reviewed_count' => 6,
            'approved_count' => 5,
            'rejected_count' => 1,
            'info_requested_count' => 0,
            'avg_review_hours' => 3.0,
        ]);

        // Outside the 30-day window — must be excluded
        OfficerPerformanceMetrics::create([
            'date' => today()->subDays(40),
            'officer_id' => $officer->id,
            'reviewed_count' => 100,
            'approved_count' => 90,
            'rejected_count' => 10,
            'info_requested_count' => 0,
            'avg_review_hours' => 5.0,
        ]);

        $page = Livewire::actingAs($officer)->test(OfficerPerformance::class)->instance();
        $summary = $page->getSummary();

        $this->assertEquals(10, $summary['reviewed']);
        $this->assertEquals(8, $summary['approved']);
        $this->assertEquals(2, $summary['rejected']);
    }

    // ── SlaAlertWidget ─────────────────────────────

    public function test_sla_alert_widget_can_only_be_viewed_by_senior_officer_and_above(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $this->actingAs($senior);
        $this->assertTrue(SlaAlertWidget::canView());
    }

    public function test_sla_alert_widget_returns_correct_breach_count(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $visaType = VisaType::factory()->create(['processing_days' => 5]);

        VisaApplication::factory()->count(2)->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(20),
            'decision_at' => null,
        ]);

        $widget = Livewire::actingAs($senior)->test(SlaAlertWidget::class)->instance();

        $this->assertEquals(2, $widget->getBreachCount());
    }

    public function test_sla_alert_widget_returns_zero_when_no_breaches(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $widget = Livewire::actingAs($senior)->test(SlaAlertWidget::class)->instance();

        $this->assertEquals(0, $widget->getBreachCount());
    }
}

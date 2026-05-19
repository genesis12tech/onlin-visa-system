<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisaApplicationSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_breached_scope_returns_past_deadline_applications(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 10]);

        $breached = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(20),
            'decision_at' => null,
        ]);

        $notYet = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(5),
            'decision_at' => null,
        ]);

        $results = VisaApplication::slaBreached()->pluck('visa_applications.ulid');

        $this->assertTrue($results->contains($breached->ulid));
        $this->assertFalse($results->contains($notYet->ulid));
    }

    public function test_sla_breached_scope_excludes_decided_applications(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 5]);

        $decided = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::Approved,
            'submitted_at' => now()->subDays(30),
            'decision_at' => now(),
        ]);

        $results = VisaApplication::slaBreached()->pluck('visa_applications.ulid');

        $this->assertFalse($results->contains($decided->ulid));
    }

    public function test_sla_breached_scope_excludes_unsubmitted_applications(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 5]);

        $draft = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::Draft,
            'submitted_at' => null,
            'decision_at' => null,
        ]);

        $results = VisaApplication::slaBreached()->pluck('visa_applications.ulid');

        $this->assertFalse($results->contains($draft->ulid));
    }

    public function test_sla_at_risk_scope_returns_applications_within_two_days_of_deadline(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 10]);

        $atRisk = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(9),
            'decision_at' => null,
        ]);

        $safe = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now()->subDays(3),
            'decision_at' => null,
        ]);

        $results = VisaApplication::slaAtRisk()->pluck('visa_applications.ulid');

        $this->assertTrue($results->contains($atRisk->ulid));
        $this->assertFalse($results->contains($safe->ulid));
    }

    public function test_sla_remaining_days_is_positive_for_future_deadline(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 30]);

        $application = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'submitted_at' => now()->subDays(10),
            'decision_at' => null,
        ]);

        $application->load('visaType');

        $this->assertGreaterThan(0, $application->sla_remaining_days);
        $this->assertEqualsWithDelta(20, $application->sla_remaining_days, 1);
    }

    public function test_sla_remaining_days_is_negative_for_past_deadline(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 10]);

        $application = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'submitted_at' => now()->subDays(20),
            'decision_at' => null,
        ]);

        $application->load('visaType');

        $this->assertLessThan(0, $application->sla_remaining_days);
        $this->assertEqualsWithDelta(-10, $application->sla_remaining_days, 1);
    }

    public function test_sla_remaining_days_is_zero_when_decided(): void
    {
        $visaType = VisaType::factory()->create(['processing_days' => 30]);

        $application = VisaApplication::factory()->create([
            'visa_type_id' => $visaType->ulid,
            'submitted_at' => now()->subDays(5),
            'decision_at' => now(),
        ]);

        $application->load('visaType');

        $this->assertEquals(0, $application->sla_remaining_days);
    }
}

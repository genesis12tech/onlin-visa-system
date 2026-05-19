<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveApplicationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_validity_period_and_entry_type(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new ApproveApplication)->execute(
            $application,
            $actor,
            null,
            '6_months',
            'multiple',
        );

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'validity_period' => '6_months',
            'entry_type' => 'multiple',
        ]);
    }

    public function test_stores_internal_note_when_provided(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new ApproveApplication)->execute(
            $application,
            $actor,
            null,
            '12_months',
            'single',
            'Applicant meets all criteria.',
        );

        $this->assertDatabaseHas('application_notes', [
            'visa_application_id' => $application->ulid,
            'body' => 'Applicant meets all criteria.',
            'is_visible_to_applicant' => false,
        ]);
    }

    public function test_existing_behaviour_unchanged_with_no_extra_params(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new ApproveApplication)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Approved->value,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Enums\RejectionReason;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectApplicationUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_rejection_reason_enum_value(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new RejectApplication)->execute(
            $application,
            $actor,
            'Incomplete documents',
            RejectionReason::IncompleteApplication,
            'Your application was missing required documents.',
        );

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Rejected->value,
            'decision_reason' => RejectionReason::IncompleteApplication->value,
        ]);
    }

    public function test_creates_applicant_visible_note_with_explanation(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new RejectApplication)->execute(
            $application,
            $actor,
            'Incomplete documents',
            RejectionReason::IncompleteApplication,
            'Your application was missing required documents.',
        );

        $this->assertDatabaseHas('application_notes', [
            'visa_application_id' => $application->ulid,
            'body' => 'Your application was missing required documents.',
            'is_visible_to_applicant' => true,
        ]);
    }

    public function test_creates_internal_note_when_provided(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new RejectApplication)->execute(
            $application,
            $actor,
            'legacy reason',
            RejectionReason::Other,
            'We could not process your application.',
            'Internal note: flagged for fraud review.',
        );

        $this->assertDatabaseHas('application_notes', [
            'visa_application_id' => $application->ulid,
            'body' => 'Internal note: flagged for fraud review.',
            'is_visible_to_applicant' => false,
        ]);
    }

    public function test_existing_behaviour_unchanged_with_minimal_params(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
        ]);

        (new RejectApplication)->execute($application, $actor, 'Basic rejection reason');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Rejected->value,
        ]);
    }
}

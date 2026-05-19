<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitInfoResponse;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubmitInfoResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_transitions_status_to_under_review(): void
    {
        ['application' => $application, 'actor' => $actor] = $this->makeInfoRequestedApplication(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);

        (new SubmitInfoResponse)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::UnderReview->value,
        ]);
    }

    public function test_records_status_history(): void
    {
        ['application' => $application, 'actor' => $actor] = $this->makeInfoRequestedApplication(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);

        (new SubmitInfoResponse)->execute($application, $actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::AdditionalInfoRequested->value,
            'to_status' => ApplicationStatus::UnderReview->value,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_throws_if_application_not_in_additional_info_requested_status(): void
    {
        $application = VisaApplication::factory()->submitted()->create();
        $actor = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not awaiting an info response');

        (new SubmitInfoResponse)->execute($application, $actor);
    }

    public function test_throws_if_unlocked_field_has_no_answer(): void
    {
        ['application' => $application, 'actor' => $actor] = $this->makeInfoRequestedApplication(
            fieldsToUnlock: ['travel_details.travel_purpose', 'background.previous_visa_refusal'],
        );

        // Only fill one of two required fields
        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fill in all requested fields');

        (new SubmitInfoResponse)->execute($application, $actor);
    }

    public function test_throws_if_blocking_document_remains(): void
    {
        ['application' => $application, 'actor' => $actor] = $this->makeInfoRequestedApplication(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);

        $docType = DocumentType::factory()->create();
        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Pending,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All requested documents must be uploaded');

        (new SubmitInfoResponse)->execute($application, $actor);
    }

    public function test_succeeds_when_no_fields_to_unlock_but_docs_all_accepted(): void
    {
        ['application' => $application, 'actor' => $actor] = $this->makeInfoRequestedApplication(
            fieldsToUnlock: [],
        );

        (new SubmitInfoResponse)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::UnderReview->value,
        ]);
    }

    public function test_policy_allows_own_applicant_to_respond(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('applicant');
        $profile = ApplicantProfile::factory()->create(['user_id' => $actor->id]);
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $profile->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        $this->assertTrue($actor->can('respondToInfoRequest', $application));
    }

    public function test_policy_denies_when_status_is_not_additional_info_requested(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('applicant');
        $profile = ApplicantProfile::factory()->create(['user_id' => $actor->id]);
        $application = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $profile->ulid,
        ]);

        $this->assertFalse($actor->can('respondToInfoRequest', $application));
    }

    public function test_policy_denies_another_applicants_application(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('applicant');
        ApplicantProfile::factory()->create(['user_id' => $actor->id]);

        $otherApplication = VisaApplication::factory()->create([
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        $this->assertFalse($actor->can('respondToInfoRequest', $otherApplication));
    }

    /** @return array{application: VisaApplication, actor: User} */
    private function makeInfoRequestedApplication(array $fieldsToUnlock): array
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        ApplicationNote::create([
            'visa_application_id' => $application->ulid,
            'author_id' => $actor->id,
            'body' => 'Please provide the required information.',
            'is_visible_to_applicant' => true,
            'metadata' => [
                'fields_to_unlock' => $fieldsToUnlock,
                'deadline_days' => 7,
                'deadline_date' => now()->addDays(7)->toDateString(),
            ],
        ]);

        return compact('application', 'actor');
    }
}

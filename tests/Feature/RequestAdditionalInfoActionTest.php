<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequestAdditionalInfoActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_action_transitions_status_to_additional_info_requested(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide your travel itinerary.');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested->value,
        ]);
    }

    public function test_action_records_status_history(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide proof of funds.');

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'to_status' => ApplicationStatus::AdditionalInfoRequested->value,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_action_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide hotel bookings.');

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, AdditionalInfoRequestedNotification::class);
    }

    public function test_identity_fields_cannot_be_unlocked(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Identity fields cannot be unlocked/');

        (new RequestAdditionalInformation)->execute(
            $application,
            $actor,
            'Please correct your details.',
            [],
            ['personal.first_name', 'personal.passport_number'],
        );
    }

    public function test_each_identity_field_is_individually_blocked(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);

        $identityFields = [
            'personal.first_name',
            'personal.last_name',
            'personal.date_of_birth',
            'personal.nationality',
            'personal.passport_number',
        ];

        foreach ($identityFields as $field) {
            try {
                (new RequestAdditionalInformation)->execute($application, $actor, 'msg', [], [$field]);
                $this->fail("Expected InvalidArgumentException for field: {$field}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }
    }

    public function test_non_identity_fields_can_be_unlocked(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);

        // Must not throw
        (new RequestAdditionalInformation)->execute(
            $application,
            $actor,
            'Please update your employment details.',
            [],
            ['employment.employer_name', 'travel.purpose'],
        );

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested->value,
        ]);
    }

    public function test_documents_to_resubmit_are_reset_to_pending(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);

        $docType = DocumentType::create([
            'name' => 'Passport',
            'accepted_mime_types' => json_encode(['application/pdf']),
            'max_size_kb' => 5000,
        ]);
        $document = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Rejected,
        ]);

        (new RequestAdditionalInformation)->execute(
            $application,
            $actor,
            'Please re-upload your passport scan.',
            [$document->ulid],
        );

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $document->ulid,
            'status' => DocumentStatus::Pending->value,
        ]);
    }

    public function test_documents_not_in_list_are_not_reset(): void
    {
        $actor = User::factory()->create();
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);

        $docType = DocumentType::create([
            'name' => 'Bank Statement',
            'accepted_mime_types' => json_encode(['application/pdf']),
            'max_size_kb' => 5000,
        ]);
        $untouched = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Accepted,
        ]);

        (new RequestAdditionalInformation)->execute(
            $application,
            $actor,
            'Please re-upload your bank statement.',
            [],
        );

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $untouched->ulid,
            'status' => DocumentStatus::Accepted->value,
        ]);
    }

    private function makeApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }
}

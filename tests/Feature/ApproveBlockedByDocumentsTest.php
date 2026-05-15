<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApproveBlockedByDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_approve_throws_when_required_document_is_uploaded_not_accepted(): void
    {
        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot approve');

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_throws_when_required_document_is_rejected(): void
    {
        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Rejected,
        ]);

        $this->expectException(\RuntimeException::class);

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_throws_when_required_document_is_missing(): void
    {
        [$application] = $this->makeApplicationWithRequiredDoc();

        $this->expectException(\RuntimeException::class);

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_succeeds_when_all_required_documents_are_accepted(): void
    {
        Notification::fake();

        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Accepted,
        ]);

        $actor = User::factory()->create();
        (new ApproveApplication)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Approved->value,
        ]);
    }

    public function test_approve_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Accepted,
        ]);

        $actor = User::factory()->create();
        (new ApproveApplication)->execute($application, $actor);

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, ApplicationApprovedNotification::class);
    }

    private function makeApplicationWithRequiredDoc(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $docType = DocumentType::factory()->create([
            'name' => 'Passport',
        ]);
        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $type->ulid,
            'document_type_id' => $docType->ulid,
            'is_required' => true,
            'display_order' => 1,
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
        $application = VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);

        return [$application, $docType];
    }
}

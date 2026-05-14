<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitApplicationDocumentCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_fails_if_any_document_slot_is_pending(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Pending);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All required documents must be uploaded before submission.');

        (new SubmitApplication)->execute($context['application'], $context['actor']);
    }

    public function test_submit_fails_if_any_document_slot_is_rejected(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Rejected);

        $this->expectException(\RuntimeException::class);

        (new SubmitApplication)->execute($context['application'], $context['actor']);
    }

    public function test_submit_succeeds_when_all_document_slots_are_uploaded(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Uploaded);

        $application = (new SubmitApplication)->execute($context['application'], $context['actor']);

        $this->assertEquals(ApplicationStatus::Submitted, $application->status);
    }

    public function test_submit_succeeds_when_there_are_no_document_slots(): void
    {
        $context = $this->makeContext();
        $actor = User::factory()->create();

        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $context['profile']->ulid,
            'visa_type_id' => $context['visaType']->ulid,
            'form_template_id' => $context['form']->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $result = (new SubmitApplication)->execute($application, $actor);

        $this->assertEquals(ApplicationStatus::Submitted, $result->status);
    }

    private function makeApplicationWithDocumentSlots(DocumentStatus $docStatus): array
    {
        $context = $this->makeContext();
        $actor = User::factory()->create();
        $docType = DocumentType::factory()->create();

        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $context['profile']->ulid,
            'visa_type_id' => $context['visaType']->ulid,
            'form_template_id' => $context['form']->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => $docStatus,
        ]);

        return ['application' => $application, 'actor' => $actor];
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
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

        return compact('country', 'visaType', 'form', 'profile');
    }
}

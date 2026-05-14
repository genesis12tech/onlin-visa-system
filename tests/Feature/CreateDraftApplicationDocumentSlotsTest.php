<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\CreateDraftApplication;
use App\Domain\Applications\Actions\GenerateTrackingNumber;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDraftApplicationDocumentSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_document_slots_for_each_required_document_type(): void
    {
        $context = $this->makeContext();

        $docType1 = DocumentType::factory()->create();
        $docType2 = DocumentType::factory()->create();

        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $context['visaType']->ulid,
            'document_type_id' => $docType1->ulid,
            'is_required' => true,
            'display_order' => 1,
        ]);
        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $context['visaType']->ulid,
            'document_type_id' => $docType2->ulid,
            'is_required' => true,
            'display_order' => 2,
        ]);

        $application = (new CreateDraftApplication(new GenerateTrackingNumber))->execute(
            $context['profile'],
            $context['visaType'],
            $context['form'],
        );

        $this->assertDatabaseCount('application_documents', 2);
        $this->assertDatabaseHas('application_documents', [
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType1->ulid,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('application_documents', [
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType2->ulid,
            'status' => 'pending',
        ]);
    }

    public function test_creates_no_document_slots_when_visa_type_has_no_requirements(): void
    {
        $context = $this->makeContext();

        (new CreateDraftApplication(new GenerateTrackingNumber))->execute(
            $context['profile'],
            $context['visaType'],
            $context['form'],
        );

        $this->assertDatabaseCount('application_documents', 0);
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

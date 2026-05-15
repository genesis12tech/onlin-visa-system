<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\AcceptDocument;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptRejectDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_sets_status_to_accepted(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new AcceptDocument)->execute($docSlot, $officer);

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Accepted->value,
            'reviewed_by' => $officer->id,
        ]);
        $this->assertNotNull($docSlot->fresh()->reviewed_at);
    }

    public function test_accept_writes_audit_log(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new AcceptDocument)->execute($docSlot, $officer);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.accepted',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $officer->id,
        ]);
    }

    public function test_reject_sets_status_to_rejected_with_reason(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new RejectDocument)->execute($docSlot, $officer, 'Passport photo is too dark.');

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Rejected->value,
            'reviewed_by' => $officer->id,
            'rejection_reason' => 'Passport photo is too dark.',
        ]);
    }

    public function test_reject_writes_audit_log_with_reason(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new RejectDocument)->execute($docSlot, $officer, 'Too blurry');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.rejected',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $officer->id,
        ]);
    }

    private function makeUploadedDocument(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
        $officer = User::factory()->create();

        return [$docSlot, $officer];
    }
}

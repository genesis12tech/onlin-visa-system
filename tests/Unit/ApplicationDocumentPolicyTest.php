<?php

namespace Tests\Unit;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->policy = new ApplicationDocumentPolicy;

        foreach (['super_admin', 'admin', 'case_officer', 'senior_officer', 'document_verifier', 'support_staff', 'applicant'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_applicant_can_view_their_own_document(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser();

        $this->assertTrue($this->policy->view($applicantUser, $docSlot));
    }

    public function test_applicant_cannot_view_another_applicants_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $otherUser = User::factory()->create();

        $this->assertFalse($this->policy->view($otherUser, $docSlot));
    }

    public function test_case_officer_can_view_any_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $this->assertTrue($this->policy->view($officer, $docSlot));
    }

    public function test_applicant_can_upload_to_draft_application(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::Draft);

        $this->assertTrue($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_applicant_cannot_upload_to_submitted_application(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::Submitted);

        $this->assertFalse($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_applicant_can_upload_when_docs_required(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::DocsRequired);

        $this->assertTrue($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_other_applicant_cannot_upload_to_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser(ApplicationStatus::Draft);
        $otherUser = User::factory()->create();

        $this->assertFalse($this->policy->upload($otherUser, $docSlot));
    }

    public function test_document_verifier_can_accept_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');

        $this->assertTrue($this->policy->accept($verifier, $docSlot));
    }

    public function test_document_verifier_can_reject_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');

        $this->assertTrue($this->policy->reject($verifier, $docSlot));
    }

    public function test_applicant_cannot_accept_document(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser();

        $this->assertFalse($this->policy->accept($applicantUser, $docSlot));
    }

    private function makeDocumentForUser(ApplicationStatus $status = ApplicationStatus::Submitted): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
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
            'status' => $status,
        ]);
        $docType = DocumentType::factory()->create();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        return [$docSlot, $applicantUser];
    }
}

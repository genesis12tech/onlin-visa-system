<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationDocumentPolicyOfficerTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->policy = new ApplicationDocumentPolicy;

        foreach (['case_officer', 'senior_officer', 'document_verifier', 'finance_officer', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_finance_officer_cannot_view_documents(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $document = $this->makeDocument();

        $this->assertFalse($this->policy->view($finance, $document));
    }

    public function test_finance_officer_cannot_accept_documents(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $document = $this->makeDocument();

        $this->assertFalse($this->policy->accept($finance, $document));
    }

    public function test_finance_officer_cannot_reject_documents(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_officer');
        $document = $this->makeDocument();

        $this->assertFalse($this->policy->reject($finance, $document));
    }

    public function test_case_officer_can_accept_their_assigned_applications_document(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $document = $this->makeDocument(assignedOfficerId: $officer->id);

        $this->assertTrue($this->policy->accept($officer, $document));
    }

    public function test_case_officer_cannot_accept_unassigned_applications_document(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $document = $this->makeDocument(assignedOfficerId: null);

        $this->assertFalse($this->policy->accept($officer, $document));
    }

    public function test_document_verifier_can_accept_their_assigned_applications_document(): void
    {
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');
        $document = $this->makeDocument(assignedOfficerId: $verifier->id);

        $this->assertTrue($this->policy->accept($verifier, $document));
    }

    public function test_document_verifier_cannot_accept_unassigned_applications_document(): void
    {
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');
        $document = $this->makeDocument(assignedOfficerId: null);

        $this->assertFalse($this->policy->accept($verifier, $document));
    }

    public function test_senior_officer_can_accept_any_document(): void
    {
        $senior = User::factory()->create();
        $senior->assignRole('senior_officer');
        $document = $this->makeDocument(assignedOfficerId: null);

        $this->assertTrue($this->policy->accept($senior, $document));
    }

    private function makeDocument(?int $assignedOfficerId = null): ApplicationDocument
    {
        $application = VisaApplication::factory()->create([
            'status' => ApplicationStatus::UnderReview,
            'assigned_officer_id' => $assignedOfficerId,
        ]);

        $docType = DocumentType::factory()->create();

        return ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
    }
}

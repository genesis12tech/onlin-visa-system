<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Livewire\Applications\DocumentUploadPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentUploadPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_status_has_human_readable_labels(): void
    {
        $this->assertSame('Pending', DocumentStatus::Pending->label());
        $this->assertSame('Uploaded', DocumentStatus::Uploaded->label());
        $this->assertSame('Scanning', DocumentStatus::PendingScan->label());
        $this->assertSame('Under Review', DocumentStatus::UnderReview->label());
        $this->assertSame('Accepted', DocumentStatus::Accepted->label());
        $this->assertSame('Rejected', DocumentStatus::Rejected->label());
        $this->assertSame('Security Failed', DocumentStatus::Infected->label());
    }

    public function test_component_mounts_and_lists_document_slots(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $profile = ApplicantProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $docType = DocumentType::factory()->create(['name' => 'Passport Scan']);
        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Pending,
        ]);

        Livewire::actingAs($user)
            ->test(DocumentUploadPanel::class, ['applicationUlid' => $application->ulid])
            ->assertOk()
            ->assertSee('Passport Scan');
    }
}

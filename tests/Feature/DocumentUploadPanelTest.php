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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_upload_creates_document_version_and_resets_pending_file(): void
    {
        Storage::fake('documents');
        Queue::fake();

        [$user, $application, $docSlot] = $this->makeApplicantWithDraftAndSlot();

        Livewire::actingAs($user)
            ->test(DocumentUploadPanel::class, ['applicationUlid' => $application->ulid])
            ->call('selectDocument', $docSlot->ulid)
            ->set('pendingFile', UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'))
            ->assertSet('pendingFile', null)
            ->assertSet('pendingDocumentUlid', '')
            ->assertSet('uploadError', null);

        $this->assertDatabaseHas('document_versions', [
            'application_document_id' => $docSlot->ulid,
            'original_filename' => 'passport.pdf',
        ]);
    }

    public function test_upload_sets_upload_error_on_invalid_mime_type(): void
    {
        Storage::fake('documents');

        [$user, $application, $docSlot] = $this->makeApplicantWithDraftAndSlot();

        Livewire::actingAs($user)
            ->test(DocumentUploadPanel::class, ['applicationUlid' => $application->ulid])
            ->call('selectDocument', $docSlot->ulid)
            ->set('pendingFile', UploadedFile::fake()->create('virus.gif', 100, 'image/gif'))
            ->assertSet('uploadError', fn ($error) => $error !== null);

        $this->assertDatabaseCount('document_versions', 0);
    }

    public function test_upload_rejects_unauthorized_applicant(): void
    {
        Storage::fake('documents');

        [$user, $application, $docSlot] = $this->makeApplicantWithDraftAndSlot();
        $otherUser = User::factory()->create();

        Livewire::actingAs($otherUser)
            ->test(DocumentUploadPanel::class, ['applicationUlid' => $application->ulid])
            ->call('selectDocument', $docSlot->ulid)
            ->assertForbidden();
    }

    public function test_upload_starts_scan_polling(): void
    {
        Storage::fake('documents');
        Queue::fake();

        [$user, $application, $docSlot] = $this->makeApplicantWithDraftAndSlot();

        Livewire::actingAs($user)
            ->test(DocumentUploadPanel::class, ['applicationUlid' => $application->ulid])
            ->call('selectDocument', $docSlot->ulid)
            ->set('pendingFile', UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'))
            ->assertSet('pollingActive', true);
    }

    private function makeApplicantWithDraftAndSlot(): array
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

        $docType = DocumentType::factory()->create([
            'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
            'max_size_kb' => 5120,
        ]);

        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Pending,
        ]);

        return [$user, $application, $docSlot];
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        Storage::fake('documents');
    }

    public function test_officer_can_preview_clean_document(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        [$document, $version] = $this->makeDocumentWithVersion(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake-content');

        $url = URL::temporarySignedRoute(
            'officer.documents.preview',
            now()->addMinutes(15),
            ['version' => $version->ulid],
        );

        $response = $this->actingAs($officer)->get($url);

        $response->assertOk();
    }

    public function test_applicant_cannot_preview_document(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        [$document, $version] = $this->makeDocumentWithVersion(ScanStatus::Clean);

        $url = URL::temporarySignedRoute(
            'officer.documents.preview',
            now()->addMinutes(15),
            ['version' => $version->ulid],
        );

        $this->actingAs($applicant)->get($url)->assertForbidden();
    }

    public function test_cannot_preview_infected_document(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        [$document, $version] = $this->makeDocumentWithVersion(ScanStatus::Infected);

        $url = URL::temporarySignedRoute(
            'officer.documents.preview',
            now()->addMinutes(15),
            ['version' => $version->ulid],
        );

        $this->actingAs($officer)->get($url)->assertForbidden();
    }

    public function test_preview_writes_audit_log(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        [$document, $version] = $this->makeDocumentWithVersion(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake-content');

        $url = URL::temporarySignedRoute(
            'officer.documents.preview',
            now()->addMinutes(15),
            ['version' => $version->ulid],
        );

        $this->actingAs($officer)->get($url);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.previewed',
            'subject_type' => DocumentVersion::class,
            'subject_id' => $version->ulid,
            'user_id' => $officer->id,
        ]);
    }

    private function makeDocumentWithVersion(ScanStatus $scanStatus): array
    {
        $application = VisaApplication::factory()->create();
        $docType = DocumentType::factory()->create();

        $document = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        $version = DocumentVersion::create([
            'application_document_id' => $document->ulid,
            'storage_path' => 'test/document.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => sha1('test'),
            'scan_status' => $scanStatus,
            'scan_completed_at' => now(),
            'uploaded_by' => User::factory()->create()->id,
            'created_at' => now(),
        ]);

        $document->update(['current_version_id' => $version->ulid]);

        return [$document, $version];
    }
}

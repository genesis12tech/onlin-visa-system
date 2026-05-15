<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\UploadDocumentVersion;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Jobs\ScanDocumentVersionJob;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanDocumentVersionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_upload_dispatches_scan_job_to_documents_queue(): void
    {
        Queue::fake();

        [$docSlot, $uploader] = $this->makeDocumentSlot();
        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        Queue::assertPushedOn('documents', ScanDocumentVersionJob::class);
    }

    public function test_scan_job_marks_version_as_clean(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'sha256_checksum' => hash('sha256', 'test'),
            'scan_status' => ScanStatus::Pending,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ScanDocumentVersionJob($version->ulid))->handle();

        $this->assertEquals(ScanStatus::Clean, $version->fresh()->scan_status);
        $this->assertNotNull($version->fresh()->scan_completed_at);
    }

    public function test_scan_job_is_idempotent_if_already_scanned(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $originalTime = now()->subMinute();

        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test2.pdf',
            'original_filename' => 'test2.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'sha256_checksum' => hash('sha256', 'test2'),
            'scan_status' => ScanStatus::Clean,
            'scan_completed_at' => $originalTime,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ScanDocumentVersionJob($version->ulid))->handle();

        // scan_completed_at should not change if already clean
        $this->assertEquals(
            $originalTime->toDateTimeString(),
            $version->fresh()->scan_completed_at->toDateTimeString()
        );
    }

    private function makeDocumentSlot(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'SC', 'iso3' => 'SCC']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'SCAN_30', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Scan', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'S12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Scan St', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-SCAN-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);
        $docType = DocumentType::factory()->create(['accepted_mime_types' => ['application/pdf'], 'max_size_kb' => 5120]);
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Pending,
        ]);
        $uploader = User::factory()->create();

        return [$docSlot, $uploader];
    }
}

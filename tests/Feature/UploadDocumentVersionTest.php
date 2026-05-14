<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\UploadDocumentVersion;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadDocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_upload_creates_document_version_with_correct_checksum(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 fake content');
        $expectedChecksum = hash('sha256', '%PDF-1.4 fake content');

        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertEquals($expectedChecksum, $version->sha256_checksum);
        $this->assertEquals('passport.pdf', $version->original_filename);
        $this->assertEquals(ScanStatus::Pending, $version->scan_status);
        $this->assertEquals($uploader->id, $version->uploaded_by);
    }

    public function test_upload_stores_file_to_documents_disk(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        Storage::disk('documents')->assertExists($version->storage_path);
    }

    public function test_upload_updates_application_document_status_to_uploaded(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Uploaded->value,
            'current_version_id' => $version->ulid,
        ]);
    }

    public function test_upload_sets_current_version_id_on_application_document(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertEquals($version->ulid, $docSlot->fresh()->current_version_id);
    }

    public function test_upload_writes_audit_log_entry(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $uploader->id,
        ]);
    }

    public function test_upload_creates_new_version_for_rejected_document(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();
        $docSlot->update(['status' => DocumentStatus::Rejected, 'rejection_reason' => 'Too blurry']);

        $file = UploadedFile::fake()->create('passport_v2.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseCount('document_versions', 1);
        $this->assertEquals(DocumentStatus::Uploaded, $docSlot->fresh()->status);
        $this->assertEquals($version->ulid, $docSlot->fresh()->current_version_id);
    }

    public function test_upload_fails_if_mime_type_not_accepted(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('image.gif', 100, 'image/gif');

        $this->expectException(ValidationException::class);

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);
    }

    public function test_upload_fails_if_file_exceeds_max_size(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        // max_size_kb is 5120 (5MB). Create a 6MB file.
        $file = UploadedFile::fake()->create('huge.pdf', 6144, 'application/pdf');

        $this->expectException(ValidationException::class);

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);
    }

    private function makeDocumentSlot(): array
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
        $uploader = User::factory()->create();

        return [$docSlot, $uploader];
    }
}

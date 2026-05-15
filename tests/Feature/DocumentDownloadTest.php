<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    public function test_download_returns_403_when_scan_status_is_pending(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Pending);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertForbidden();
    }

    public function test_download_returns_403_when_scan_status_is_infected(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Infected);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertForbidden();
    }

    public function test_download_succeeds_when_scan_status_is_clean(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertSuccessful();
    }

    public function test_download_writes_audit_log_on_success(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'user_id' => $officer->id,
        ]);
    }

    public function test_download_returns_redirect_for_unauthenticated_user(): void
    {
        [$version] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->get($url)->assertRedirect('/admin/login');
    }

    public function test_download_returns_403_for_unauthorized_user(): void
    {
        [$version] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $otherUser = User::factory()->create(); // no role, different applicant

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($otherUser)->get($url)->assertForbidden();
    }

    private function makeVersionWithOfficer(ScanStatus $scanStatus): array
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
        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test-file.pdf',
            'original_filename' => 'passport.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'fake content'),
            'scan_status' => $scanStatus,
            'uploaded_by' => $user->id,
            'created_at' => now(),
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        return [$version, $officer];
    }
}

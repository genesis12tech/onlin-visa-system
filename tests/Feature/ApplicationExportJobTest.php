<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ApplicationExport;
use App\Jobs\ExportApplicationsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationExportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_application_export_record(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create();

        (new ExportApplicationsJob([], $requester->id))->handle();

        $this->assertDatabaseHas('application_exports', [
            'requested_by' => $requester->id,
            'status' => ExportStatus::Ready->value,
        ]);
    }

    public function test_job_writes_csv_to_private_local_disk(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create();
        $type = $this->makeVisaType();
        $this->makeApplication($type);

        (new ExportApplicationsJob([], $requester->id))->handle();

        $export = ApplicationExport::first();

        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_job_sets_correct_row_count(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create();
        $type = $this->makeVisaType();
        $this->makeApplication($type);
        $this->makeApplication($type);

        (new ExportApplicationsJob([], $requester->id))->handle();

        $this->assertSame(2, ApplicationExport::first()->row_count);
    }

    public function test_job_csv_headers_do_not_contain_sensitive_fields(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create();
        $type = $this->makeVisaType();
        $this->makeApplication($type);

        (new ExportApplicationsJob([], $requester->id))->handle();

        $export = ApplicationExport::first();
        $contents = Storage::disk('local')->get($export->file_path);

        $this->assertStringNotContainsStringIgnoringCase('passport', $contents);
        $this->assertStringNotContainsString('date_of_birth', $contents);
        $this->assertStringContainsString('tracking_number', $contents);
    }

    private function makeVisaType(): VisaType
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);

        return VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOUR30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
    }

    private function makeApplication(VisaType $type): VisaApplication
    {
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $country = Country::firstOrCreate(['iso2' => 'TE'], ['name' => 'Test', 'iso3' => 'TST']);
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'B'.rand(10000000, 99999999),
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-TEST-'.rand(1000, 9999),
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now()->toDateTimeString(),
        ]);
    }
}

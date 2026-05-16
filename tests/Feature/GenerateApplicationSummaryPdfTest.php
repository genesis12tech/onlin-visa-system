<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateApplicationSummaryPdf;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateApplicationSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_application_dispatches_summary_pdf_job(): void
    {
        Queue::fake();

        ['application' => $application] = $this->makeContext();
        $actor = User::factory()->create();

        (new SubmitApplication)->execute($application, $actor);

        Queue::assertPushedOn('pdfs', GenerateApplicationSummaryPdf::class);
    }

    public function test_summary_pdf_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        ['application' => $application] = $this->makeContext();
        $application->update(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()]);

        (new GenerateApplicationSummaryPdf($application->ulid))->handle();

        $this->assertNotNull($application->fresh()->summary_pdf_path);
        Storage::disk('documents')->assertExists($application->fresh()->summary_pdf_path);
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'SU', 'iso3' => 'SUM']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'SUM_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Summary', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'SU345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test St', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-SUM-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        return compact('application');
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GenerateDecisionLetterPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_approve_action_dispatches_decision_letter_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $application = $this->makeSubmittedApplication();

        (new ApproveApplication)->execute($application, $actor);

        Queue::assertPushedOn('pdfs', GenerateDecisionLetterPdf::class);
    }

    public function test_reject_action_dispatches_decision_letter_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Missing documents');

        Queue::assertPushedOn('pdfs', GenerateDecisionLetterPdf::class);
    }

    public function test_decision_letter_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        $application = $this->makeSubmittedApplication();
        $application->update(['status' => ApplicationStatus::Approved, 'decision_at' => now()]);

        (new GenerateDecisionLetterPdf($application->ulid))->handle();

        $this->assertNotNull($application->fresh()->decision_letter_pdf_path);
        Storage::disk('documents')->assertExists($application->fresh()->decision_letter_pdf_path);
    }

    private function makeSubmittedApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'DL', 'iso3' => 'DLT']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'DL_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Decision', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'DL345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test St', 'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-DL-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}

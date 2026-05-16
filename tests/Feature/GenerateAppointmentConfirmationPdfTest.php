<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateAppointmentConfirmationPdf;
use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateAppointmentConfirmationPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_appointment_dispatches_confirmation_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::now()->addDays(7));

        Queue::assertPushedOn('pdfs', GenerateAppointmentConfirmationPdf::class);
    }

    public function test_confirmation_pdf_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        $application = $this->makeSubmittedApplication();
        $actor = User::factory()->create();
        $appointment = ApplicationAppointment::create([
            'visa_application_id' => $application->ulid,
            'created_by' => $actor->id,
            'appointment_at' => now()->addDays(7),
            'location' => 'Main Office',
            'instructions' => 'Bring originals.',
        ]);

        (new GenerateAppointmentConfirmationPdf($appointment->ulid))->handle();

        $this->assertNotNull($appointment->fresh()->confirmation_pdf_path);
        Storage::disk('documents')->assertExists($appointment->fresh()->confirmation_pdf_path);
    }

    private function makeSubmittedApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'AC', 'iso3' => 'ACT']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'AC_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Appt', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'AC345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Appt St', 'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-AC-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}

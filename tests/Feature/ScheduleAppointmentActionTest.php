<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateAppointmentConfirmationPdf;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ScheduleAppointmentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_action_creates_appointment_record(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();
        $appointmentAt = Carbon::parse('2026-06-01 10:00:00');

        (new ScheduleAppointment)->execute($application, $actor, $appointmentAt, 'Main Office', 'Bring all originals.');

        $this->assertDatabaseHas('application_appointments', [
            'visa_application_id' => $application->ulid,
            'created_by' => $actor->id,
            'location' => 'Main Office',
            'instructions' => 'Bring all originals.',
        ]);
    }

    public function test_action_records_status_history(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::parse('2026-06-01 10:00:00'));

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_action_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::parse('2026-06-01 10:00:00'));

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, AppointmentScheduledNotification::class);
    }

    public function test_action_dispatches_pdf_job(): void
    {
        Bus::fake();

        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::parse('2026-06-01 10:00:00'));

        Bus::assertDispatched(GenerateAppointmentConfirmationPdf::class);
    }

    private function makeApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }
}

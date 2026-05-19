<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\ApplicationRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RejectApplicationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_reject_action_transitions_status_to_rejected(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Incomplete documents');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Rejected->value,
        ]);
    }

    public function test_reject_action_stores_rejection_reason(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Incomplete documents');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'decision_reason' => 'Incomplete documents',
        ]);
    }

    public function test_reject_action_records_status_history_with_reason(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Missing passport copy');

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'to_status' => ApplicationStatus::Rejected->value,
            'actor_id' => $actor->id,
            'reason' => 'Missing passport copy',
        ]);
    }

    public function test_reject_action_sends_notification_to_applicant(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Incomplete documents');

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, ApplicationRejectedNotification::class);
    }

    private function makeSubmittedApplication(): VisaApplication
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
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}

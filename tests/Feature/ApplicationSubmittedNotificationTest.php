<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicationSubmittedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_dispatches_submitted_notification_to_applicant(): void
    {
        Notification::fake();

        ['application' => $application, 'applicantUser' => $applicantUser] = $this->makeContext();

        (new SubmitApplication)->execute($application, User::factory()->create());

        Notification::assertSentTo($applicantUser, ApplicationSubmittedNotification::class);
    }

    public function test_submitted_notification_is_on_high_queue(): void
    {
        ['application' => $application] = $this->makeContext();

        $notification = new ApplicationSubmittedNotification($application);

        $this->assertEquals('high', $notification->queue);
    }

    public function test_submitted_notification_database_payload_contains_tracking_number(): void
    {
        Notification::fake();

        ['application' => $application, 'applicantUser' => $applicantUser] = $this->makeContext();

        (new SubmitApplication)->execute($application, User::factory()->create());

        Notification::assertSentTo(
            $applicantUser,
            ApplicationSubmittedNotification::class,
            function (ApplicationSubmittedNotification $n) use ($applicantUser, $application) {
                $data = $n->toArray($applicantUser);

                return $data['type'] === 'application_submitted'
                    && $data['tracking_number'] === $application->tracking_number;
            }
        );
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'TOURIST_30', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id,
            'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-SUBMIT-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        return compact('application', 'applicantUser');
    }
}

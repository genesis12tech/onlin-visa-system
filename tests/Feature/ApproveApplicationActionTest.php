<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApproveApplicationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_approve_action_transitions_status_to_approved(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $application = $this->makeSubmittedApplication();

        (new ApproveApplication)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Approved->value,
        ]);
    }

    public function test_approve_action_records_status_history(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new ApproveApplication)->execute($application, $actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'to_status' => ApplicationStatus::Approved->value,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_approve_action_sets_decision_at(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new ApproveApplication)->execute($application, $actor);

        $this->assertNotNull($application->fresh()->decision_at);
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

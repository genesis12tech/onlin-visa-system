<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private ApplicantProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->applicant = User::factory()->create(['email_verified_at' => now()]);
        $this->applicant->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->applicant->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    public function test_applicant_can_create_an_application(): void
    {
        $this->assertTrue($this->applicant->can('create', VisaApplication::class));
    }

    public function test_applicant_can_view_own_application(): void
    {
        $app = VisaApplication::factory()->create(['applicant_profile_id' => $this->profile->ulid]);

        $this->assertTrue($this->applicant->can('view', $app));
    }

    public function test_applicant_cannot_view_another_applicants_application(): void
    {
        $otherApp = VisaApplication::factory()->create();

        $this->assertFalse($this->applicant->can('view', $otherApp));
    }

    public function test_applicant_can_update_own_draft_application(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $this->assertTrue($this->applicant->can('update', $app));
    }

    public function test_applicant_cannot_update_submitted_application(): void
    {
        $app = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);

        $this->assertFalse($this->applicant->can('update', $app));
    }

    public function test_applicant_can_submit_own_draft(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $this->assertTrue($this->applicant->can('submit', $app));
    }

    public function test_applicant_can_withdraw_draft_or_submitted_application(): void
    {
        $draft = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);
        $submitted = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);

        $this->assertTrue($this->applicant->can('withdraw', $draft));
        $this->assertTrue($this->applicant->can('withdraw', $submitted));
    }

    public function test_applicant_can_withdraw_at_any_pre_decision_stage(): void
    {
        $preDecisionStatuses = [
            ApplicationStatus::PaymentPending,
            ApplicationStatus::PaymentCompleted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::AdditionalInfoRequested,
        ];

        foreach ($preDecisionStatuses as $status) {
            $app = VisaApplication::factory()->create([
                'applicant_profile_id' => $this->profile->ulid,
                'status' => $status,
            ]);

            $this->assertTrue(
                $this->applicant->can('withdraw', $app),
                "Expected applicant to be able to withdraw when status is {$status->value}",
            );
        }
    }

    public function test_applicant_cannot_withdraw_post_decision_application(): void
    {
        foreach ([ApplicationStatus::Approved, ApplicationStatus::Rejected] as $status) {
            $app = VisaApplication::factory()->create([
                'applicant_profile_id' => $this->profile->ulid,
                'status' => $status,
            ]);

            $this->assertFalse(
                $this->applicant->can('withdraw', $app),
                "Expected applicant to be unable to withdraw when status is {$status->value}",
            );
        }
    }

    public function test_applicant_cannot_withdraw_approved_application(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Approved,
        ]);

        $this->assertFalse($this->applicant->can('withdraw', $app));
    }

    public function test_unauthenticated_user_cannot_create_application(): void
    {
        $guest = User::factory()->create(); // no role
        $this->assertFalse($guest->can('create', VisaApplication::class));
    }
}

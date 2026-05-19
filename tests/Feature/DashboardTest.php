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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_applicant_without_profile_redirected_to_setup(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('profile.setup'));
    }

    public function test_applicant_with_profile_can_view_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $country = Country::factory()->create();
        $user->assignRole('applicant');

        ApplicantProfile::factory()->create([
            'user_id' => $user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_non_applicant_role_user_can_view_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        // No role — EnsureProfileComplete only redirects for 'applicant' role users

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_action_required_banner_shown_when_application_needs_attention(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $profile = ApplicantProfile::factory()->create([
            'user_id' => $user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        VisaApplication::factory()->create([
            'applicant_profile_id' => $profile->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Needs your attention');
    }

    public function test_action_required_banner_not_shown_when_no_attention_needed(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $profile = ApplicantProfile::factory()->create([
            'user_id' => $user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $profile->ulid,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Needs your attention');
    }
}

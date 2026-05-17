<?php

namespace Tests\Feature;

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

class CreateApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private VisaType $visaType;

    private FormTemplate $formTemplate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $this->visaType = VisaType::factory()->create(['country_id' => $country->id]);
        $this->formTemplate = FormTemplate::factory()->create(['visa_type_id' => $this->visaType->ulid]);
    }

    public function test_start_page_shows_active_visa_types(): void
    {
        $this->actingAs($this->user)
            ->get(route('applications.start'))
            ->assertOk()
            ->assertSee($this->visaType->name);
    }

    public function test_inactive_visa_types_are_not_shown(): void
    {
        $inactive = VisaType::factory()->inactive()->create(['country_id' => Country::factory()->create()->id]);

        $this->actingAs($this->user)
            ->get(route('applications.start'))
            ->assertOk()
            ->assertDontSee($inactive->name);
    }

    public function test_storing_an_application_creates_draft_and_redirects_to_wizard(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $this->visaType->ulid]);

        $application = VisaApplication::where('applicant_profile_id', $this->profile->ulid)->first();

        $this->assertNotNull($application);
        $this->assertEquals(ApplicationStatus::Draft, $application->status);
        $response->assertRedirect(route('applications.wizard', $application->tracking_number));
    }

    public function test_storing_with_no_form_template_returns_404(): void
    {
        $typeWithNoTemplate = VisaType::factory()->create([
            'country_id' => Country::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $typeWithNoTemplate->ulid])
            ->assertStatus(404);
    }

    public function test_second_store_for_same_visa_type_redirects_to_existing_draft(): void
    {
        $existing = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id' => $this->visaType->ulid,
            'form_template_id' => $this->formTemplate->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $this->visaType->ulid])
            ->assertRedirect(route('applications.wizard', $existing->tracking_number));

        $this->assertDatabaseCount('visa_applications', 1);
    }

    public function test_applicant_cannot_access_another_applicants_wizard(): void
    {
        $other = VisaApplication::factory()->create(); // different profile

        $this->actingAs($this->user)
            ->get(route('applications.wizard', $other->tracking_number))
            ->assertForbidden();
    }

    public function test_withdraw_sets_status_to_withdrawn(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.withdraw', $application->tracking_number))
            ->assertRedirect(route('dashboard'));

        $this->assertEquals(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    public function test_posting_inactive_visa_type_is_rejected(): void
    {
        $inactive = VisaType::factory()->inactive()->create(['country_id' => Country::factory()->create()->id]);

        $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $inactive->ulid])
            ->assertRedirect()
            ->assertSessionHasErrors('visa_type_ulid');
    }

    public function test_another_applicant_cannot_withdraw_someones_application(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $country = Country::factory()->create();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $this->actingAs($otherUser)
            ->post(route('applications.withdraw', $application->tracking_number))
            ->assertForbidden();

        $this->assertEquals(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_guest_cannot_start_application(): void
    {
        $this->get(route('applications.start'))->assertRedirect(route('login'));
    }
}

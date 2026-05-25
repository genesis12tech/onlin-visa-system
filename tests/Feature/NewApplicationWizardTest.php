<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\ServiceLocation;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\ApplicationWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Testing\TestableLivewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NewApplicationWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private Country $country;

    private VisaType $visaType;

    private FormTemplate $formTemplate;

    private ServiceLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $this->country = Country::factory()->create(['is_active' => true]);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $this->country->id,
            'country_of_residence_id' => $this->country->id,
        ]);
        $this->visaType = VisaType::factory()->create(['country_id' => $this->country->id, 'is_active' => true]);
        $this->formTemplate = FormTemplate::factory()->create(['visa_type_id' => $this->visaType->ulid, 'is_active' => true]);
        $this->location = ServiceLocation::create([
            'name' => 'Main International Airport',
            'address' => '1 Airport Rd',
            'city' => 'Capital City',
            'country_id' => $this->country->id,
            'is_active' => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // Step 1 — Visa type selection
    // -----------------------------------------------------------------------

    public function test_wizard_loads_on_step_1_for_authenticated_applicant(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->assertOk()
            ->assertSet('currentStep', 1)
            ->assertSee($this->visaType->name);
    }

    public function test_wizard_pre_fills_personal_info_from_applicant_profile(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class);

        $component->assertSet('firstName', $this->profile->first_name);
        $component->assertSet('passportNumber', $this->profile->passport_number);
    }

    public function test_step_1_requires_visa_type_selection(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->set('selectedVisaTypeUlid', '')
            ->call('nextStep')
            ->assertHasErrors(['selectedVisaTypeUlid'])
            ->assertSet('currentStep', 1);
    }

    public function test_step_1_rejects_nonexistent_visa_type(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->set('selectedVisaTypeUlid', 'nonexistent-ulid')
            ->call('nextStep')
            ->assertHasErrors(['selectedVisaTypeUlid'])
            ->assertSet('currentStep', 1);
    }

    public function test_step_1_creates_draft_application_and_advances(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->set('selectedVisaTypeUlid', $this->visaType->ulid)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2);

        $this->assertDatabaseHas('visa_applications', [
            'visa_type_id' => $this->visaType->ulid,
            'status' => 'draft',
        ]);
    }

    public function test_step_1_tracking_number_matches_format(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->set('selectedVisaTypeUlid', $this->visaType->ulid)
            ->call('nextStep');

        $applicationUlid = $component->get('applicationUlid');
        $application = VisaApplication::find($applicationUlid);

        $this->assertNotNull($application);
        $this->assertMatchesRegularExpression('/^VA-\d{4}-[A-Z0-9]{6}$/', $application->tracking_number);
    }

    public function test_step_1_resumes_existing_draft_instead_of_creating_new(): void
    {
        // Pre-create a draft application for this profile and visa type
        VisaApplication::create([
            'tracking_number' => 'VA-2026-RESUME',
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id' => $this->visaType->ulid,
            'form_template_id' => $this->formTemplate->ulid,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->set('selectedVisaTypeUlid', $this->visaType->ulid)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2);

        $this->assertDatabaseCount('visa_applications', 1);
    }

    // -----------------------------------------------------------------------
    // Step 2 — Personal information
    // -----------------------------------------------------------------------

    public function test_step_2_requires_all_personal_info_fields(): void
    {
        $component = $this->advanceToStep(2);

        $component
            ->set('firstName', '')
            ->set('lastName', '')
            ->set('dateOfBirth', '')
            ->set('gender', '')
            ->set('nationalityId', '')
            ->set('passportNumber', '')
            ->set('passportExpiry', '')
            ->set('phone', '')
            ->set('email', '')
            ->call('nextStep')
            ->assertHasErrors([
                'firstName',
                'lastName',
                'dateOfBirth',
                'gender',
                'nationalityId',
                'passportNumber',
                'passportExpiry',
                'phone',
                'email',
            ]);
    }

    public function test_step_2_rejects_applicant_under_18(): void
    {
        $component = $this->advanceToStep(2);

        $component
            ->set('dateOfBirth', now()->subYears(17)->format('Y-m-d'))
            ->call('nextStep')
            ->assertHasErrors(['dateOfBirth']);
    }

    public function test_step_2_rejects_passport_expiring_within_6_months(): void
    {
        $component = $this->advanceToStep(2);

        $component
            ->set('passportExpiry', now()->addMonths(5)->format('Y-m-d'))
            ->call('nextStep')
            ->assertHasErrors(['passportExpiry']);
    }

    public function test_step_2_valid_data_saves_answers_and_advances(): void
    {
        $component = $this->advanceToStep(2);

        $component
            ->set('firstName', $this->profile->first_name)
            ->set('lastName', $this->profile->last_name)
            ->set('dateOfBirth', now()->subYears(30)->format('Y-m-d'))
            ->set('gender', 'male')
            ->set('nationalityId', (string) $this->country->id)
            ->set('passportNumber', 'AB123456')
            ->set('passportExpiry', now()->addYears(3)->format('Y-m-d'))
            ->set('phone', '+1 555 000 1234')
            ->set('email', 'test@example.com')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 3);

        $this->assertDatabaseHas('application_answers', [
            'field_key' => 'personal_info.first_name',
        ]);
    }

    // -----------------------------------------------------------------------
    // Step 3 — Travel details
    // -----------------------------------------------------------------------

    public function test_step_3_requires_all_travel_fields(): void
    {
        $component = $this->advanceToStep(3);

        $component
            ->set('arrivalDate', '')
            ->set('departureDate', '')
            ->set('portOfEntry', '')
            ->set('accommodation', '')
            ->set('purpose', '')
            ->set('previouslyRefused', '')
            ->call('nextStep')
            ->assertHasErrors([
                'arrivalDate',
                'departureDate',
                'portOfEntry',
                'accommodation',
                'purpose',
                'previouslyRefused',
            ]);
    }

    public function test_step_3_rejects_departure_before_arrival(): void
    {
        $component = $this->advanceToStep(3);

        $component
            ->set('arrivalDate', now()->addDays(10)->format('Y-m-d'))
            ->set('departureDate', now()->addDays(5)->format('Y-m-d'))
            ->call('nextStep')
            ->assertHasErrors(['departureDate']);
    }

    public function test_step_3_rejects_purpose_under_20_characters(): void
    {
        $component = $this->advanceToStep(3);

        $component
            ->set('purpose', 'Short.')
            ->call('nextStep')
            ->assertHasErrors(['purpose']);
    }

    public function test_step_3_valid_data_saves_answers_and_advances(): void
    {
        $component = $this->advanceToStep(3);

        $component
            ->set('arrivalDate', now()->addDays(30)->format('Y-m-d'))
            ->set('departureDate', now()->addDays(40)->format('Y-m-d'))
            ->set('portOfEntry', $this->location->name)
            ->set('accommodation', 'Grand Hotel, 5th Ave')
            ->set('purpose', 'Tourism and cultural exploration of the region.')
            ->set('previouslyRefused', 'no')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);

        $this->assertDatabaseHas('application_answers', [
            'field_key' => 'travel_details.arrival_date',
        ]);
    }

    // -----------------------------------------------------------------------
    // Step 4 — Review / document upload (no validation, just advances)
    // -----------------------------------------------------------------------

    public function test_step_4_can_advance_to_review(): void
    {
        $this->advanceToStep(4)
            ->call('nextStep')
            ->assertSet('currentStep', 5);
    }

    // -----------------------------------------------------------------------
    // Step 5 — Declaration & submit
    // -----------------------------------------------------------------------

    public function test_step_5_requires_declaration_checkbox(): void
    {
        $component = $this->advanceToStep(5);

        $component
            ->set('declarationAccepted', false)
            ->call('submit')
            ->assertHasErrors(['declarationAccepted'])
            ->assertSet('submitted', false);
    }

    public function test_step_5_submit_creates_snapshot_and_shows_success(): void
    {
        $component = $this->advanceToStep(5);

        $applicationUlid = $component->get('applicationUlid');

        $component
            ->set('declarationAccepted', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $applicationUlid,
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('application_snapshots', [
            'visa_application_id' => $applicationUlid,
        ]);
    }

    // -----------------------------------------------------------------------
    // Navigation — going back
    // -----------------------------------------------------------------------

    public function test_back_from_step_2_returns_to_step_1_preserving_visa_type(): void
    {
        $component = $this->advanceToStep(2);

        $component
            ->call('previousStep')
            ->assertSet('currentStep', 1)
            ->assertSet('selectedVisaTypeUlid', $this->visaType->ulid);
    }

    public function test_back_from_step_3_returns_to_step_2_preserving_personal_info(): void
    {
        $component = $this->advanceToStep(3);

        $component
            ->call('previousStep')
            ->assertSet('currentStep', 2)
            ->assertSet('firstName', $this->profile->first_name);
    }

    public function test_previous_step_does_nothing_on_step_1(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class)
            ->assertSet('currentStep', 1)
            ->call('previousStep')
            ->assertSet('currentStep', 1);
    }

    // -----------------------------------------------------------------------
    // Private helper
    // -----------------------------------------------------------------------

    private function advanceToStep(int $targetStep): TestableLivewire
    {
        $component = Livewire::actingAs($this->user)->test(ApplicationWizard::class);

        if ($targetStep <= 1) {
            return $component;
        }

        // → step 2
        $component->set('selectedVisaTypeUlid', $this->visaType->ulid)->call('nextStep');

        if ($targetStep <= 2) {
            return $component;
        }

        // → step 3
        $component
            ->set('firstName', $this->profile->first_name)
            ->set('lastName', $this->profile->last_name)
            ->set('dateOfBirth', now()->subYears(30)->format('Y-m-d'))
            ->set('gender', 'male')
            ->set('nationalityId', (string) $this->country->id)
            ->set('passportNumber', 'AB123456')
            ->set('passportExpiry', now()->addYears(3)->format('Y-m-d'))
            ->set('phone', '+1 555 000 1234')
            ->set('email', 'test@example.com')
            ->call('nextStep');

        if ($targetStep <= 3) {
            return $component;
        }

        // → step 4
        $component
            ->set('arrivalDate', now()->addDays(30)->format('Y-m-d'))
            ->set('departureDate', now()->addDays(40)->format('Y-m-d'))
            ->set('portOfEntry', $this->location->name)
            ->set('accommodation', 'Grand Hotel, 5th Ave')
            ->set('purpose', 'Tourism and cultural exploration of the region.')
            ->set('previouslyRefused', 'no')
            ->call('nextStep');

        if ($targetStep <= 4) {
            return $component;
        }

        // → step 5
        $component->call('nextStep');

        return $component;
    }
}

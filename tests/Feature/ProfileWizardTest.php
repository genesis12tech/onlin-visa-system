<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Profile\SetupWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfileWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->country = Country::factory()->create();
    }

    public function test_profile_setup_page_requires_auth(): void
    {
        $this->get(route('profile.setup'))->assertRedirect(route('login'));
    }

    public function test_profile_setup_page_requires_verified_email(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('profile.setup'))->assertRedirect(route('verification.notice'));
    }

    public function test_step_1_validates_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->call('nextStep')
            ->assertHasErrors(['firstName', 'lastName', 'dateOfBirth', 'gender', 'nationalityId', 'countryOfResidenceId']);
    }

    public function test_step_1_advances_to_step_2_with_valid_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('dateOfBirth', '1990-01-15')
            ->set('gender', 'female')
            ->set('nationalityId', (string) $this->country->id)
            ->set('countryOfResidenceId', (string) $this->country->id)
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertHasNoErrors();
    }

    public function test_completing_wizard_creates_profile(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('dateOfBirth', '1990-01-15')
            ->set('gender', 'female')
            ->set('nationalityId', (string) $this->country->id)
            ->set('countryOfResidenceId', (string) $this->country->id)
            ->call('nextStep')
            ->set('passportNumber', 'AB1234567')
            ->set('passportExpiryDate', '2030-01-01')
            ->set('phone', '+44 7911 000000')
            ->set('addressLine1', '10 Downing Street')
            ->set('city', 'London')
            ->call('complete');

        $this->assertDatabaseHas('applicant_profiles', [
            'user_id' => $this->user->id,
            'first_name' => 'Jane',
            'city' => 'London',
        ]);
    }

    public function test_wizard_pre_fills_existing_profile(): void
    {
        ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'first_name' => 'Existing',
            'nationality_id' => $this->country->id,
            'country_of_residence_id' => $this->country->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->assertSet('firstName', 'Existing');
    }
}

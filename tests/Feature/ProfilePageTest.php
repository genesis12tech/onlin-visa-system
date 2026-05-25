<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Profile\ProfilePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('CurrentPassword1!'),
        ]);
        $this->user->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    public function test_profile_page_requires_authentication(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_authenticated_applicant_can_access_profile_page(): void
    {
        $this->actingAs($this->user)->get(route('profile'))->assertOk();
    }

    public function test_profile_page_shows_existing_profile_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->assertSet('firstName', $this->profile->first_name)
            ->assertSet('lastName', $this->profile->last_name);
    }

    public function test_save_profile_updates_name_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('firstName', 'Updated')
            ->set('lastName', 'Name')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('applicant_profiles', [
            'user_id' => $this->user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    public function test_save_profile_validates_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('firstName', '')
            ->call('saveProfile')
            ->assertHasErrors(['firstName' => 'required']);
    }

    public function test_change_password_updates_password_when_current_is_correct(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('currentPassword', 'CurrentPassword1!')
            ->set('newPassword', 'NewSecurePass1!')
            ->set('newPasswordConfirmation', 'NewSecurePass1!')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('NewSecurePass1!', $this->user->fresh()->password));
    }

    public function test_change_password_fails_when_current_password_wrong(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('currentPassword', 'WrongPassword!')
            ->set('newPassword', 'NewSecurePass1!')
            ->set('newPasswordConfirmation', 'NewSecurePass1!')
            ->call('changePassword')
            ->assertHasErrors(['currentPassword']);
    }

    public function test_change_password_fails_when_confirmation_mismatch(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('currentPassword', 'CurrentPassword1!')
            ->set('newPassword', 'NewSecurePass1!')
            ->set('newPasswordConfirmation', 'DifferentPassword1!')
            ->call('changePassword')
            ->assertHasErrors(['newPassword']);
    }

    public function test_notification_preferences_are_saved(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->set('notifyEmailApplicationUpdates', false)
            ->call('saveNotificationPreferences')
            ->assertHasNoErrors();

        $this->profile->refresh();
        $prefs = $this->profile->notification_preferences;
        $this->assertFalse($prefs['email_application_updates']);
    }

    public function test_verified_badge_shown_for_verified_email(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfilePage::class)
            ->assertSee('Verified');
    }
}

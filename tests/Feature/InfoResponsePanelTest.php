<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Applications\InfoResponsePanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InfoResponsePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_panel_renders_officer_message(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
            officerMessage: 'Please clarify your purpose of travel.',
        );

        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Please clarify your purpose of travel.');
    }

    public function test_panel_renders_deadline(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
            deadlineDate: '2026-06-01',
        );

        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->assertSee('2026-06-01');
    }

    public function test_panel_shows_only_unlocked_fields(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        // travel_purpose label = "Purpose of travel", intended_entry_date label = "Intended entry date"
        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Purpose of travel')
            ->assertDontSee('Intended entry date');
    }

    public function test_updating_a_field_saves_to_application_answers(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->set('answers.travel_details.travel_purpose', 'Tourism');

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
        ]);
    }

    public function test_panel_pre_populates_existing_answers(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Business',
        ]);

        $component = Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid]);

        $this->assertEquals('Business', $component->get('answers.travel_details.travel_purpose'));
    }

    public function test_submitting_response_redirects_to_application_wizard(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        ApplicationAnswer::create([
            'visa_application_id' => $application->ulid,
            'field_key' => 'travel_details.travel_purpose',
            'value' => 'Tourism',
        ]);

        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->call('submitResponse')
            ->assertRedirect(route('applications.wizard', $application->tracking_number));
    }

    public function test_submit_error_is_displayed_when_field_missing(): void
    {
        ['user' => $user, 'application' => $application] = $this->makeInfoRequestedApp(
            fieldsToUnlock: ['travel_details.travel_purpose'],
        );

        // Do NOT fill in the required field
        Livewire::actingAs($user)
            ->test(InfoResponsePanel::class, ['applicationUlid' => $application->ulid])
            ->call('submitResponse')
            ->assertHasErrors(['submit']);
    }

    /** @return array{user: User, application: VisaApplication} */
    private function makeInfoRequestedApp(
        array $fieldsToUnlock,
        string $officerMessage = 'Please update your details.',
        string $deadlineDate = '2026-06-01',
    ): array {
        $country = Country::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('applicant');
        $profile = ApplicantProfile::factory()->create([
            'user_id' => $user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
        $type = VisaType::factory()->create(['country_id' => $country->id]);
        $form = FormTemplate::factory()->create(['visa_type_id' => $type->ulid]);

        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested,
        ]);

        $officer = User::factory()->create();
        ApplicationNote::create([
            'visa_application_id' => $application->ulid,
            'author_id' => $officer->id,
            'body' => $officerMessage,
            'is_visible_to_applicant' => true,
            'metadata' => [
                'fields_to_unlock' => $fieldsToUnlock,
                'deadline_days' => 7,
                'deadline_date' => $deadlineDate,
            ],
        ]);

        return compact('user', 'application');
    }
}

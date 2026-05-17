<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Applications\ApplicationWizard;
use App\Livewire\Applications\DynamicFormSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private VisaApplication $application;

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

        $visaType = VisaType::factory()->create(['country_id' => $country->id]);
        FormTemplate::factory()->create(['visa_type_id' => $visaType->ulid]);

        $this->application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => FormTemplate::where('visa_type_id', $visaType->ulid)->value('ulid'),
        ]);
    }

    public function test_wizard_loads_for_applicant(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->assertOk()
            ->assertSet('onReviewStep', false)
            ->assertSet('currentSectionIndex', 0);
    }

    public function test_advance_increments_section_index(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->call('advance')
            ->assertSet('currentSectionIndex', 1);
    }

    public function test_advance_on_last_section_goes_to_documents_step(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('currentSectionIndex', 1) // last section (FormTemplateFactory has 2 sections)
            ->call('advance')
            ->assertSet('onDocumentsStep', true)
            ->assertSet('onReviewStep', false);
    }

    public function test_go_back_from_review_goes_to_documents_step(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('onReviewStep', true)
            ->call('goBack')
            ->assertSet('onReviewStep', false)
            ->assertSet('onDocumentsStep', true);
    }

    public function test_dynamic_form_section_saves_answers(): void
    {
        $section = $this->application->formTemplate->schema['sections'][0];

        Livewire::actingAs($this->user)
            ->test(DynamicFormSection::class, [
                'applicationUlid' => $this->application->ulid,
                'section' => $section,
                'savedAnswers' => [],
            ])
            ->set('answers.travel_purpose', 'Tourism')
            ->call('autoSave');

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $this->application->ulid,
            'field_key' => 'travel_details.travel_purpose',
        ]);
    }

    public function test_dynamic_form_section_respects_conditions(): void
    {
        $section = [
            'key' => 'travel_details',
            'title' => 'Travel Details',
            'fields' => [
                ['key' => 'travel_purpose', 'type' => 'select', 'label' => 'Purpose', 'required' => true, 'options' => ['Tourism', 'Business']],
                ['key' => 'employer_name', 'type' => 'text', 'label' => 'Employer', 'required' => false, 'condition' => ['field' => 'travel_purpose', 'equals' => 'Business']],
            ],
        ];

        $component = Livewire::actingAs($this->user)
            ->test(DynamicFormSection::class, [
                'applicationUlid' => $this->application->ulid,
                'section' => $section,
                'savedAnswers' => [],
            ]);

        // employer_name should be hidden when purpose is Tourism
        $component->set('answers.travel_purpose', 'Tourism');
        $this->assertFalse($component->get('visibleFields')['employer_name'] ?? true);

        // employer_name should be visible when purpose is Business
        $component->set('answers.travel_purpose', 'Business');
        $this->assertTrue($component->get('visibleFields')['employer_name'] ?? false);
    }

    public function test_advance_from_documents_step_goes_to_review(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('onDocumentsStep', true)
            ->call('advance')
            ->assertSet('onReviewStep', true)
            ->assertSet('onDocumentsStep', false);
    }

    public function test_go_back_from_documents_step_goes_to_last_form_section(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('currentSectionIndex', 1)
            ->set('onDocumentsStep', true)
            ->call('goBack')
            ->assertSet('onDocumentsStep', false)
            ->assertSet('onReviewStep', false)
            ->assertSet('currentSectionIndex', 1);
    }

    public function test_submitted_application_shows_tracking_number(): void
    {
        $submitted = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id' => $this->application->visa_type_id,
            'form_template_id' => $this->application->form_template_id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $submitted->tracking_number])
            ->assertSee($submitted->tracking_number);
    }

    public function test_submit_redirects_to_pay_page(): void
    {
        $this->mock(SubmitApplication::class)
            ->shouldReceive('execute')
            ->once();

        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('onReviewStep', true)
            ->call('submit')
            ->assertRedirect(route('applications.pay', $this->application->tracking_number));
    }
}

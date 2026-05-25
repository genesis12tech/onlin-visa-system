<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Tracking\PublicTrackingForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PublicTrackingFormTest extends TestCase
{
    use RefreshDatabase;

    private VisaApplication $application;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('track');

        $country = Country::factory()->create();
        $this->user = User::factory()->create(['email' => 'applicant@example.com']);
        $profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
        $this->application = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $profile->ulid,
        ]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $this->application->ulid,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'actor_id' => null,
            'public_label' => 'Application received',
            'created_at' => now(),
        ]);
    }

    public function test_renders_the_tracking_form(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->assertOk()
            ->assertSet('trackingNumber', '')
            ->assertSet('email', '')
            ->assertSet('result', null)
            ->assertSet('notFound', false);
    }

    public function test_valid_tracking_number_and_email_shows_application_status(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $this->application->tracking_number)
            ->set('email', 'applicant@example.com')
            ->call('submit')
            ->assertSet('notFound', false)
            ->assertSet('result.tracking_number', $this->application->tracking_number)
            ->assertSee('Application received');
    }

    public function test_unknown_tracking_number_shows_generic_error(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', 'VA-DOES-NOT-EXIST')
            ->set('email', 'any@example.com')
            ->call('submit')
            ->assertSet('notFound', true)
            ->assertSet('result', null);
    }

    public function test_wrong_email_shows_same_generic_error_as_not_found(): void
    {
        Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $this->application->tracking_number)
            ->set('email', 'wrong@example.com')
            ->call('submit')
            ->assertSet('notFound', true)
            ->assertSet('result', null);
    }

    public function test_does_not_show_internal_only_status_histories(): void
    {
        ApplicationStatusHistory::create([
            'visa_application_id' => $this->application->ulid,
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'actor_id' => null,
            'public_label' => null,
            'created_at' => now(),
        ]);

        $component = Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $this->application->tracking_number)
            ->set('email', 'applicant@example.com')
            ->call('submit');

        $result = $component->get('result');
        $this->assertNotNull($result);

        $histories = collect($result['histories']);
        $internalEntries = $histories->filter(fn ($h) => is_null($h['public_label'] ?? null));
        $this->assertEmpty($internalEntries);
    }

    public function test_tracking_page_is_publicly_accessible(): void
    {
        $this->get(route('track'))->assertOk();
    }

    public function test_result_includes_active_step_for_stepper(): void
    {
        $component = Livewire::test(PublicTrackingForm::class)
            ->set('trackingNumber', $this->application->tracking_number)
            ->set('email', 'applicant@example.com')
            ->call('submit');

        $result = $component->get('result');
        $this->assertArrayHasKey('active_step', $result);
        $this->assertIsInt($result['active_step']);
    }

    public function test_track_route_rate_limited_after_ten_requests(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('track'))->assertOk();
        }

        $this->get(route('track'))->assertStatus(429);
    }
}

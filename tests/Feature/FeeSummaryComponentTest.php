<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\VisaFee;
use App\Livewire\Payments\FeeSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stripe\StripeClient;
use Tests\TestCase;

class FeeSummaryComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private VisaApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        [$this->profile, $this->application] = $this->makeSubmittedApplicationWithFee();
    }

    public function test_fee_summary_mounts_for_submitted_application(): void
    {
        Livewire::actingAs($this->user)
            ->test(FeeSummary::class, ['tracking' => $this->application->tracking_number])
            ->assertOk()
            ->assertSet('priorityEnabled', false);
    }

    public function test_fee_summary_redirects_draft_application_to_wizard(): void
    {
        $draft = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id' => $this->application->visa_type_id,
            'form_template_id' => $this->application->form_template_id,
            'status' => ApplicationStatus::Draft,
        ]);

        Livewire::actingAs($this->user)
            ->test(FeeSummary::class, ['tracking' => $draft->tracking_number])
            ->assertRedirect(route('applications.wizard', $draft->tracking_number));
    }

    public function test_fee_summary_shows_standard_fee(): void
    {
        Livewire::actingAs($this->user)
            ->test(FeeSummary::class, ['tracking' => $this->application->tracking_number])
            ->assertSee('Application Fee');
    }

    public function test_priority_toggle_is_shown_when_priority_fee_exists(): void
    {
        VisaFee::create([
            'visa_type_id' => $this->application->visa_type_id,
            'name' => 'Priority Processing',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(FeeSummary::class, ['tracking' => $this->application->tracking_number])
            ->assertSee('Priority Processing');
    }

    public function test_initiate_payment_redirects_to_stripe(): void
    {
        $this->mockStripe('cs_test_fee123', 'https://checkout.stripe.com/pay/cs_test_fee123');

        Livewire::actingAs($this->user)
            ->test(FeeSummary::class, ['tracking' => $this->application->tracking_number])
            ->call('initiatePayment')
            ->assertRedirect('https://checkout.stripe.com/pay/cs_test_fee123');
    }

    public function test_unauthenticated_user_cannot_access_fee_summary(): void
    {
        $this->get(route('applications.pay', $this->application->tracking_number))
            ->assertRedirect(route('login'));
    }

    private function makeSubmittedApplicationWithFee(): array
    {
        $country = Country::factory()->create();
        $visaType = VisaType::factory()->create(['country_id' => $country->id]);
        FormTemplate::factory()->create(['visa_type_id' => $visaType->ulid]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 10000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $profile = ApplicantProfile::factory()->create(['user_id' => $this->user->id]);

        $application = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => FormTemplate::where('visa_type_id', $visaType->ulid)->value('ulid'),
        ]);

        return [$profile, $application];
    }

    private function mockStripe(string $sessionId, string $sessionUrl): void
    {
        $mockSession = new \stdClass;
        $mockSession->id = $sessionId;
        $mockSession->url = $sessionUrl;
        $mockSession->payment_intent = 'pi_test_mock';

        $mockSessions = Mockery::mock();
        $mockSessions->shouldReceive('create')->once()->andReturn($mockSession);

        $mockCheckout = new \stdClass;
        $mockCheckout->sessions = $mockSessions;

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockStripe->checkout = $mockCheckout;

        $this->app->instance(StripeClient::class, $mockStripe);
    }
}

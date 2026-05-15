<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\CreateCheckoutSession;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentItem;
use App\Domain\Payments\Models\VisaFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class CreateCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    private VisaApplication $application;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->application = $this->makeApplicationWithFee();
        $this->actor = User::factory()->create();
        $this->mockStripe('cs_test_mock123', 'https://checkout.stripe.com/pay/cs_test_mock123');
    }

    public function test_creates_payment_record_in_processing_status(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertDatabaseHas('payments', [
            'visa_application_id' => $this->application->ulid,
            'status' => 'processing',
            'provider' => 'stripe',
            'provider_checkout_session_id' => 'cs_test_mock123',
        ]);
    }

    public function test_creates_payment_items_as_fee_snapshot(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $payment = Payment::where('visa_application_id', $this->application->ulid)->firstOrFail();

        $this->assertEquals(1, PaymentItem::where('payment_id', $payment->ulid)->count());
        $this->assertDatabaseHas('payment_items', [
            'payment_id' => $payment->ulid,
            'description' => 'Application Fee',
            'unit_amount' => 10000,
            'quantity' => 1,
        ]);
    }

    public function test_transitions_application_to_payment_pending(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->application->refresh();
        $this->assertEquals(ApplicationStatus::PaymentPending, $this->application->status);
    }

    public function test_writes_status_history_entry(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $this->application->ulid,
            'from_status' => 'submitted',
            'to_status' => 'payment_pending',
            'actor_id' => $this->actor->id,
        ]);
    }

    public function test_returns_stripe_checkout_url(): void
    {
        $url = (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_mock123', $url);
    }

    public function test_throws_if_application_not_in_submitted_status(): void
    {
        $this->application->update(['status' => ApplicationStatus::Approved]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot initiate checkout/');

        (new CreateCheckoutSession)->execute($this->application, $this->actor);
    }

    private function mockStripe(string $sessionId, string $sessionUrl): void
    {
        $mockSession = new \stdClass;
        $mockSession->id = $sessionId;
        $mockSession->url = $sessionUrl;
        $mockSession->payment_intent = 'pi_test_mock456';

        $mockSessions = Mockery::mock();
        $mockSessions->shouldReceive('create')->zeroOrMoreTimes()->andReturn($mockSession);

        $mockCheckout = new \stdClass;
        $mockCheckout->sessions = $mockSessions;

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockStripe->checkout = $mockCheckout;

        $this->app->instance(StripeClient::class, $mockStripe);
    }

    private function makeApplicationWithFee(): VisaApplication
    {
        $country = Country::create(['name' => 'Checkland', 'iso2' => 'CK', 'iso3' => 'CKL']);
        $visaType = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_CK_'.uniqid(),
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 10000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Checkout',
            'last_name' => 'Tester',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'D12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Checkout Lane',
            'city' => 'Checkville',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-CK-'.uniqid(),
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
    }
}

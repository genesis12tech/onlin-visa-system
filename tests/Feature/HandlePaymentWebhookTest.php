<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\HandlePaymentWebhook;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandlePaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completed_marks_payment_succeeded(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id' => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata' => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals('pi_test_xyz789', $payment->provider_payment_intent_id);
        $this->assertNotNull($payment->succeeded_at);
    }

    public function test_checkout_completed_transitions_application_to_payment_completed(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id' => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata' => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertEquals(ApplicationStatus::PaymentCompleted, $application->fresh()->status);
    }

    public function test_checkout_completed_writes_status_history(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id' => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata' => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'from_status' => 'payment_pending',
            'to_status' => 'payment_completed',
            'actor_id' => null,
        ]);
    }

    public function test_checkout_completed_creates_invoice(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id' => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata' => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertDatabaseHas('invoices', ['payment_id' => $payment->ulid]);
    }

    public function test_checkout_completed_dispatches_generate_receipt_pdf_job(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id' => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata' => ['visa_application_ulid' => $application->ulid],
            ])
        );

        Queue::assertPushedOn('pdfs', GenerateReceiptPdf::class);
    }

    public function test_duplicate_event_is_silently_ignored(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        $eventData = [
            'id' => 'cs_test_abc123',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_xyz789',
            'metadata' => ['visa_application_ulid' => $application->ulid],
        ];

        (new HandlePaymentWebhook)->execute($this->makeEvent('checkout.session.completed', $eventData, 'evt_dupe_001'));
        (new HandlePaymentWebhook)->execute($this->makeEvent('checkout.session.completed', $eventData, 'evt_dupe_001'));

        $transitionCount = ApplicationStatusHistory::where('visa_application_id', $application->ulid)
            ->where('to_status', 'payment_completed')
            ->count();

        $this->assertEquals(1, $transitionCount);
        Queue::assertPushedTimes(GenerateReceiptPdf::class, 1);
    }

    public function test_failed_payment_marks_payment_failed(): void
    {
        [$application, $payment] = $this->makeApplicationAndPayment('pi_test_fail001');

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('payment_intent.payment_failed', [
                'id' => 'pi_test_fail001',
                'last_payment_error' => ['message' => 'Your card was declined.'],
            ])
        );

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
        $this->assertEquals('Your card was declined.', $payment->failure_reason);
    }

    public function test_failed_payment_leaves_application_in_payment_pending(): void
    {
        [$application, $payment] = $this->makeApplicationAndPayment('pi_test_fail002');

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('payment_intent.payment_failed', [
                'id' => 'pi_test_fail002',
                'last_payment_error' => ['message' => 'Insufficient funds.'],
            ])
        );

        $this->assertEquals(ApplicationStatus::PaymentPending, $application->fresh()->status);
    }

    private function makeEvent(string $type, array $data, string $eventId = 'evt_test_001'): object
    {
        return (object) [
            'id' => $eventId,
            'type' => $type,
            'data' => (object) ['object' => (object) $this->deepCastToObject($data)],
        ];
    }

    private function deepCastToObject(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = (object) $this->deepCastToObject($value);
            }
        }

        return $data;
    }

    private function makeApplicationAndPayment(?string $paymentIntentId = null): array
    {
        $country = Country::create(['name' => 'Webhook', 'iso2' => 'WH', 'iso3' => 'WHK']);
        $visaType = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_WH_'.uniqid(),
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Webhook',
            'last_name' => 'Tester',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'E12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Webhook Way',
            'city' => 'Hookville',
        ]);

        $application = VisaApplication::create([
            'tracking_number' => 'VA-WH-'.uniqid(),
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::PaymentPending,
        ]);

        $payment = Payment::create([
            'visa_application_id' => $application->ulid,
            'status' => PaymentStatus::Processing,
            'provider' => 'stripe',
            'provider_checkout_session_id' => 'cs_test_abc123',
            'provider_payment_intent_id' => $paymentIntentId,
            'amount_subtotal' => 10000,
            'amount_total' => 10000,
            'currency' => 'USD',
        ]);

        return [$application, $payment];
    }
}

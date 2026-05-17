<?php

namespace Database\Factories;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'visa_application_id' => VisaApplication::factory(),
            'status' => PaymentStatus::Pending,
            'provider' => 'stripe',
            'provider_payment_intent_id' => null,
            'provider_checkout_session_id' => null,
            'amount_subtotal' => 10000,
            'amount_total' => 10000,
            'currency' => 'USD',
            'failure_reason' => null,
            'succeeded_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => 'pi_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'provider_checkout_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'succeeded_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Processing,
            'provider_checkout_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }
}

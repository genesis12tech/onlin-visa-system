<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class CreateCheckoutSession
{
    public function execute(VisaApplication $application, User $actor, bool $priority = false): string
    {
        if ($application->status !== ApplicationStatus::Submitted) {
            throw new \RuntimeException(
                "Cannot initiate checkout for application in status: {$application->status->value}"
            );
        }

        $feeData = (new CalculateApplicationFee)->execute($application, $priority);

        $stripe = app(StripeClient::class);

        $lineItems = $feeData['items']->map(fn (array $item) => [
            'price_data' => [
                'currency' => strtolower($item['currency']),
                'product_data' => ['name' => $item['description']],
                'unit_amount' => $item['unit_amount'],
            ],
            'quantity' => $item['quantity'],
        ])->values()->all();

        // Create the local payment record first so every Stripe session has a local counterpart
        $payment = DB::transaction(function () use ($application, $feeData): Payment {
            $payment = Payment::create([
                'visa_application_id' => $application->ulid,
                'status' => PaymentStatus::Processing,
                'provider' => 'stripe',
                'amount_subtotal' => $feeData['total_amount'],
                'amount_total' => $feeData['total_amount'],
                'currency' => $feeData['currency'],
            ]);

            foreach ($feeData['items'] as $item) {
                $payment->items()->create([
                    'visa_fee_id' => $item['visa_fee_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'total_amount' => $item['total_amount'],
                ]);
            }

            return $payment;
        });

        try {
            $session = $stripe->checkout->sessions->create(
                [
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => route('payment.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('applications.pay', $application->tracking_number),
                    'metadata' => [
                        'visa_application_ulid' => $application->ulid,
                    ],
                ],
                ['idempotency_key' => 'checkout-'.$payment->ulid]
            );
        } catch (\Exception $e) {
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => $e->getMessage()]);
            throw $e;
        }

        if (! $session->url) {
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        DB::transaction(function () use ($application, $actor, $payment, $session): void {
            $payment->update(['provider_checkout_session_id' => $session->id]);

            $fromStatus = $application->status->value;

            $application->update(['status' => ApplicationStatus::PaymentPending]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::PaymentPending->value,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
        });

        return $session->url;
    }
}

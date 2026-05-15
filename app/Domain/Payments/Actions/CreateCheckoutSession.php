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
    public function execute(VisaApplication $application, User $actor): string
    {
        if ($application->status !== ApplicationStatus::Submitted) {
            throw new \RuntimeException(
                "Cannot initiate checkout for application in status: {$application->status->value}"
            );
        }

        $feeData = (new CalculateApplicationFee)->execute($application);

        $stripe = app(StripeClient::class);

        $lineItems = $feeData['items']->map(fn (array $item) => [
            'price_data' => [
                'currency' => strtolower($item['currency']),
                'product_data' => ['name' => $item['description']],
                'unit_amount' => $item['unit_amount'],
            ],
            'quantity' => $item['quantity'],
        ])->values()->all();

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => config('app.url').'/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url').'/payment/cancel',
            'metadata' => [
                'visa_application_ulid' => $application->ulid,
            ],
        ]);

        if (! $session->url) {
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }

        DB::transaction(function () use ($application, $actor, $feeData, $session): void {
            $payment = Payment::create([
                'visa_application_id' => $application->ulid,
                'status' => PaymentStatus::Processing,
                'provider' => 'stripe',
                'provider_checkout_session_id' => $session->id,
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

<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class HandlePaymentWebhook
{
    public function execute(object $event): void
    {
        $webhookEvent = PaymentWebhookEvent::firstOrCreate(
            ['event_id' => $event->id],
            [
                'provider' => 'stripe',
                'event_type' => $event->type,
                'payload' => json_decode(json_encode($event), true),
                'created_at' => now(),
            ]
        );

        if ($webhookEvent->processed_at !== null) {
            return;
        }

        try {
            DB::transaction(function () use ($event, $webhookEvent): void {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                    'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                    default => null,
                };

                $webhookEvent->markProcessed();
            });
        } catch (\Throwable $e) {
            $webhookEvent->fresh()?->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $payment = Payment::where('provider_checkout_session_id', $session->id)->first();

        if (! $payment || $payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->update([
            'provider_payment_intent_id' => $session->payment_intent ?? null,
        ]);

        (new ConfirmPayment)->execute($payment, null);
    }

    private function handlePaymentFailed(object $intent): void
    {
        $failureMessage = $intent->last_payment_error->message ?? 'Payment failed';

        $payment = Payment::where('provider_payment_intent_id', $intent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $failureMessage,
        ]);
    }
}

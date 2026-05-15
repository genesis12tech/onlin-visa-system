<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Notifications\PaymentSucceededNotification;
use Illuminate\Support\Facades\DB;

class HandlePaymentWebhook
{
    private ?string $succeededSessionId = null;

    public function execute(object $event): void
    {
        $this->succeededSessionId = null;

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

        if ($this->succeededSessionId !== null) {
            $this->notifyPaymentSucceeded($this->succeededSessionId);
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
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => $session->payment_intent ?? null,
            'succeeded_at' => now(),
        ]);

        $application = $payment->visaApplication;

        if (! $application) {
            throw new \RuntimeException("Payment {$payment->ulid} has no associated visa application.");
        }

        $application->update(['status' => ApplicationStatus::PaymentCompleted]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::PaymentPending->value,
            'to_status' => ApplicationStatus::PaymentCompleted->value,
            'actor_id' => null,
            'created_at' => now(),
        ]);

        $invoice = Invoice::create([
            'payment_id' => $payment->ulid,
            'invoice_number' => $this->generateInvoiceNumber(),
            'issued_at' => now(),
        ]);

        GenerateReceiptPdf::dispatch($invoice->ulid)->onQueue('pdfs');

        $this->succeededSessionId = $session->id;
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

    private function notifyPaymentSucceeded(string $sessionId): void
    {
        $payment = Payment::with(['visaApplication.applicantProfile.user', 'invoice'])
            ->where('provider_checkout_session_id', $sessionId)
            ->first();

        if (! $payment || ! $payment->invoice) {
            return;
        }

        $applicantUser = $payment->visaApplication?->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new PaymentSucceededNotification($payment, $payment->invoice));
        }
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $count = Invoice::whereYear('issued_at', $year)->count() + $attempt;
            $candidate = 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);

            if (! Invoice::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'INV-'.$year.'-'.now()->format('Hisu');
    }
}
